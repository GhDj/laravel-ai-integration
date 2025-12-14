<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Contracts;

interface PromptTemplateInterface
{
    public function render(array $variables = []): string;

    public function getTemplate(): string;

    public function getVariables(): array;

    public function getRequiredVariables(): array;

    public function with(array $variables): static;

    public function validate(array $variables = []): bool;

    public function toMessages(array $variables = []): array;
}
