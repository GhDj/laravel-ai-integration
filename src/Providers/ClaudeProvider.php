<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Providers;

use Ghdj\AIIntegration\Contracts\AIResponseInterface;
use Ghdj\AIIntegration\Contracts\EmbeddingResponseInterface;
use Ghdj\AIIntegration\Contracts\StreamingResponseInterface;
use Ghdj\AIIntegration\DTOs\AIResponse;
use Ghdj\AIIntegration\Exceptions\AIException;
use Ghdj\AIIntegration\Exceptions\APIException;
use Ghdj\AIIntegration\Exceptions\ClaudeException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;

class ClaudeProvider extends AbstractProvider
{
    protected function createHttpClient(): Client
    {
        return new Client([
            'base_uri' => rtrim($this->getConfig('base_url', 'https://api.anthropic.com'), '/') . '/',
            'timeout' => $this->getConfig('timeout', 30),
            'headers' => [
                'x-api-key' => $this->getConfig('api_key'),
                'anthropic-version' => $this->getConfig('api_version', '2023-06-01'),
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function getName(): string
    {
        return 'claude';
    }

    public function getModels(): array
    {
        return [
            'claude-sonnet-4-20250514',
            'claude-opus-4-20250514',
            'claude-3-5-sonnet-20241022',
            'claude-3-5-haiku-20241022',
            'claude-3-opus-20240229',
            'claude-3-sonnet-20240229',
            'claude-3-haiku-20240307',
        ];
    }

    public function supportsStreaming(): bool
    {
        return true;
    }

    public function chat(array $messages, array $options = []): AIResponseInterface
    {
        $payload = $this->buildPayload($messages, $options);
        $response = $this->request('POST', 'v1/messages', ['json' => $payload]);

        return $this->parseResponse($response);
    }

    public function chatStream(array $messages, array $options = []): StreamingResponseInterface
    {
        $payload = $this->buildPayload($messages, $options);
        $payload['stream'] = true;

        $response = $this->client->request('POST', 'v1/messages', [
            'json' => $payload,
            'stream' => true,
        ]);

        return new ClaudeStreamingResponse(
            $response->getBody(),
            $payload['model']
        );
    }

    public function complete(string $prompt, array $options = []): AIResponseInterface
    {
        $systemPrompt = $options['system'] ?? null;
        unset($options['system']);

        $messages = [
            ['role' => 'user', 'content' => $prompt],
        ];

        if ($systemPrompt !== null) {
            $options['system'] = $systemPrompt;
        }

        return $this->chat($messages, $options);
    }

    public function embed(string|array $input, array $options = []): EmbeddingResponseInterface
    {
        throw new AIException('Claude does not support embeddings. Use OpenAI or Gemini for embeddings.');
    }

    public function chatWithTools(array $messages, array $tools, array $options = []): AIResponseInterface
    {
        $options['tools'] = $this->formatTools($tools);

        if (isset($options['tool_choice'])) {
            $options['tool_choice'] = $this->formatToolChoice($options['tool_choice']);
        }

        return $this->chat($messages, $options);
    }

    protected function buildPayload(array $messages, array $options): array
    {
        $model = $options['model'] ?? $this->getConfig('default_model', 'claude-sonnet-4-20250514');
        $maxTokens = $options['max_tokens'] ?? $this->getConfig('max_tokens', 4096);

        [$systemPrompt, $filteredMessages] = $this->extractSystemMessage($messages);

        if (isset($options['system'])) {
            $systemPrompt = $options['system'];
        }

        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'messages' => $this->formatMessages($filteredMessages),
        ];

        if ($systemPrompt !== null) {
            $payload['system'] = $systemPrompt;
        }

        $optionalParams = [
            'temperature',
            'top_p',
            'top_k',
            'stop_sequences',
            'tools',
            'tool_choice',
            'metadata',
        ];

        foreach ($optionalParams as $param) {
            if (isset($options[$param])) {
                $key = $param === 'stop_sequences' ? 'stop_sequences' : $param;
                $payload[$key] = $options[$param];
            }
        }

        if (isset($options['stop']) && ! isset($options['stop_sequences'])) {
            $payload['stop_sequences'] = (array) $options['stop'];
        }

        return $payload;
    }

    protected function extractSystemMessage(array $messages): array
    {
        $systemPrompt = null;
        $filtered = [];

        foreach ($messages as $message) {
            if (($message['role'] ?? '') === 'system') {
                $systemPrompt = $message['content'] ?? '';
            } else {
                $filtered[] = $message;
            }
        }

        return [$systemPrompt, $filtered];
    }

    protected function formatMessages(array $messages): array
    {
        $formatted = [];

        foreach ($messages as $message) {
            $role = $message['role'] ?? 'user';

            if ($role === 'system') {
                continue;
            }

            if ($role === 'assistant' && isset($message['tool_calls'])) {
                $formatted[] = $this->formatAssistantToolCallMessage($message);

                continue;
            }

            if ($role === 'tool') {
                $formatted[] = $this->formatToolResultMessage($message);

                continue;
            }

            $content = $message['content'] ?? '';

            if (is_array($content)) {
                $formatted[] = [
                    'role' => $role,
                    'content' => $this->formatContentBlocks($content),
                ];
            } else {
                $formatted[] = [
                    'role' => $role,
                    'content' => $content,
                ];
            }
        }

        return $formatted;
    }

    protected function formatContentBlocks(array $content): array
    {
        $blocks = [];

        foreach ($content as $block) {
            if (isset($block['type'])) {
                $blocks[] = $block;
            } elseif (isset($block['text'])) {
                $blocks[] = ['type' => 'text', 'text' => $block['text']];
            } elseif (isset($block['image_url'])) {
                $blocks[] = $this->formatImageBlock($block['image_url']);
            }
        }

        return $blocks;
    }

    protected function formatImageBlock(array|string $imageUrl): array
    {
        $url = is_array($imageUrl) ? ($imageUrl['url'] ?? '') : $imageUrl;

        if (str_starts_with($url, 'data:')) {
            preg_match('/^data:([^;]+);base64,(.+)$/', $url, $matches);

            return [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $matches[1] ?? 'image/jpeg',
                    'data' => $matches[2] ?? '',
                ],
            ];
        }

        return [
            'type' => 'image',
            'source' => [
                'type' => 'url',
                'url' => $url,
            ],
        ];
    }

    protected function formatAssistantToolCallMessage(array $message): array
    {
        $content = [];

        if (! empty($message['content'])) {
            $content[] = ['type' => 'text', 'text' => $message['content']];
        }

        foreach ($message['tool_calls'] as $toolCall) {
            $content[] = [
                'type' => 'tool_use',
                'id' => $toolCall['id'],
                'name' => $toolCall['function']['name'] ?? $toolCall['name'] ?? '',
                'input' => json_decode($toolCall['function']['arguments'] ?? '{}', true),
            ];
        }

        return [
            'role' => 'assistant',
            'content' => $content,
        ];
    }

    protected function formatToolResultMessage(array $message): array
    {
        return [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'tool_result',
                    'tool_use_id' => $message['tool_call_id'] ?? '',
                    'content' => $message['content'] ?? '',
                ],
            ],
        ];
    }

