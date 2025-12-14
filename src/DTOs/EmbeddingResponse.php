<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\DTOs;

use Ghdj\AIIntegration\Contracts\EmbeddingResponseInterface;

class EmbeddingResponse implements EmbeddingResponseInterface
{
    public function __construct(
        protected array $embeddings,
        protected string $model,
        protected array $usage,
        protected array $raw
    ) {
    }

    public function getEmbeddings(): array
    {
        return $this->embeddings;
    }

    public function getFirstEmbedding(): array
    {
        return $this->embeddings[0] ?? [];
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getUsage(): array
    {
        return $this->usage;
    }

    public function getTotalTokens(): int
    {
        return $this->usage['total_tokens'] ?? 0;
    }

    public function getRaw(): array
    {
        return $this->raw;
    }

    public function toArray(): array
    {
        return [
            'embeddings' => $this->embeddings,
            'model' => $this->model,
            'usage' => $this->usage,
        ];
    }
}
