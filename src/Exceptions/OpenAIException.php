<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Exceptions;

class OpenAIException extends APIException
{
    public const ERROR_INVALID_API_KEY = 'invalid_api_key';
    public const ERROR_RATE_LIMIT = 'rate_limit_exceeded';
    public const ERROR_INSUFFICIENT_QUOTA = 'insufficient_quota';
    public const ERROR_INVALID_REQUEST = 'invalid_request_error';
    public const ERROR_SERVER = 'server_error';

    protected ?string $errorType = null;
    protected ?string $errorCode = null;

    public static function fromResponse(array $response, int $statusCode): self
    {
        $error = $response['error'] ?? [];
        $message = $error['message'] ?? 'Unknown OpenAI API error';
        $type = $error['type'] ?? null;
        $code = $error['code'] ?? null;

        $exception = new self($message, $statusCode, $response);
        $exception->errorType = $type;
        $exception->errorCode = $code;

        return $exception;
    }

    public function getErrorType(): ?string
    {
        return $this->errorType;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function isRateLimitError(): bool
    {
        return $this->errorType === self::ERROR_RATE_LIMIT
            || $this->errorCode === self::ERROR_RATE_LIMIT;
    }

    public function isQuotaError(): bool
    {
        return $this->errorCode === self::ERROR_INSUFFICIENT_QUOTA;
    }

    public function isAuthenticationError(): bool
    {
        return $this->errorCode === self::ERROR_INVALID_API_KEY;
    }
}
