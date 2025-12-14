<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Ghdj\AIIntegration\Contracts\AIResponseInterface;
use Ghdj\AIIntegration\Contracts\EmbeddingResponseInterface;
use Ghdj\AIIntegration\Exceptions\OpenAIException;
use Ghdj\AIIntegration\Providers\OpenAIProvider;
use Ghdj\AIIntegration\Tests\TestCase;

class OpenAIProviderTest extends TestCase
{
    private function createProviderWithMock(array $responses): OpenAIProvider
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $provider = new OpenAIProvider([
            'api_key' => 'test-key',
            'default_model' => 'gpt-4o',
            'default_embedding_model' => 'text-embedding-3-small',
        ]);

        $reflection = new \ReflectionClass($provider);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($provider, $client);

        return $provider;
    }

    public function test_it_returns_correct_name(): void
    {
        $provider = new OpenAIProvider(['api_key' => 'test']);

        $this->assertEquals('openai', $provider->getName());
    }

    public function test_it_returns_available_models(): void
    {
        $provider = new OpenAIProvider(['api_key' => 'test']);

        $models = $provider->getModels();

        $this->assertContains('gpt-4o', $models);
        $this->assertContains('gpt-4o-mini', $models);
        $this->assertContains('gpt-3.5-turbo', $models);
    }

    public function test_it_supports_streaming(): void
    {
        $provider = new OpenAIProvider(['api_key' => 'test']);

        $this->assertTrue($provider->supportsStreaming());
    }

    public function test_chat_returns_response(): void
    {
        $provider = $this->createProviderWithMock([
            new Response(200, [], json_encode([
                'id' => 'chatcmpl-123',
                'model' => 'gpt-4o',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Hello! How can I help you today?',
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 20,
                    'total_tokens' => 30,
                ],
            ])),
        ]);

        $response = $provider->chat([
            ['role' => 'user', 'content' => 'Hello'],
        ]);

        $this->assertInstanceOf(AIResponseInterface::class, $response);
        $this->assertEquals('Hello! How can I help you today?', $response->getContent());
        $this->assertEquals('assistant', $response->getRole());
        $this->assertEquals('gpt-4o', $response->getModel());
        $this->assertEquals('stop', $response->getFinishReason());
        $this->assertEquals(10, $response->getPromptTokens());
        $this->assertEquals(20, $response->getCompletionTokens());
        $this->assertEquals(30, $response->getTotalTokens());
    }

    public function test_complete_wraps_prompt_in_message(): void
    {
        $provider = $this->createProviderWithMock([
            new Response(200, [], json_encode([
                'model' => 'gpt-4o',
                'choices' => [
                    [
                        'message' => ['role' => 'assistant', 'content' => 'Response'],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 5, 'total_tokens' => 10],
            ])),
        ]);

        $response = $provider->complete('Hello');

        $this->assertEquals('Response', $response->getContent());
    }

    public function test_complete_includes_system_prompt(): void
    {
        $provider = $this->createProviderWithMock([
            new Response(200, [], json_encode([
                'model' => 'gpt-4o',
                'choices' => [
                    [
                        'message' => ['role' => 'assistant', 'content' => 'Formal response'],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => ['prompt_tokens' => 15, 'completion_tokens' => 5, 'total_tokens' => 20],
            ])),
        ]);

        $response = $provider->complete('Hello', [
            'system' => 'You are a formal assistant.',
        ]);

        $this->assertEquals('Formal response', $response->getContent());
    }

    public function test_embed_returns_embeddings(): void
    {
        $embedding = array_fill(0, 1536, 0.1);

        $provider = $this->createProviderWithMock([
            new Response(200, [], json_encode([
                'model' => 'text-embedding-3-small',
                'data' => [
                    ['embedding' => $embedding, 'index' => 0],
                ],
                'usage' => ['prompt_tokens' => 5, 'total_tokens' => 5],
            ])),
        ]);

        $response = $provider->embed('Test text');

        $this->assertInstanceOf(EmbeddingResponseInterface::class, $response);
        $this->assertEquals($embedding, $response->getFirstEmbedding());
        $this->assertEquals('text-embedding-3-small', $response->getModel());
        $this->assertEquals(5, $response->getTotalTokens());
    }

    public function test_embed_handles_multiple_inputs(): void
    {
        $embedding1 = array_fill(0, 1536, 0.1);
        $embedding2 = array_fill(0, 1536, 0.2);

        $provider = $this->createProviderWithMock([
            new Response(200, [], json_encode([
                'model' => 'text-embedding-3-small',
                'data' => [
                    ['embedding' => $embedding1, 'index' => 0],
                    ['embedding' => $embedding2, 'index' => 1],
                ],
                'usage' => ['prompt_tokens' => 10, 'total_tokens' => 10],
            ])),
        ]);

        $response = $provider->embed(['Text 1', 'Text 2']);

        $embeddings = $response->getEmbeddings();
        $this->assertCount(2, $embeddings);
        $this->assertEquals($embedding1, $embeddings[0]);
        $this->assertEquals($embedding2, $embeddings[1]);
    }

    public function test_chat_with_tools_formats_tools_correctly(): void
    {
        $provider = $this->createProviderWithMock([
            new Response(200, [], json_encode([
                'model' => 'gpt-4o',
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => null,
                            'tool_calls' => [
                                [
                                    'id' => 'call_123',
                                    'type' => 'function',
                                    'function' => [
                                        'name' => 'get_weather',
                                        'arguments' => '{"location":"London"}',
                                    ],
                                ],
                            ],
                        ],
                        'finish_reason' => 'tool_calls',
                    ],
                ],
                'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 20, 'total_tokens' => 70],
            ])),
        ]);

        $tools = [
            [
                'name' => 'get_weather',
                'description' => 'Get current weather',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'location' => ['type' => 'string'],
                    ],
                ],
            ],
        ];

        $response = $provider->chatWithTools(
            [['role' => 'user', 'content' => 'What is the weather in London?']],
            $tools
        );

        $this->assertTrue($response->hasToolCalls());
        $toolCalls = $response->getToolCalls();
        $this->assertCount(1, $toolCalls);
        $this->assertEquals('get_weather', $toolCalls[0]['function']['name']);
    }

    public function test_chat_with_json_mode(): void
    {
        $provider = $this->createProviderWithMock([
            new Response(200, [], json_encode([
                'model' => 'gpt-4o',
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => '{"name": "John", "age": 30}',
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 10, 'total_tokens' => 30],
            ])),
        ]);

        $response = $provider->chatWithJsonMode([
            ['role' => 'user', 'content' => 'Return JSON with name and age'],
        ]);

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('John', $content['name']);
        $this->assertEquals(30, $content['age']);
    }

    public function test_it_throws_openai_exception_on_error(): void
    {
        $provider = $this->createProviderWithMock([
            new Response(401, [], json_encode([
                'error' => [
                    'message' => 'Invalid API key',
                    'type' => 'invalid_request_error',
                    'code' => 'invalid_api_key',
                ],
            ])),
        ]);

        $this->expectException(OpenAIException::class);
        $this->expectExceptionMessage('Invalid API key');

        $provider->chat([['role' => 'user', 'content' => 'Hello']]);
    }

    public function test_openai_exception_identifies_rate_limit(): void
    {
        $provider = $this->createProviderWithMock([
            new Response(429, [], json_encode([
                'error' => [
                    'message' => 'Rate limit exceeded',
                    'type' => 'rate_limit_exceeded',
                    'code' => 'rate_limit_exceeded',
                ],
            ])),
        ]);

        try {
            $provider->chat([['role' => 'user', 'content' => 'Hello']]);
        } catch (OpenAIException $e) {
            $this->assertTrue($e->isRateLimitError());
            return;
        }

        $this->fail('Expected OpenAIException was not thrown');
    }

    public function test_it_handles_optional_parameters(): void
    {
        $provider = $this->createProviderWithMock([
            new Response(200, [], json_encode([
                'model' => 'gpt-4o',
                'choices' => [
                    [
                        'message' => ['role' => 'assistant', 'content' => 'Creative response'],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ])),
        ]);

        $response = $provider->chat(
            [['role' => 'user', 'content' => 'Be creative']],
            [
                'temperature' => 0.9,
                'top_p' => 0.95,
                'max_tokens' => 100,
                'frequency_penalty' => 0.5,
                'presence_penalty' => 0.5,
            ]
        );

        $this->assertEquals('Creative response', $response->getContent());
    }

    public function test_reasoning_models_use_max_completion_tokens(): void
    {
        $provider = $this->createProviderWithMock([
            new Response(200, [], json_encode([
                'model' => 'o1-preview',
                'choices' => [
                    [
                        'message' => ['role' => 'assistant', 'content' => 'Reasoned response'],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 100, 'total_tokens' => 110],
            ])),
        ]);

        $response = $provider->chat(
            [['role' => 'user', 'content' => 'Think step by step']],
            [
                'model' => 'o1-preview',
                'max_completion_tokens' => 1000,
            ]
        );

        $this->assertEquals('Reasoned response', $response->getContent());
    }
}
