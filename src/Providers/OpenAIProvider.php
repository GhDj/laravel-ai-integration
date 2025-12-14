<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Providers;

use Ghdj\AIIntegration\Contracts\AIResponseInterface;
use Ghdj\AIIntegration\Contracts\EmbeddingResponseInterface;
use Ghdj\AIIntegration\Contracts\StreamingResponseInterface;
use Ghdj\AIIntegration\DTOs\AIResponse;
use Ghdj\AIIntegration\DTOs\EmbeddingResponse;
use Ghdj\AIIntegration\DTOs\StreamingResponse;
use Ghdj\AIIntegration\Exceptions\APIException;
use Ghdj\AIIntegration\Exceptions\OpenAIException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;

class OpenAIProvider extends AbstractProvider
{
    protected function createHttpClient(): Client
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->getConfig('api_key'),
            'Content-Type' => 'application/json',
        ];

        if ($organization = $this->getConfig('organization')) {
            $headers['OpenAI-Organization'] = $organization;
        }

        return new Client([
            'base_uri' => rtrim($this->getConfig('base_url', 'https://api.openai.com/v1'), '/') . '/',
            'timeout' => $this->getConfig('timeout', 30),
            'headers' => $headers,
        ]);
    }

    public function getName(): string
    {
        return 'openai';
    }

    public function getModels(): array
    {
        return [
            'gpt-4o',
            'gpt-4o-mini',
            'gpt-4-turbo',
            'gpt-4',
            'gpt-3.5-turbo',
            'o1-preview',
            'o1-mini',
        ];
    }

    public function getEmbeddingModels(): array
    {
        return [
            'text-embedding-3-small',
            'text-embedding-3-large',
            'text-embedding-ada-002',
        ];
    }

    public function supportsStreaming(): bool
    {
        return true;
    }

    public function chat(array $messages, array $options = []): AIResponseInterface
    {
        $payload = $this->buildChatPayload($messages, $options);
        $response = $this->request('POST', 'chat/completions', ['json' => $payload]);

        return $this->parseResponse($response);
    }

    public function chatStream(array $messages, array $options = []): StreamingResponseInterface
    {
        $payload = $this->buildChatPayload($messages, $options);
        $payload['stream'] = true;
        $payload['stream_options'] = ['include_usage' => true];

        $response = $this->client->request('POST', 'chat/completions', [
            'json' => $payload,
            'stream' => true,
        ]);

        return new StreamingResponse(
            $response->getBody(),
            $payload['model']
        );
    }

    public function complete(string $prompt, array $options = []): AIResponseInterface
    {
        $systemPrompt = $options['system'] ?? null;
        unset($options['system']);

        $messages = [];

        if ($systemPrompt !== null) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        $messages[] = ['role' => 'user', 'content' => $prompt];

        return $this->chat($messages, $options);
    }

    public function embed(string|array $input, array $options = []): EmbeddingResponseInterface
    {
        $model = $options['model'] ?? $this->getConfig('default_embedding_model', 'text-embedding-3-small');
        $dimensions = $options['dimensions'] ?? null;

        $payload = [
            'model' => $model,
            'input' => $input,
        ];

        if ($dimensions !== null && $model !== 'text-embedding-ada-002') {
            $payload['dimensions'] = $dimensions;
        }

        $response = $this->request('POST', 'embeddings', ['json' => $payload]);

        return $this->parseEmbeddingResponse($response);
    }

    public function chatWithTools(array $messages, array $tools, array $options = []): AIResponseInterface
    {
        $options['tools'] = $this->formatTools($tools);

        if (isset($options['tool_choice'])) {
            $options['tool_choice'] = $this->formatToolChoice($options['tool_choice']);
        }

        return $this->chat($messages, $options);
    }

    public function chatWithJsonMode(array $messages, array $options = []): AIResponseInterface
    {
        $options['response_format'] = ['type' => 'json_object'];

        $hasJsonInstruction = false;
        foreach ($messages as $message) {
            if (stripos($message['content'] ?? '', 'json') !== false) {
                $hasJsonInstruction = true;

                break;
            }
        }

        if (! $hasJsonInstruction) {
            array_unshift($messages, [
                'role' => 'system',
                'content' => 'Respond with valid JSON.',
            ]);
        }

        return $this->chat($messages, $options);
    }

    public function listModels(): array
    {
        $response = $this->request('GET', 'models');

        return array_map(
            fn (array $model) => $model['id'],
            $response['data'] ?? []
        );
    }

    protected function buildChatPayload(array $messages, array $options): array
    {
        $model = $options['model'] ?? $this->getConfig('default_model', 'gpt-4o');
        $maxTokens = $options['max_tokens'] ?? $this->getConfig('max_tokens', 4096);

        $payload = [
            'model' => $model,
            'messages' => $messages,
        ];

        if (! $this->isReasoningModel($model)) {
            $payload['max_tokens'] = $maxTokens;
        } else {
            if (isset($options['max_completion_tokens'])) {
                $payload['max_completion_tokens'] = $options['max_completion_tokens'];
            }
        }

        $optionalParams = [
            'temperature',
            'top_p',
            'frequency_penalty',
            'presence_penalty',
            'stop',
            'tools',
            'tool_choice',
            'response_format',
            'seed',
            'user',
            'logprobs',
            'top_logprobs',
        ];

        foreach ($optionalParams as $param) {
            if (isset($options[$param])) {
                $payload[$param] = $options[$param];
            }
        }

        return $payload;
    }

    protected function isReasoningModel(string $model): bool
    {
        return str_starts_with($model, 'o1-') || str_starts_with($model, 'o3-');
    }

    protected function formatTools(array $tools): array
    {
        return array_map(function (array $tool) {
            if (isset($tool['type']) && $tool['type'] === 'function') {
                return $tool;
            }

            return [
                'type' => 'function',
                'function' => $tool,
            ];
        }, $tools);
    }

    protected function formatToolChoice(string|array $choice): string|array
    {
        if (is_string($choice) && ! in_array($choice, ['auto', 'none', 'required'])) {
            return [
                'type' => 'function',
                'function' => ['name' => $choice],
            ];
        }

        return $choice;
    }

    protected function request(string $method, string $endpoint, array $options = []): array
    {
        try {
            $response = $this->client->request($method, $endpoint, $options);
            $body = $response->getBody()->getContents();

            return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (ClientException $e) {
            $response = $e->getResponse();
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true) ?? [];

            throw OpenAIException::fromResponse($data, $response->getStatusCode());
        } catch (GuzzleException $e) {
            throw new APIException(
                "OpenAI API request failed: {$e->getMessage()}",
                $e->getCode(),
                null,
                $e
            );
        } catch (\JsonException $e) {
            throw new APIException(
                "Failed to parse OpenAI API response: {$e->getMessage()}",
                0,
                null,
                $e
            );
        }
    }

    protected function parseResponse(array $response): AIResponseInterface
    {
        $choice = $response['choices'][0] ?? [];
        $message = $choice['message'] ?? [];

        $content = $message['content'] ?? '';
        $toolCalls = $message['tool_calls'] ?? [];

        return new AIResponse(
            content: $content,
            role: $message['role'] ?? 'assistant',
            model: $response['model'] ?? '',
            usage: $response['usage'] ?? [],
            finishReason: $choice['finish_reason'] ?? null,
            raw: $response,
            toolCalls: $toolCalls
        );
    }

    protected function parseEmbeddingResponse(array $response): EmbeddingResponseInterface
    {
        $embeddings = array_map(
            fn (array $item) => $item['embedding'] ?? [],
            $response['data'] ?? []
        );

        return new EmbeddingResponse(
            embeddings: $embeddings,
            model: $response['model'] ?? '',
            usage: $response['usage'] ?? [],
            raw: $response
        );
    }
}
