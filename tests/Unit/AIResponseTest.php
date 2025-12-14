<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Tests\Unit;

use Ghdj\AIIntegration\DTOs\AIResponse;
use Ghdj\AIIntegration\Tests\TestCase;

class AIResponseTest extends TestCase
{
    public function test_it_creates_response_with_all_parameters(): void
    {
        $response = new AIResponse(
            content: 'Hello, World!',
            role: 'assistant',
            model: 'gpt-4o',
            usage: [
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
                'total_tokens' => 30,
            ],
            finishReason: 'stop',
            raw: ['id' => 'test-123'],
            toolCalls: []
        );

        $this->assertEquals('Hello, World!', $response->getContent());
        $this->assertEquals('assistant', $response->getRole());
        $this->assertEquals('gpt-4o', $response->getModel());
        $this->assertEquals('stop', $response->getFinishReason());
    }

    public function test_it_returns_usage_data(): void
    {
        $usage = [
            'prompt_tokens' => 100,
            'completion_tokens' => 200,
            'total_tokens' => 300,
        ];

        $response = new AIResponse(
            content: 'Test',
            role: 'assistant',
            model: 'gpt-4o',
            usage: $usage,
            finishReason: 'stop',
            raw: []
        );

        $this->assertEquals($usage, $response->getUsage());
        $this->assertEquals(100, $response->getPromptTokens());
        $this->assertEquals(200, $response->getCompletionTokens());
        $this->assertEquals(300, $response->getTotalTokens());
    }

    public function test_it_calculates_total_tokens_when_not_provided(): void
    {
        $response = new AIResponse(
            content: 'Test',
            role: 'assistant',
            model: 'gpt-4o',
            usage: [
                'prompt_tokens' => 50,
                'completion_tokens' => 75,
            ],
            finishReason: 'stop',
            raw: []
        );

        $this->assertEquals(125, $response->getTotalTokens());
    }

    public function test_it_returns_zero_for_missing_token_counts(): void
    {
        $response = new AIResponse(
            content: 'Test',
            role: 'assistant',
            model: 'gpt-4o',
            usage: [],
            finishReason: 'stop',
            raw: []
        );

        $this->assertEquals(0, $response->getPromptTokens());
        $this->assertEquals(0, $response->getCompletionTokens());
        $this->assertEquals(0, $response->getTotalTokens());
    }

    public function test_it_returns_raw_response(): void
    {
        $raw = [
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => 1234567890,
        ];

        $response = new AIResponse(
            content: 'Test',
            role: 'assistant',
            model: 'gpt-4o',
            usage: [],
            finishReason: 'stop',
            raw: $raw
        );

        $this->assertEquals($raw, $response->getRaw());
    }

    public function test_it_converts_to_array(): void
    {
        $response = new AIResponse(
            content: 'Hello',
            role: 'assistant',
            model: 'gpt-4o',
            usage: ['prompt_tokens' => 10, 'completion_tokens' => 20],
            finishReason: 'stop',
            raw: [],
            toolCalls: [['id' => 'call_1']]
        );

        $array = $response->toArray();

        $this->assertEquals('Hello', $array['content']);
        $this->assertEquals('assistant', $array['role']);
        $this->assertEquals('gpt-4o', $array['model']);
        $this->assertEquals('stop', $array['finish_reason']);
        $this->assertArrayHasKey('usage', $array);
        $this->assertArrayHasKey('tool_calls', $array);
    }

    public function test_it_handles_tool_calls(): void
    {
        $toolCalls = [
            [
                'id' => 'call_123',
                'type' => 'function',
                'function' => [
                    'name' => 'get_weather',
                    'arguments' => '{"location":"London"}',
                ],
            ],
        ];

        $response = new AIResponse(
            content: '',
            role: 'assistant',
            model: 'gpt-4o',
            usage: [],
            finishReason: 'tool_calls',
            raw: [],
            toolCalls: $toolCalls
        );

        $this->assertTrue($response->hasToolCalls());
        $this->assertEquals($toolCalls, $response->getToolCalls());
        $this->assertTrue($response->isToolCall());
    }

    public function test_it_returns_false_for_no_tool_calls(): void
    {
        $response = new AIResponse(
            content: 'Hello',
            role: 'assistant',
            model: 'gpt-4o',
            usage: [],
            finishReason: 'stop',
            raw: [],
            toolCalls: []
        );

        $this->assertFalse($response->hasToolCalls());
        $this->assertEmpty($response->getToolCalls());
        $this->assertFalse($response->isToolCall());
    }

    public function test_it_handles_null_finish_reason(): void
    {
        $response = new AIResponse(
            content: 'Test',
            role: 'assistant',
            model: 'gpt-4o',
            usage: [],
            finishReason: null,
            raw: []
        );

        $this->assertNull($response->getFinishReason());
    }
}
