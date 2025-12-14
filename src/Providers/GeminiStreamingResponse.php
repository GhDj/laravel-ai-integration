<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Providers;

use Generator;
use Psr\Http\Message\StreamInterface;
use Ghdj\AIIntegration\Contracts\AIResponseInterface;
use Ghdj\AIIntegration\Contracts\StreamingResponseInterface;
use Ghdj\AIIntegration\DTOs\AIResponse;

class GeminiStreamingResponse implements StreamingResponseInterface
{
    private string $collectedContent = '';
    private ?string $finishReason = null;
    private array $usage = [];
    private array $toolCalls = [];

    public function __construct(
        private StreamInterface $stream,
        private string $model
    ) {
    }

    public function getIterator(): Generator
    {
        $buffer = '';

        while (!$this->stream->eof()) {
            $chunk = $this->stream->read(1024);
            $buffer .= $chunk;

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);

                $line = trim($line);

                if ($line === '' || str_starts_with($line, ':')) {
                    continue;
                }

                if (str_starts_with($line, 'data: ')) {
                    $json = substr($line, 6);

                    if ($json === '[DONE]') {
                        continue;
                    }

                    $data = json_decode($json, true);

                    if ($data === null) {
                        continue;
                    }

                    $content = $this->processChunk($data);

                    if ($content !== null && $content !== '') {
                        $this->collectedContent .= $content;
                        yield $content;
                    }
                }
            }
        }
    }

    protected function processChunk(array $data): ?string
    {
        $candidate = $data['candidates'][0] ?? [];
        $content = $candidate['content'] ?? [];
        $parts = $content['parts'] ?? [];

        $text = '';

        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $text .= $part['text'];
            } elseif (isset($part['functionCall'])) {
                $this->toolCalls[] = [
                    'id' => 'call_' . uniqid(),
                    'type' => 'function',
                    'function' => [
                        'name' => $part['functionCall']['name'] ?? '',
                        'arguments' => json_encode($part['functionCall']['args'] ?? []),
                    ],
                ];
            }
        }

        if (isset($candidate['finishReason'])) {
            $this->finishReason = $candidate['finishReason'];
        }

        if (isset($data['usageMetadata'])) {
            $this->usage = [
                'prompt_tokens' => $data['usageMetadata']['promptTokenCount'] ?? 0,
                'completion_tokens' => $data['usageMetadata']['candidatesTokenCount'] ?? 0,
                'total_tokens' => $data['usageMetadata']['totalTokenCount'] ?? 0,
            ];
        }

        return $text !== '' ? $text : null;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function collect(): AIResponseInterface
    {
        if ($this->collectedContent === '' && empty($this->toolCalls)) {
            foreach ($this->getIterator() as $chunk) {
                // Consume the iterator to collect content
            }
        }

        $finishReason = match ($this->finishReason) {
            'STOP' => 'stop',
            'MAX_TOKENS' => 'length',
            'SAFETY' => 'content_filter',
            'RECITATION' => 'content_filter',
            'TOOL_CALL', 'FUNCTION_CALL' => 'tool_calls',
            default => $this->finishReason,
        };

        return new AIResponse(
            content: $this->collectedContent,
            role: 'assistant',
            model: $this->model,
            usage: $this->usage ?: ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
            finishReason: $finishReason,
            raw: [],
            toolCalls: $this->toolCalls
        );
    }
}
