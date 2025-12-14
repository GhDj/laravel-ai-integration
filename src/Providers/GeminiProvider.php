<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Ghdj\AIIntegration\Contracts\AIResponseInterface;
use Ghdj\AIIntegration\Contracts\EmbeddingResponseInterface;
use Ghdj\AIIntegration\Contracts\StreamingResponseInterface;
use Ghdj\AIIntegration\DTOs\AIResponse;
use Ghdj\AIIntegration\DTOs\EmbeddingResponse;
use Ghdj\AIIntegration\Exceptions\APIException;
use Ghdj\AIIntegration\Exceptions\GeminiException;

class GeminiProvider extends AbstractProvider
{
    protected function createHttpClient(): Client
    {
        return new Client([
            'base_uri' => rtrim($this->getConfig('base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/') . '/',
            'timeout' => $this->getConfig('timeout', 30),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function getName(): string
    {
        return 'gemini';
    }

    public function getModels(): array
    {
        return [
            'gemini-2.0-flash-exp',
            'gemini-1.5-pro',
            'gemini-1.5-pro-latest',
            'gemini-1.5-flash',
            'gemini-1.5-flash-latest',
            'gemini-1.0-pro',
        ];
    }

    public function getEmbeddingModels(): array
    {
        return [
            'text-embedding-004',
            'embedding-001',
        ];
    }

    public function supportsStreaming(): bool
    {
        return true;
    }

    public function chat(array $messages, array $options = []): AIResponseInterface
    {
        $model = $options['model'] ?? $this->getConfig('default_model', 'gemini-1.5-pro');
        $payload = $this->buildPayload($messages, $options);

        $endpoint = "models/{$model}:generateContent";
        $response = $this->request('POST', $endpoint, ['json' => $payload]);

        return $this->parseResponse($response, $model);
    }

    public function chatStream(array $messages, array $options = []): StreamingResponseInterface
    {
        $model = $options['model'] ?? $this->getConfig('default_model', 'gemini-1.5-pro');
        $payload = $this->buildPayload($messages, $options);

        $endpoint = "models/{$model}:streamGenerateContent?alt=sse";

        $response = $this->client->request('POST', $endpoint . '&key=' . $this->getConfig('api_key'), [
            'json' => $payload,
            'stream' => true,
        ]);

        return new GeminiStreamingResponse($response->getBody(), $model);
    }

    public function complete(string $prompt, array $options = []): AIResponseInterface
    {
        $systemPrompt = $options['system'] ?? null;
        unset($options['system']);

        $messages = [];

        if ($systemPrompt !== null) {
            $options['systemInstruction'] = $systemPrompt;
        }

        $messages[] = ['role' => 'user', 'content' => $prompt];

        return $this->chat($messages, $options);
    }

    public function embed(string|array $input, array $options = []): EmbeddingResponseInterface
    {
        $model = $options['model'] ?? $this->getConfig('default_embedding_model', 'text-embedding-004');
        $inputs = is_array($input) ? $input : [$input];

        $embeddings = [];
        $totalTokens = 0;

        foreach ($inputs as $text) {
            $payload = [
                'model' => "models/{$model}",
                'content' => [
                    'parts' => [['text' => $text]],
                ],
            ];

            if (isset($options['task_type'])) {
                $payload['taskType'] = $options['task_type'];
            }

            if (isset($options['title'])) {
                $payload['title'] = $options['title'];
            }

            $endpoint = "models/{$model}:embedContent";
            $response = $this->request('POST', $endpoint, ['json' => $payload]);

            $embeddings[] = $response['embedding']['values'] ?? [];
        }

        return new EmbeddingResponse(
            embeddings: $embeddings,
            model: $model,
            usage: ['total_tokens' => $totalTokens],
            raw: ['model' => $model, 'embeddings_count' => count($embeddings)]
        );
    }

    public function batchEmbed(array $inputs, array $options = []): EmbeddingResponseInterface
    {
        $model = $options['model'] ?? $this->getConfig('default_embedding_model', 'text-embedding-004');

        $requests = array_map(function (string $text) use ($model, $options) {
            $request = [
                'model' => "models/{$model}",
                'content' => [
                    'parts' => [['text' => $text]],
                ],
            ];

            if (isset($options['task_type'])) {
                $request['taskType'] = $options['task_type'];
            }

            return $request;
        }, $inputs);

        $endpoint = "models/{$model}:batchEmbedContents";
        $response = $this->request('POST', $endpoint, [
            'json' => ['requests' => $requests],
        ]);

        $embeddings = array_map(
            fn($embedding) => $embedding['values'] ?? [],
            $response['embeddings'] ?? []
        );

        return new EmbeddingResponse(
            embeddings: $embeddings,
            model: $model,
            usage: ['total_tokens' => 0],
            raw: $response
        );
    }

    public function chatWithTools(array $messages, array $tools, array $options = []): AIResponseInterface
    {
        $options['tools'] = $this->formatTools($tools);

        if (isset($options['tool_choice'])) {
            $options['toolConfig'] = $this->formatToolConfig($options['tool_choice']);
            unset($options['tool_choice']);
        }

        return $this->chat($messages, $options);
    }

    protected function buildPayload(array $messages, array $options): array
    {
        [$systemInstruction, $filteredMessages] = $this->extractSystemMessage($messages);

        if (isset($options['systemInstruction'])) {
            $systemInstruction = $options['systemInstruction'];
        }

        $payload = [
            'contents' => $this->formatMessages($filteredMessages),
        ];

        if ($systemInstruction !== null) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $systemInstruction]],
            ];
        }

        $generationConfig = [];

        if (isset($options['max_tokens'])) {
            $generationConfig['maxOutputTokens'] = $options['max_tokens'];
        } elseif ($maxTokens = $this->getConfig('max_tokens')) {
            $generationConfig['maxOutputTokens'] = $maxTokens;
        }

        if (isset($options['temperature'])) {
            $generationConfig['temperature'] = $options['temperature'];
        }

        if (isset($options['top_p'])) {
            $generationConfig['topP'] = $options['top_p'];
        }

        if (isset($options['top_k'])) {
            $generationConfig['topK'] = $options['top_k'];
        }

        if (isset($options['stop']) || isset($options['stop_sequences'])) {
            $generationConfig['stopSequences'] = (array) ($options['stop'] ?? $options['stop_sequences']);
        }

        if (isset($options['response_mime_type'])) {
            $generationConfig['responseMimeType'] = $options['response_mime_type'];
        }

        if (!empty($generationConfig)) {
            $payload['generationConfig'] = $generationConfig;
        }

        if (isset($options['tools'])) {
            $payload['tools'] = $options['tools'];
        }

        if (isset($options['toolConfig'])) {
            $payload['toolConfig'] = $options['toolConfig'];
        }

        if (isset($options['safetySettings'])) {
            $payload['safetySettings'] = $options['safetySettings'];
        }

        return $payload;
    }

