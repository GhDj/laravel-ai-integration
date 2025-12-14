<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Tests\Feature;

use Ghdj\AIIntegration\Contracts\AIResponseInterface;
use Ghdj\AIIntegration\Contracts\EmbeddingResponseInterface;
use Ghdj\AIIntegration\Services\CostTracker;
use Ghdj\AIIntegration\Tests\TestCase;

class CostTrackingIntegrationTest extends TestCase
{
    private function createMockResponse(string $model, int $prompt, int $completion): AIResponseInterface
    {
        $response = $this->createMock(AIResponseInterface::class);
        $response->method('getModel')->willReturn($model);
        $response->method('getPromptTokens')->willReturn($prompt);
        $response->method('getCompletionTokens')->willReturn($completion);
        $response->method('getTotalTokens')->willReturn($prompt + $completion);

        return $response;
    }

    private function createMockEmbeddingResponse(string $model, int $tokens): EmbeddingResponseInterface
    {
        $response = $this->createMock(EmbeddingResponseInterface::class);
        $response->method('getModel')->willReturn($model);
        $response->method('getTotalTokens')->willReturn($tokens);

        return $response;
    }

    public function test_tracks_multiple_requests(): void
    {
        $tracker = new CostTracker([
            'enabled' => true,
            'pricing' => [
                'openai' => [
                    'gpt-4o' => ['prompt' => 0.0025, 'completion' => 0.01],
                ],
            ],
        ]);

        $tracker->track('openai', $this->createMockResponse('gpt-4o', 100, 50));
        $tracker->track('openai', $this->createMockResponse('gpt-4o', 200, 100));
        $tracker->track('openai', $this->createMockResponse('gpt-4o', 300, 150));

        $usage = $tracker->getUsage('openai');

        $this->assertCount(3, $usage);
    }

    public function test_calculates_total_cost_across_requests(): void
    {
        $tracker = new CostTracker([
            'enabled' => true,
            'pricing' => [
                'openai' => [
                    'gpt-4o' => ['prompt' => 0.01, 'completion' => 0.02],
                ],
            ],
        ]);

        // Request 1: 1000 prompt + 500 completion = 0.01 + 0.01 = 0.02
        $tracker->track('openai', $this->createMockResponse('gpt-4o', 1000, 500));

        // Request 2: 2000 prompt + 1000 completion = 0.02 + 0.02 = 0.04
        $tracker->track('openai', $this->createMockResponse('gpt-4o', 2000, 1000));

        $totalCost = $tracker->getTotalCost('openai');

        $this->assertEquals(0.06, $totalCost);
    }

    public function test_tracks_multiple_providers_separately(): void
    {
        $tracker = new CostTracker([
            'enabled' => true,
            'pricing' => [
                'openai' => [
                    'gpt-4o' => ['prompt' => 0.01, 'completion' => 0.02],
                ],
                'claude' => [
                    'claude-3-sonnet' => ['prompt' => 0.003, 'completion' => 0.015],
                ],
            ],
        ]);

        $tracker->track('openai', $this->createMockResponse('gpt-4o', 1000, 500));
        $tracker->track('claude', $this->createMockResponse('claude-3-sonnet', 1000, 500));

        $openaiCost = $tracker->getTotalCost('openai');
        $claudeCost = $tracker->getTotalCost('claude');

        $this->assertNotEquals($openaiCost, $claudeCost);
        $this->assertGreaterThan(0, $openaiCost);
        $this->assertGreaterThan(0, $claudeCost);
    }

    public function test_tracks_embeddings(): void
    {
        $tracker = new CostTracker([
            'enabled' => true,
            'pricing' => [
                'openai' => [
                    'text-embedding-3-small' => ['prompt' => 0.00002, 'completion' => 0],
                ],
            ],
        ]);

        $tracker->trackEmbedding('openai', $this->createMockEmbeddingResponse('text-embedding-3-small', 1000));

        $usage = $tracker->getUsage('openai');
        $this->assertCount(1, $usage);

        // 1000 tokens * 0.00002 = 0.00002
        $this->assertEquals(0.00002, $usage[0]['cost']);
    }

    public function test_uses_default_pricing_for_unknown_model(): void
    {
        $tracker = new CostTracker([
            'enabled' => true,
            'pricing' => [
                'openai' => [
                    'default' => ['prompt' => 0.005, 'completion' => 0.015],
                ],
            ],
        ]);

        $tracker->track('openai', $this->createMockResponse('unknown-model', 1000, 500));

        $usage = $tracker->getUsage('openai');

        // 1000 * 0.005 + 500 * 0.015 = 5 + 7.5 = 12.5 (per 1000)
        // = 0.005 + 0.0075 = 0.0125
        $this->assertEquals(0.0125, $usage[0]['cost']);
    }

    public function test_reset_clears_all_usage(): void
    {
        $tracker = new CostTracker([
            'enabled' => true,
            'pricing' => [
                'openai' => [
                    'gpt-4o' => ['prompt' => 0.01, 'completion' => 0.02],
                ],
            ],
        ]);

        $tracker->track('openai', $this->createMockResponse('gpt-4o', 1000, 500));
        $tracker->track('openai', $this->createMockResponse('gpt-4o', 1000, 500));

        $this->assertCount(2, $tracker->getUsage('openai'));

        $tracker->reset();

        $this->assertEmpty($tracker->getUsage());
        $this->assertEquals(0, $tracker->getTotalCost());
    }

    public function test_disabled_tracker_does_not_track(): void
    {
        $tracker = new CostTracker([
            'enabled' => false,
            'pricing' => [
                'openai' => [
                    'gpt-4o' => ['prompt' => 0.01, 'completion' => 0.02],
                ],
            ],
        ]);

        $tracker->track('openai', $this->createMockResponse('gpt-4o', 1000, 500));

        $this->assertEmpty($tracker->getUsage());
        $this->assertEquals(0, $tracker->getTotalCost());
    }

    public function test_get_total_cost_all_providers(): void
    {
        $tracker = new CostTracker([
            'enabled' => true,
            'pricing' => [
                'openai' => [
                    'gpt-4o' => ['prompt' => 0.01, 'completion' => 0.02],
                ],
                'claude' => [
                    'claude-3' => ['prompt' => 0.01, 'completion' => 0.02],
                ],
            ],
        ]);

        $tracker->track('openai', $this->createMockResponse('gpt-4o', 1000, 500));
        $tracker->track('claude', $this->createMockResponse('claude-3', 1000, 500));

        $openaiCost = $tracker->getTotalCost('openai');
        $claudeCost = $tracker->getTotalCost('claude');
        $totalCost = $tracker->getTotalCost();

        $this->assertEquals($openaiCost + $claudeCost, $totalCost);
    }
}
