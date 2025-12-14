<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Services;

use Ghdj\AIIntegration\Contracts\AIResponseInterface;
use Ghdj\AIIntegration\Contracts\EmbeddingResponseInterface;

class CostTracker
{
    protected array $usage = [];

    public function __construct(
        protected array $config
    ) {
    }

    public function track(string $provider, AIResponseInterface $response): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $model = $response->getModel();
        $promptTokens = $response->getPromptTokens();
        $completionTokens = $response->getCompletionTokens();

        $cost = $this->calculateCost($provider, $model, $promptTokens, $completionTokens);

        $this->recordUsage($provider, $model, [
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $response->getTotalTokens(),
            'cost' => $cost,
        ]);
    }

    public function trackEmbedding(string $provider, EmbeddingResponseInterface $response): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $model = $response->getModel();
        $tokens = $response->getTotalTokens();

        $cost = $this->calculateEmbeddingCost($provider, $model, $tokens);

        $this->recordUsage($provider, $model, [
            'total_tokens' => $tokens,
            'cost' => $cost,
        ]);
    }

    public function getUsage(?string $provider = null): array
    {
        if ($provider === null) {
            return $this->usage;
        }

        return $this->usage[$provider] ?? [];
    }

    public function getTotalCost(?string $provider = null): float
    {
        $usage = $this->getUsage($provider);

        if ($provider !== null) {
            return array_sum(array_column($usage, 'cost'));
        }

        $total = 0.0;
        foreach ($usage as $providerUsage) {
            $total += array_sum(array_column($providerUsage, 'cost'));
        }

        return $total;
    }

    public function reset(): void
    {
        $this->usage = [];
    }

    protected function calculateCost(string $provider, string $model, int $promptTokens, int $completionTokens): float
    {
        $pricing = $this->getPricing($provider, $model);

        $promptCost = ($promptTokens / 1000) * ($pricing['prompt'] ?? 0);
        $completionCost = ($completionTokens / 1000) * ($pricing['completion'] ?? 0);

        return round($promptCost + $completionCost, 6);
    }

    protected function calculateEmbeddingCost(string $provider, string $model, int $tokens): float
    {
        $pricing = $this->getPricing($provider, $model);

        return round(($tokens / 1000) * ($pricing['embedding'] ?? $pricing['prompt'] ?? 0), 6);
    }

    protected function getPricing(string $provider, string $model): array
    {
        return $this->config['pricing'][$provider][$model]
            ?? $this->config['pricing'][$provider]['default']
            ?? ['prompt' => 0, 'completion' => 0];
    }

    protected function recordUsage(string $provider, string $model, array $data): void
    {
        if (!isset($this->usage[$provider])) {
            $this->usage[$provider] = [];
        }

        $this->usage[$provider][] = array_merge([
            'model' => $model,
            'timestamp' => time(),
        ], $data);
    }

    protected function isEnabled(): bool
    {
        return $this->config['enabled'] ?? false;
    }
}
