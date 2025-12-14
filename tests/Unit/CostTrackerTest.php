<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Tests\Unit;

use Ghdj\AIIntegration\Contracts\AIResponseInterface;
use Ghdj\AIIntegration\Services\CostTracker;
use Ghdj\AIIntegration\Tests\TestCase;

class CostTrackerTest extends TestCase
{
    private CostTracker $costTracker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->costTracker = new CostTracker([
            'enabled' => true,
            'pricing' => [
                'openai' => [
                    'gpt-4o' => ['prompt' => 0.0025, 'completion' => 0.01],
                ],
            ],
        ]);
    }

    public function test_it_tracks_usage(): void
    {
        $response = $this->createMockResponse('gpt-4o', 100, 50);

        $this->costTracker->track('openai', $response);

        $usage = $this->costTracker->getUsage('openai');

        $this->assertCount(1, $usage);
        $this->assertEquals('gpt-4o', $usage[0]['model']);
        $this->assertEquals(100, $usage[0]['prompt_tokens']);
        $this->assertEquals(50, $usage[0]['completion_tokens']);
    }

    public function test_it_calculates_cost(): void
    {
        $response = $this->createMockResponse('gpt-4o', 1000, 500);

        $this->costTracker->track('openai', $response);

        $usage = $this->costTracker->getUsage('openai');

        // (1000 / 1000) * 0.0025 + (500 / 1000) * 0.01 = 0.0025 + 0.005 = 0.0075
        $this->assertEquals(0.0075, $usage[0]['cost']);
    }

    public function test_it_returns_total_cost(): void
    {
        $this->costTracker->track('openai', $this->createMockResponse('gpt-4o', 1000, 500));
        $this->costTracker->track('openai', $this->createMockResponse('gpt-4o', 2000, 1000));

        // First: 0.0075, Second: 0.005 + 0.01 = 0.015
        $this->assertEquals(0.0225, $this->costTracker->getTotalCost('openai'));
    }

    public function test_it_can_reset(): void
    {
        $this->costTracker->track('openai', $this->createMockResponse('gpt-4o', 1000, 500));

        $this->costTracker->reset();

        $this->assertEmpty($this->costTracker->getUsage());
    }

    public function test_it_skips_when_disabled(): void
    {
        $tracker = new CostTracker(['enabled' => false]);

        $tracker->track('openai', $this->createMockResponse('gpt-4o', 1000, 500));

        $this->assertEmpty($tracker->getUsage());
    }

    private function createMockResponse(string $model, int $promptTokens, int $completionTokens): AIResponseInterface
    {
        $response = $this->createMock(AIResponseInterface::class);
        $response->method('getModel')->willReturn($model);
        $response->method('getPromptTokens')->willReturn($promptTokens);
        $response->method('getCompletionTokens')->willReturn($completionTokens);
        $response->method('getTotalTokens')->willReturn($promptTokens + $completionTokens);

        return $response;
    }
}
