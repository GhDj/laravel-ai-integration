<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Tests\Unit;

use Ghdj\AIIntegration\Contracts\AIResponseInterface;
use Ghdj\AIIntegration\Contracts\EmbeddingResponseInterface;
use Ghdj\AIIntegration\Exceptions\GeminiException;
use Ghdj\AIIntegration\Providers\GeminiProvider;
use Ghdj\AIIntegration\Tests\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class GeminiProviderTest extends TestCase
{
    private function createProviderWithMock(array $responses): GeminiProvider
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $provider = new GeminiProvider([
            'api_key' => 'test-key',
            'default_model' => 'gemini-1.5-pro',
            'default_embedding_model' => 'text-embedding-004',
        ]);

        $reflection = new \ReflectionClass($provider);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($provider, $client);

        return $provider;
    }

    public function test_it_returns_correct_name(): void
    {
        $provider = new GeminiProvider(['api_key' => 'test']);

        $this->assertEquals('gemini', $provider->getName());
    }

    public function test_it_returns_available_models(): void
    {
        $provider = new GeminiProvider(['api_key' => 'test']);

        $models = $provider->getModels();

        $this->assertContains('gemini-1.5-pro', $models);
        $this->assertContains('gemini-1.5-flash', $models);
        $this->assertContains('gemini-2.0-flash-exp', $models);
    }

    public function test_it_supports_streaming(): void
    {
        $provider = new GeminiProvider(['api_key' => 'test']);

        $this->assertTrue($provider->supportsStreaming());
    }

    public function test_chat_returns_response(): void
    {
        $provider = $this->createProviderWithMock([
            new Response(200, [], json_encode([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Hello! How can I help you today?'],
                            ],
                            'role' => 'model',
                        ],
                        'finishReason' => 'STOP',
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 10,
                    'candidatesTokenCount' => 20,
                    'totalTokenCount' => 30,
                ],
            ])),
        ]);

        $response = $provider->chat([
            ['role' => 'user', 'content' => 'Hello'],
        ]);

        $this->assertInstanceOf(AIResponseInterface::class, $response);
        $this->assertEquals('Hello! How can I help you today?', $response->getContent());
        $this->assertEquals('assistant', $response->getRole());
        $this->assertEquals('stop', $response->getFinishReason());
        $this->assertEquals(10, $response->getPromptTokens());
        $this->assertEquals(20, $response->getCompletionTokens());
        $this->assertEquals(30, $response->getTotalTokens());
    }

    public function test_it_extracts_system_message(): void
    {
        $provider = $this->createProviderWithMock([
            new Response(200, [], json_encode([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [['text' => 'Formal response']],
                            'role' => 'model',
                        ],
                        'finishReason' => 'STOP',
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 20,
                    'candidatesTokenCount' => 10,
                    'totalTokenCount' => 30,
                ],
            ])),
        ]);

        $response = $provider->chat([
            ['role' => 'system', 'content' => 'Be very formal.'],
            ['role' => 'user', 'content' => 'Hello'],
        ]);

        $this->assertEquals('Formal response', $response->getContent());
    }

    public function test_complete_wraps_prompt_in_message(): void
    {
        $provider = $this->createProviderWithMock([
            new Response(200, [], json_encode([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [['text' => 'Response']],
                            'role' => 'model',
                        ],
                        'finishReason' => 'STOP',
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 5,
                    'candidatesTokenCount' => 5,
                    'totalTokenCount' => 10,
                ],
            ])),
        ]);

        $response = $provider->complete('Hello');

        $this->assertEquals('Response', $response->getContent());
    }

    public function test_embed_returns_embeddings(): void
    {
        $embedding = array_fill(0, 768, 0.1);

        $provider = $this->createProviderWithMock([
            new Response(200, [], json_encode([
                'embedding' => [
                    'values' => $embedding,
                ],
            ])),
        ]);

        $response = $provider->embed('Test text');

        $this->assertInstanceOf(EmbeddingResponseInterface::class, $response);
        $this->assertEquals($embedding, $response->getFirstEmbedding());
        $this->assertEquals('text-embedding-004', $response->getModel());
    }

    public function test_batch_embed_returns_multiple_embeddings(): void
    {
        $embedding1 = array_fill(0, 768, 0.1);
        $embedding2 = array_fill(0, 768, 0.2);

        $provider = $this->createProviderWithMock([
            new Response(200, [], json_encode([
                'embeddings' => [
                    ['values' => $embedding1],
                    ['values' => $embedding2],
                ],
            ])),
        ]);

        $response = $provider->batchEmbed(['Text 1', 'Text 2']);

        $embeddings = $response->getEmbeddings();
        $this->assertCount(2, $embeddings);
        $this->assertEquals($embedding1, $embeddings[0]);
        $this->assertEquals($embedding2, $embeddings[1]);
    }

    public function test_chat_with_tools(): void
    {
        $provider = $this->createProviderWithMock([
            new Response(200, [], json_encode([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'functionCall' => [
                                        'name' => 'get_weather',
                                        'args' => ['location' => 'London'],
                                    ],
                                ],
                            ],
                            'role' => 'model',
                        ],
                        'finishReason' => 'TOOL_CALL',
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 50,
                    'candidatesTokenCount' => 30,
                    'totalTokenCount' => 80,
                ],
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
        $this->assertEquals('tool_calls', $response->getFinishReason());
    }

    public function test_it_throws_gemini_exception_on_error(): void
    {
        $provider = $this->createProviderWithMock([
            new Response(400, [], json_encode([
                'error' => [
                    'code' => 400,
                    'message' => 'Invalid API key',
                    'status' => 'INVALID_ARGUMENT',
                ],
            ])),
        ]);

        $this->expectException(GeminiException::class);
        $this->expectExceptionMessage('Invalid API key');

        $provider->chat([['role' => 'user', 'content' => 'Hello']]);
    }

    public function test_gemini_exception_identifies_rate_limit(): void
    {
        $provider = $this->createProviderWithMock([
            new Response(429, [], json_encode([
                'error' => [
                    'code' => 429,
                    'message' => 'Resource exhausted',
                    'status' => 'RESOURCE_EXHAUSTED',
                ],
            ])),
        ]);

        try {
            $provider->chat([['role' => 'user', 'content' => 'Hello']]);
        } catch (GeminiException $e) {
            $this->assertTrue($e->isRateLimitError());

            return;
        }

        $this->fail('Expected GeminiException was not thrown');
    }

    public function test_it_maps_finish_reasons_correctly(): void
    {
        $testCases = [
            ['finishReason' => 'STOP', 'expected' => 'stop'],
            ['finishReason' => 'MAX_TOKENS', 'expected' => 'length'],
            ['finishReason' => 'SAFETY', 'expected' => 'content_filter'],
            ['finishReason' => 'TOOL_CALL', 'expected' => 'tool_calls'],
        ];

        foreach ($testCases as $case) {
            $provider = $this->createProviderWithMock([
                new Response(200, [], json_encode([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [['text' => 'Response']],
                                'role' => 'model',
                            ],
                            'finishReason' => $case['finishReason'],
                        ],
                    ],
                    'usageMetadata' => [
                        'promptTokenCount' => 5,
                        'candidatesTokenCount' => 5,
                        'totalTokenCount' => 10,
                    ],
                ])),
            ]);

            $response = $provider->chat([['role' => 'user', 'content' => 'Test']]);
            $this->assertEquals($case['expected'], $response->getFinishReason());
        }
    }

    public function test_it_handles_optional_parameters(): void
    {
        $provider = $this->createProviderWithMock([
            new Response(200, [], json_encode([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [['text' => 'Creative response']],
                            'role' => 'model',
                        ],
                        'finishReason' => 'STOP',
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 10,
                    'candidatesTokenCount' => 10,
                    'totalTokenCount' => 20,
                ],
            ])),
        ]);

        $response = $provider->chat(
            [['role' => 'user', 'content' => 'Be creative']],
            [
                'temperature' => 0.9,
                'top_p' => 0.95,
                'top_k' => 40,
                'max_tokens' => 100,
            ]
        );

        $this->assertEquals('Creative response', $response->getContent());
    }

    public function test_it_handles_multiple_text_parts(): void
    {
        $provider = $this->createProviderWithMock([
            new Response(200, [], json_encode([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'First part. '],
                                ['text' => 'Second part.'],
                            ],
                            'role' => 'model',
                        ],
                        'finishReason' => 'STOP',
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 10,
                    'candidatesTokenCount' => 15,
                    'totalTokenCount' => 25,
                ],
            ])),
        ]);

        $response = $provider->chat([
            ['role' => 'user', 'content' => 'Hello'],
        ]);

        $this->assertEquals('First part. Second part.', $response->getContent());
    }
}