    protected function formatTools(array $tools): array
    {
        return array_map(function (array $tool) {
            if (isset($tool['input_schema'])) {
                return $tool;
            }

            $function = $tool['function'] ?? $tool;

            return [
                'name' => $function['name'] ?? '',
                'description' => $function['description'] ?? '',
                'input_schema' => $function['parameters'] ?? ['type' => 'object', 'properties' => []],
            ];
        }, $tools);
    }

    protected function formatToolChoice(string|array $choice): array
    {
        if (is_array($choice)) {
            return $choice;
        }

        return match ($choice) {
            'auto' => ['type' => 'auto'],
            'none' => ['type' => 'none'],
            'any', 'required' => ['type' => 'any'],
            default => ['type' => 'tool', 'name' => $choice],
        };
    }

    protected function request(string $method, string $endpoint, array $options = []): array
    {
        try {
            $response = $this->client->request($method, $endpoint, $options);
            $body = $response->getBody()->getContents();

            return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (ClientException|ServerException $e) {
            $response = $e->getResponse();
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true) ?? [];

            throw ClaudeException::fromResponse($data, $response->getStatusCode());
        } catch (GuzzleException $e) {
            throw new APIException(
                "Claude API request failed: {$e->getMessage()}",
                $e->getCode(),
                null,
                $e
            );
        } catch (\JsonException $e) {
            throw new APIException(
                "Failed to parse Claude API response: {$e->getMessage()}",
                0,
                null,
                $e
            );
        }
    }

    protected function parseResponse(array $response): AIResponseInterface
    {
        $content = '';
        $toolCalls = [];

        foreach ($response['content'] ?? [] as $block) {
            if ($block['type'] === 'text') {
                $content .= $block['text'];
            } elseif ($block['type'] === 'tool_use') {
                $toolCalls[] = [
                    'id' => $block['id'],
                    'type' => 'function',
                    'function' => [
                        'name' => $block['name'],
                        'arguments' => json_encode($block['input'] ?? []),
                    ],
                ];
            }
        }

        $usage = $response['usage'] ?? [];

        return new AIResponse(
            content: $content,
            role: $response['role'] ?? 'assistant',
            model: $response['model'] ?? '',
            usage: [
                'prompt_tokens' => $usage['input_tokens'] ?? 0,
                'completion_tokens' => $usage['output_tokens'] ?? 0,
                'total_tokens' => ($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0),
            ],
            finishReason: $this->mapStopReason($response['stop_reason'] ?? null),
            raw: $response,
            toolCalls: $toolCalls
        );
    }

    protected function mapStopReason(?string $stopReason): ?string
    {
        return match ($stopReason) {
            'end_turn' => 'stop',
            'max_tokens' => 'length',
            'stop_sequence' => 'stop',
            'tool_use' => 'tool_calls',
            default => $stopReason,
        };
    }
}