    protected function extractSystemMessage(array $messages): array
    {
        $systemInstruction = null;
        $filtered = [];

        foreach ($messages as $message) {
            if (($message['role'] ?? '') === 'system') {
                $systemInstruction = $message['content'] ?? '';
            } else {
                $filtered[] = $message;
            }
        }

        return [$systemInstruction, $filtered];
    }

    protected function formatMessages(array $messages): array
    {
        $formatted = [];

        foreach ($messages as $message) {
            $role = $message['role'] ?? 'user';

            if ($role === 'system') {
                continue;
            }

            $geminiRole = match ($role) {
                'assistant' => 'model',
                'tool' => 'function',
                default => 'user',
            };

            $content = $message['content'] ?? '';

            if ($role === 'tool' || $role === 'function') {
                $formatted[] = [
                    'role' => 'function',
                    'parts' => [
                        [
                            'functionResponse' => [
                                'name' => $message['name'] ?? $message['tool_call_id'] ?? 'function',
                                'response' => [
                                    'result' => $content,
                                ],
                            ],
                        ],
                    ],
                ];
                continue;
            }

            if (isset($message['tool_calls'])) {
                $parts = [];
                foreach ($message['tool_calls'] as $toolCall) {
                    $parts[] = [
                        'functionCall' => [
                            'name' => $toolCall['function']['name'] ?? $toolCall['name'] ?? '',
                            'args' => json_decode($toolCall['function']['arguments'] ?? '{}', true),
                        ],
                    ];
                }
                $formatted[] = ['role' => 'model', 'parts' => $parts];
                continue;
            }

            if (is_array($content)) {
                $formatted[] = [
                    'role' => $geminiRole,
                    'parts' => $this->formatContentParts($content),
                ];
            } else {
                $formatted[] = [
                    'role' => $geminiRole,
                    'parts' => [['text' => $content]],
                ];
            }
        }

        return $formatted;
    }

