<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Contracts;

interface AIResponseInterface
{
    public function getContent(): string;

    public function getRole(): string;

    public function getModel(): string;

    public function getUsage(): array;

    public function getPromptTokens(): int;

    public function getCompletionTokens(): int;

    public function getTotalTokens(): int;

    public function getFinishReason(): ?string;

    public function getRaw(): array;

    public function toArray(): array;
}
