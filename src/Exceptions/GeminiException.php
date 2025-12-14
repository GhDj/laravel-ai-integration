<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Exceptions;

class GeminiException extends APIException
{
    public const ERROR_INVALID_API_KEY = 'INVALID_ARGUMENT';
    public const ERROR_RATE_LIMIT = 'RESOURCE_EXHAUSTED';
    public const ERROR_NOT_FOUND = 'NOT_FOUND';
    public const ERROR_PERMISSION_DENIED = 'PERMISSION_DENIED';
    public const ERROR_INTERNAL = 'INTERNAL';

    protected ?string $errorStatus = null;
    protected ?string $errorCode = null;

    public static function fromResponse(array $response, int $statusCode): self
    {
        $error = $response['error'] ?? [];
        $message = $error['message'] ?? 'Unknown Gemini API error';
        $status = $error['status'] ?? null;
        $code = $error['code'] ?? null;

        $exception = new self($message, $statusCode, $response);
        $exception->errorStatus = $status;
        $exception->errorCode = is_int($code) ? (string) $code : $code;

        return $exception;
    }

    public function getErrorStatus(): ?string
    {
        return $this->errorStatus;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function isRateLimitError(): bool
    {
        return $this->errorStatus === self::ERROR_RATE_LIMIT;
    }

    public function isAuthenticationError(): bool
    {
        return $this->errorStatus === self::ERROR_INVALID_API_KEY
            || $this->errorStatus === self::ERROR_PERMISSION_DENIED;
    }

    public function isNotFoundError(): bool
    {
        return $this->errorStatus === self::ERROR_NOT_FOUND;
    }
}