    protected function formatContentParts(array $content): array
    {
        $parts = [];

        foreach ($content as $block) {
            if (isset($block['type'])) {
                if ($block['type'] === 'text') {
                    $parts[] = ['text' => $block['text'] ?? ''];
                } elseif ($block['type'] === 'image_url') {
                    $parts[] = $this->formatImagePart($block['image_url']);
                }
            } elseif (isset($block['text'])) {
                $parts[] = ['text' => $block['text']];
            } elseif (isset($block['image_url'])) {
                $parts[] = $this->formatImagePart($block['image_url']);
            }
        }

        return $parts;
    }

    protected function formatImagePart(array|string $imageUrl): array
    {
        $url = is_array($imageUrl) ? ($imageUrl['url'] ?? '') : $imageUrl;

        if (str_starts_with($url, 'data:')) {
            preg_match('/^data:([^;]+);base64,(.+)$/', $url, $matches);

            return [
                'inlineData' => [
                    'mimeType' => $matches[1] ?? 'image/jpeg',
                    'data' => $matches[2] ?? '',
                ],
            ];
        }

        return [
            'fileData' => [
                'mimeType' => 'image/jpeg',
                'fileUri' => $url,
            ],
        ];
    }

    protected function formatTools(array $tools): array
    {
        $functionDeclarations = array_map(function (array $tool) {
            $function = $tool['function'] ?? $tool;

            return [
                'name' => $function['name'] ?? '',
                'description' => $function['description'] ?? '',
                'parameters' => $function['parameters'] ?? ['type' => 'object', 'properties' => []],
            ];
        }, $tools);

        return [
            ['functionDeclarations' => $functionDeclarations],
        ];
    }

    protected function formatToolConfig(string|array $choice): array
    {
        if (is_array($choice)) {
            return $choice;
        }

        return match ($choice) {
            'auto' => ['functionCallingConfig' => ['mode' => 'AUTO']],
            'none' => ['functionCallingConfig' => ['mode' => 'NONE']],
            'any', 'required' => ['functionCallingConfig' => ['mode' => 'ANY']],
            default => [
                'functionCallingConfig' => [
                    'mode' => 'ANY',
                    'allowedFunctionNames' => [$choice],
                ],
            ],
        };
    }

    protected function request(string $method, string $endpoint, array $options = []): array
    {
        $apiKey = $this->getConfig('api_key');
        $separator = str_contains($endpoint, '?') ? '&' : '?';
        $endpoint .= "{$separator}key={$apiKey}";

        try {
            $response = $this->client->request($method, $endpoint, $options);
            $body = $response->getBody()->getContents();

            return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (ClientException $e) {
            $response = $e->getResponse();
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true) ?? [];

            throw GeminiException::fromResponse($data, $response->getStatusCode());
        } catch (GuzzleException $e) {
            throw new APIException(
                "Gemini API request failed: {$e->getMessage()}",
                $e->getCode(),
                null,
                $e
            );
        } catch (\JsonException $e) {
            throw new APIException(
                "Failed to parse Gemini API response: {$e->getMessage()}",
                0,
                null,
                $e
            );
        }
    }

    protected function parseResponse(array $response, string $model): AIResponseInterface
    {
        $candidate = $response['candidates'][0] ?? [];
        $content = $candidate['content'] ?? [];
        $parts = $content['parts'] ?? [];

        $text = '';
        $toolCalls = [];

        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $text .= $part['text'];
            } elseif (isset($part['functionCall'])) {
                $toolCalls[] = [
                    'id' => 'call_' . uniqid(),
                    'type' => 'function',
                    'function' => [
                        'name' => $part['functionCall']['name'] ?? '',
                        'arguments' => json_encode($part['functionCall']['args'] ?? []),
                    ],
                ];
            }
        }

        $usageMetadata = $response['usageMetadata'] ?? [];

        return new AIResponse(
            content: $text,
            role: 'assistant',
            model: $model,
            usage: [
                'prompt_tokens' => $usageMetadata['promptTokenCount'] ?? 0,
                'completion_tokens' => $usageMetadata['candidatesTokenCount'] ?? 0,
                'total_tokens' => $usageMetadata['totalTokenCount'] ?? 0,
            ],
            finishReason: $this->mapFinishReason($candidate['finishReason'] ?? null),
            raw: $response,
            toolCalls: $toolCalls
        );
    }

    protected function mapFinishReason(?string $finishReason): ?string
    {
        return match ($finishReason) {
            'STOP' => 'stop',
            'MAX_TOKENS' => 'length',
            'SAFETY' => 'content_filter',
            'RECITATION' => 'content_filter',
            'TOOL_CALL', 'FUNCTION_CALL' => 'tool_calls',
            default => $finishReason,
        };
    }
}
