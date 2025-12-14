<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Contracts;

interface EmbeddingResponseInterface
{
    public function getEmbeddings(): array;

    public function getFirstEmbedding(): array;

    public function getModel(): string;

    public function getUsage(): array;

    public function getTotalTokens(): int;

    public function getRaw(): array;

    public function toArray(): array;
}
