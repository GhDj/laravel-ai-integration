<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Exceptions;

class ClaudeException extends APIException
{
    public const ERROR_INVALID_API_KEY = 'authentication_error';
    public const ERROR_RATE_LIMIT = 'rate_limit_error';
    public const ERROR_OVERLOADED = 'overloaded_error';
    public const ERROR_INVALID_REQUEST = 'invalid_request_error';
    public const ERROR_API = 'api_error';

    protected ?string $errorType = null;

    public static function fromResponse(array $response, int $statusCode): self
    {
        $error = $response['error'] ?? [];
        $message = $error['message'] ?? 'Unknown Claude API error';
        $type = $error['type'] ?? null;

        $exception = new self($message, $statusCode, $response);
        $exception->errorType = $type;

        return $exception;
    }

    public function getErrorType(): ?string
    {
        return $this->errorType;
    }

    public function isRateLimitError(): bool
    {
        return $this->errorType === self::ERROR_RATE_LIMIT;
    }

    public function isOverloadedError(): bool
    {
        return $this->errorType === self::ERROR_OVERLOADED;
    }

    public function isAuthenticationError(): bool
    {
        return $this->errorType === self::ERROR_INVALID_API_KEY;
    }
}
