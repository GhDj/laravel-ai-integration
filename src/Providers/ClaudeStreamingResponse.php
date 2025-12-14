<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Providers;

use Generator;
use Psr\Http\Message\StreamInterface;
use Ghdj\AIIntegration\Contracts\AIResponseInterface;
use Ghdj\AIIntegration\Contracts\StreamingResponseInterface;
use Ghdj\AIIntegration\DTOs\AIResponse;

class ClaudeStreamingResponse implements StreamingResponseInterface
{
    private string $collectedContent = '';
    private ?string $stopReason = null;
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

                if (str_starts_with($line, 'event: ')) {
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

                    $content = $this->processEvent($data);

                    if ($content !== null && $content !== '') {
                        $this->collectedContent .= $content;
                        yield $content;
                    }
                }
            }
        }
    }

    protected function processEvent(array $data): ?string
    {
        $type = $data['type'] ?? '';

        return match ($type) {
            'content_block_delta' => $this->processContentBlockDelta($data),
            'content_block_start' => $this->processContentBlockStart($data),
            'message_delta' => $this->processMessageDelta($data),
            'message_start' => $this->processMessageStart($data),
            default => null,
        };
    }

    protected function processContentBlockDelta(array $data): ?string
    {
        $delta = $data['delta'] ?? [];

        if (($delta['type'] ?? '') === 'text_delta') {
            return $delta['text'] ?? '';
        }

        if (($delta['type'] ?? '') === 'input_json_delta') {
            if (!empty($this->toolCalls)) {
                $lastIndex = count($this->toolCalls) - 1;
                $this->toolCalls[$lastIndex]['function']['arguments'] .= $delta['partial_json'] ?? '';
            }
        }

        return null;
    }

    protected function processContentBlockStart(array $data): ?string
    {
        $contentBlock = $data['content_block'] ?? [];

        if (($contentBlock['type'] ?? '') === 'tool_use') {
            $this->toolCalls[] = [
                'id' => $contentBlock['id'] ?? '',
                'type' => 'function',
                'function' => [
                    'name' => $contentBlock['name'] ?? '',
                    'arguments' => '',
                ],
            ];
        }

        return null;
    }

    protected function processMessageDelta(array $data): ?string
    {
        $delta = $data['delta'] ?? [];

        if (isset($delta['stop_reason'])) {
            $this->stopReason = $delta['stop_reason'];
        }

        if (isset($data['usage'])) {
            $this->usage = array_merge($this->usage, [
                'completion_tokens' => $data['usage']['output_tokens'] ?? 0,
            ]);
        }

        return null;
    }

    protected function processMessageStart(array $data): ?string
    {
        $message = $data['message'] ?? [];

        if (isset($message['usage'])) {
            $this->usage = [
                'prompt_tokens' => $message['usage']['input_tokens'] ?? 0,
                'completion_tokens' => 0,
            ];
        }

        if (isset($message['model'])) {
            $this->model = $message['model'];
        }

        return null;
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

        $finishReason = match ($this->stopReason) {
            'end_turn' => 'stop',
            'max_tokens' => 'length',
            'stop_sequence' => 'stop',
            'tool_use' => 'tool_calls',
            default => $this->stopReason,
        };

        return new AIResponse(
            content: $this->collectedContent,
            role: 'assistant',
            model: $this->model,
            usage: [
                'prompt_tokens' => $this->usage['prompt_tokens'] ?? 0,
                'completion_tokens' => $this->usage['completion_tokens'] ?? 0,
                'total_tokens' => ($this->usage['prompt_tokens'] ?? 0) + ($this->usage['completion_tokens'] ?? 0),
            ],
            finishReason: $finishReason,
            raw: [],
            toolCalls: $this->toolCalls
        );
    }
}
