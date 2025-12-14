<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Contracts;

interface AIProviderInterface
{
    public function chat(array $messages, array $options = []): AIResponseInterface;

    public function complete(string $prompt, array $options = []): AIResponseInterface;

    public function embed(string|array $input, array $options = []): EmbeddingResponseInterface;

    public function getName(): string;

    public function getModels(): array;

    public function supportsStreaming(): bool;
}
