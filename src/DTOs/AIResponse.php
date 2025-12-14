<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\DTOs;

use Ghdj\AIIntegration\Contracts\AIResponseInterface;

class AIResponse implements AIResponseInterface
{
    public function __construct(
        protected string $content,
        protected string $role,
        protected string $model,
        protected array $usage,
        protected ?string $finishReason,
        protected array $raw,
        protected array $toolCalls = []
    ) {
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getUsage(): array
    {
        return $this->usage;
    }

    public function getPromptTokens(): int
    {
        return $this->usage['prompt_tokens'] ?? 0;
    }

    public function getCompletionTokens(): int
    {
        return $this->usage['completion_tokens'] ?? 0;
    }

    public function getTotalTokens(): int
    {
        return $this->usage['total_tokens']
            ?? ($this->getPromptTokens() + $this->getCompletionTokens());
    }

    public function getFinishReason(): ?string
    {
        return $this->finishReason;
    }

    public function getRaw(): array
    {
        return $this->raw;
    }

    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'role' => $this->role,
            'model' => $this->model,
            'usage' => $this->usage,
            'finish_reason' => $this->finishReason,
            'tool_calls' => $this->toolCalls,
        ];
    }

    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }

    public function hasToolCalls(): bool
    {
        return !empty($this->toolCalls);
    }

    public function isToolCall(): bool
    {
        return $this->finishReason === 'tool_calls';
    }
}
