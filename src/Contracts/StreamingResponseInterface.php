<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Contracts;

use Generator;

interface StreamingResponseInterface
{
    public function getIterator(): Generator;

    public function getModel(): string;

    public function collect(): AIResponseInterface;
}
