<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\DTOs;

use Generator;
use Psr\Http\Message\StreamInterface;
use Ghdj\AIIntegration\Contracts\AIResponseInterface;
use Ghdj\AIIntegration\Contracts\StreamingResponseInterface;

class StreamingResponse implements StreamingResponseInterface
{
    private string $collectedContent = '';
    private ?string $finishReason = null;
    private array $usage = [];

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
                if ($line === '' || $line === 'data: [DONE]') {
                    continue;
                }

                if (str_starts_with($line, 'data: ')) {
                    $json = substr($line, 6);
                    $data = json_decode($json, true);

                    if ($data === null) {
                        continue;
                    }

                    $delta = $data['choices'][0]['delta'] ?? [];
                    $content = $delta['content'] ?? '';

                    if ($content !== '') {
                        $this->collectedContent .= $content;
                        yield $content;
                    }

                    if (isset($data['choices'][0]['finish_reason'])) {
                        $this->finishReason = $data['choices'][0]['finish_reason'];
                    }

                    if (isset($data['usage'])) {
                        $this->usage = $data['usage'];
                    }
                }
            }
        }
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function collect(): AIResponseInterface
    {
        if ($this->collectedContent === '') {
            foreach ($this->getIterator() as $chunk) {
                // Consume the iterator to collect content
            }
        }

        return new AIResponse(
            content: $this->collectedContent,
            role: 'assistant',
            model: $this->model,
            usage: $this->usage,
            finishReason: $this->finishReason,
            raw: []
        );
    }
}
