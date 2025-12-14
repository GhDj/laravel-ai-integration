<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Contracts;

interface AIManagerInterface
{
    public function provider(?string $name = null): AIProviderInterface;

    public function chat(array $messages, array $options = []): AIResponseInterface;

    public function complete(string $prompt, array $options = []): AIResponseInterface;

    public function embed(string|array $input, array $options = []): EmbeddingResponseInterface;

    public function getDefaultProvider(): string;

    public function extend(string $name, callable $callback): static;
}
