<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Exceptions;

class APIException extends AIException
{
    public function __construct(
        string $message,
        int $code = 0,
        protected ?array $response = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getResponse(): ?array
    {
        return $this->response;
    }
}
