<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Ghdj\AIIntegration\Contracts\AIResponseInterface;
use Ghdj\AIIntegration\Exceptions\AIException;
use Ghdj\AIIntegration\Exceptions\ClaudeException;
use Ghdj\AIIntegration\Providers\ClaudeProvider;
use Ghdj\AIIntegration\Tests\TestCase;

class ClaudeProviderTest extends TestCase
{
    private function createProviderWithMock(array $responses): ClaudeProvider
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $provider = new ClaudeProvider([
            'api_key' => 'test-key',
            'default_model' => 'claude-sonnet-4-20250514',
            'api_version' => '2023-06-01',
        ]);

        $reflection = new \ReflectionClass($provider);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($provider, $client);

        return $provider;
    }

    public function test_it_returns_correct_name(): void
    {
        $provider = new ClaudeProvider(['api_key' => 'test']);

        $this->assertEquals('claude', $provider->getName());
    }

    public function test_it_returns_available_models(): void
    {
        $provider = new ClaudeProvider(['api_key' => 'test']);

        $models = $provider->getModels();

        $this->assertContains('claude-sonnet-4-20250514', $models);
        $this->assertContains('claude-3-5-sonnet-20241022', $models);
        $this->assertContains('claude-3-opus-20240229', $models);
    }

    public function test_it_supports_streaming(): void
    {
        $provider = new ClaudeProvider(['api_key' => 'test']);

        $this->assertTrue($provider->supportsStreaming());
    }

    public function test_chat_returns_response(): void
    {
        $provider = $this->createProviderWithMock([
            new Response(200, [], json_encode([
                'id' => 'msg_123',
                'type' => 'message',
                'role' => 'assistant',
                'model' => 'claude-sonnet-4-20250514',
                'content' => [
                    ['type' => 'text', 'text' => 'Hello! How can I help you today?'],
                ],
                'stop_reason' => 'end_turn',
                'usage' => [
                    'input_tokens' => 10,
                    'output_tokens' => 20,
                ],
            ])),
        ]);

        $response = $provider->chat([
            ['role' => 'user', 'content' => 'Hello'],
        ]);

        $this->assertInstanceOf(AIResponseInterface::class, $response);
        $this->assertEquals('Hello! How can I help you today?', $response->getContent());
        $this->assertEquals('assistant', $response->getRole());
        $this->assertEquals('claude-sonnet-4-20250514', $response->getModel());
        $this->assertEquals('stop', $response->getFinishReason());
        $this->assertEquals(10, $response->getPromptTokens());
        $this->assertEquals(20, $response->getCompletionTokens());
        $this->assertEquals(30, $response->getTotalTokens());
    }

    public function test_it_extracts_system_message(): void
    {
        $provider = $this->createProviderWithMock([
            new Response(200, [], json_encode([
                'role' => 'assistant',
                'model' => 'claude-sonnet-4-20250514',
                'content' => [['type' => 'text', 'text' => 'Formal response']],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 20, 'output_tokens' => 10],
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
                'role' => 'assistant',
                'model' => 'claude-sonnet-4-20250514',
                'content' => [['type' => 'text', 'text' => 'Response']],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 5, 'output_tokens' => 5],
            ])),
        ]);

        $response = $provider->complete('Hello');

        $this->assertEquals('Response', $response->getContent());
    }

    public function test_complete_with_system_option(): void
    {
        $provider = $this->createProviderWithMock([
            new Response(200, [], json_encode([
                'role' => 'assistant',
                'model' => 'claude-sonnet-4-20250514',
                'content' => [['type' => 'text', 'text' => 'Helpful response']],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 15, 'output_tokens' => 5],
            ])),
        ]);

        $response = $provider->complete('Hello', [
            'system' => 'You are a helpful assistant.',
        ]);

        $this->assertEquals('Helpful response', $response->getContent());
    }

    public function test_embed_throws_exception(): void
    {
        $provider = new ClaudeProvider(['api_key' => 'test']);

        $this->expectException(AIException::class);
        $this->expectExceptionMessage('Claude does not support embeddings');

        $provider->embed('Test text');
    }

    public function test_chat_with_tools(): void
    {
        $provider = $this->createProviderWithMock([
            new Response(200, [], json_encode([
                'role' => 'assistant',
                'model' => 'claude-sonnet-4-20250514',
                'content' => [
                    [
                        'type' => 'tool_use',
                        'id' => 'toolu_123',
                        'name' => 'get_weather',
                        'input' => ['location' => 'London'],
                    ],
                ],
                'stop_reason' => 'tool_use',
                'usage' => ['input_tokens' => 50, 'output_tokens' => 30],
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

    public function test_it_throws_claude_exception_on_error(): void
    {
        $provider = $this->createProviderWithMock([
            new Response(401, [], json_encode([
                'error' => [
                    'type' => 'authentication_error',
                    'message' => 'Invalid API key',
                ],
            ])),
        ]);

        $this->expectException(ClaudeException::class);
        $this->expectExceptionMessage('Invalid API key');

        $provider->chat([['role' => 'user', 'content' => 'Hello']]);
    }

    public function test_claude_exception_identifies_rate_limit(): void
    {
        $provider = $this->createProviderWithMock([
            new Response(429, [], json_encode([
                'error' => [
                    'type' => 'rate_limit_error',
                    'message' => 'Rate limit exceeded',
                ],
            ])),
        ]);

        try {
            $provider->chat([['role' => 'user', 'content' => 'Hello']]);
        } catch (ClaudeException $e) {
            $this->assertTrue($e->isRateLimitError());
            return;
        }

        $this->fail('Expected ClaudeException was not thrown');
    }

    public function test_claude_exception_identifies_overloaded(): void
    {
        $provider = $this->createProviderWithMock([
            new Response(529, [], json_encode([
                'error' => [
                    'type' => 'overloaded_error',
                    'message' => 'API is overloaded',
                ],
            ])),
        ]);

        try {
            $provider->chat([['role' => 'user', 'content' => 'Hello']]);
        } catch (ClaudeException $e) {
            $this->assertTrue($e->isOverloadedError());
            return;
        }

        $this->fail('Expected ClaudeException was not thrown');
    }

    public function test_it_handles_multiple_content_blocks(): void
    {
        $provider = $this->createProviderWithMock([
            new Response(200, [], json_encode([
                'role' => 'assistant',
                'model' => 'claude-sonnet-4-20250514',
                'content' => [
                    ['type' => 'text', 'text' => 'First part. '],
                    ['type' => 'text', 'text' => 'Second part.'],
                ],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 10, 'output_tokens' => 15],
            ])),
        ]);

        $response = $provider->chat([
            ['role' => 'user', 'content' => 'Hello'],
        ]);

        $this->assertEquals('First part. Second part.', $response->getContent());
    }

    public function test_it_maps_stop_reasons_correctly(): void
    {
        $testCases = [
            ['stop_reason' => 'end_turn', 'expected' => 'stop'],
            ['stop_reason' => 'max_tokens', 'expected' => 'length'],
            ['stop_reason' => 'stop_sequence', 'expected' => 'stop'],
            ['stop_reason' => 'tool_use', 'expected' => 'tool_calls'],
        ];

        foreach ($testCases as $case) {
            $provider = $this->createProviderWithMock([
                new Response(200, [], json_encode([
                    'role' => 'assistant',
                    'model' => 'claude-sonnet-4-20250514',
                    'content' => [['type' => 'text', 'text' => 'Response']],
                    'stop_reason' => $case['stop_reason'],
                    'usage' => ['input_tokens' => 5, 'output_tokens' => 5],
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
                'role' => 'assistant',
                'model' => 'claude-sonnet-4-20250514',
                'content' => [['type' => 'text', 'text' => 'Creative response']],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
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
}
