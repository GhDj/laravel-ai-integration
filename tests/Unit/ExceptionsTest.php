<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Tests\Unit;

use Ghdj\AIIntegration\Exceptions\AIException;
use Ghdj\AIIntegration\Exceptions\APIException;
use Ghdj\AIIntegration\Exceptions\ClaudeException;
use Ghdj\AIIntegration\Exceptions\GeminiException;
use Ghdj\AIIntegration\Exceptions\OpenAIException;
use Ghdj\AIIntegration\Exceptions\PromptException;
use Ghdj\AIIntegration\Exceptions\ProviderNotFoundException;
use Ghdj\AIIntegration\Exceptions\RateLimitExceededException;
use Ghdj\AIIntegration\Tests\TestCase;

class ExceptionsTest extends TestCase
{
    public function test_ai_exception_is_throwable(): void
    {
        $this->expectException(AIException::class);
        $this->expectExceptionMessage('Test error');

        throw new AIException('Test error');
    }

    public function test_provider_not_found_exception_extends_ai_exception(): void
    {
        $exception = new ProviderNotFoundException('Provider not found');

        $this->assertInstanceOf(AIException::class, $exception);
        $this->assertEquals('Provider not found', $exception->getMessage());
    }

    public function test_rate_limit_exceeded_exception_extends_ai_exception(): void
    {
        $exception = new RateLimitExceededException('Rate limit exceeded');

        $this->assertInstanceOf(AIException::class, $exception);
        $this->assertEquals('Rate limit exceeded', $exception->getMessage());
    }

    public function test_prompt_exception_extends_ai_exception(): void
    {
        $exception = new PromptException('Missing variable');

        $this->assertInstanceOf(AIException::class, $exception);
        $this->assertEquals('Missing variable', $exception->getMessage());
    }

    public function test_api_exception_stores_response(): void
    {
        $response = ['error' => ['message' => 'API error']];

        $exception = new APIException('API error', 400, $response);

        $this->assertEquals($response, $exception->getResponse());
        $this->assertEquals(400, $exception->getCode());
    }

    public function test_api_exception_handles_null_response(): void
    {
        $exception = new APIException('API error', 500, null);

        $this->assertNull($exception->getResponse());
    }

    // OpenAI Exception Tests
    public function test_openai_exception_from_response(): void
    {
        $response = [
            'error' => [
                'message' => 'Invalid API key',
                'type' => 'invalid_request_error',
                'code' => 'invalid_api_key',
            ],
        ];

        $exception = OpenAIException::fromResponse($response, 401);

        $this->assertEquals('Invalid API key', $exception->getMessage());
        $this->assertEquals(401, $exception->getCode());
        $this->assertEquals('invalid_request_error', $exception->getErrorType());
        $this->assertEquals('invalid_api_key', $exception->getErrorCode());
    }

    public function test_openai_exception_identifies_rate_limit(): void
    {
        $response = [
            'error' => [
                'message' => 'Rate limit exceeded',
                'type' => 'rate_limit_exceeded',
                'code' => 'rate_limit_exceeded',
            ],
        ];

        $exception = OpenAIException::fromResponse($response, 429);

        $this->assertTrue($exception->isRateLimitError());
        $this->assertFalse($exception->isAuthenticationError());
        $this->assertFalse($exception->isQuotaError());
    }

    public function test_openai_exception_identifies_quota_error(): void
    {
        $response = [
            'error' => [
                'message' => 'Insufficient quota',
                'type' => 'insufficient_quota',
                'code' => 'insufficient_quota',
            ],
        ];

        $exception = OpenAIException::fromResponse($response, 429);

        $this->assertTrue($exception->isQuotaError());
    }

    public function test_openai_exception_identifies_auth_error(): void
    {
        $response = [
            'error' => [
                'message' => 'Invalid API key',
                'type' => 'invalid_request_error',
                'code' => 'invalid_api_key',
            ],
        ];

        $exception = OpenAIException::fromResponse($response, 401);

        $this->assertTrue($exception->isAuthenticationError());
    }

    public function test_openai_exception_handles_missing_fields(): void
    {
        $exception = OpenAIException::fromResponse([], 500);

        $this->assertEquals('Unknown OpenAI API error', $exception->getMessage());
        $this->assertNull($exception->getErrorType());
        $this->assertNull($exception->getErrorCode());
    }

    // Claude Exception Tests
    public function test_claude_exception_from_response(): void
    {
        $response = [
            'error' => [
                'message' => 'Invalid API key',
                'type' => 'authentication_error',
            ],
        ];

        $exception = ClaudeException::fromResponse($response, 401);

        $this->assertEquals('Invalid API key', $exception->getMessage());
        $this->assertEquals('authentication_error', $exception->getErrorType());
    }

    public function test_claude_exception_identifies_rate_limit(): void
    {
        $response = [
            'error' => [
                'message' => 'Rate limit exceeded',
                'type' => 'rate_limit_error',
            ],
        ];

        $exception = ClaudeException::fromResponse($response, 429);

        $this->assertTrue($exception->isRateLimitError());
    }

    public function test_claude_exception_identifies_overloaded(): void
    {
        $response = [
            'error' => [
                'message' => 'API is overloaded',
                'type' => 'overloaded_error',
            ],
        ];

        $exception = ClaudeException::fromResponse($response, 529);

        $this->assertTrue($exception->isOverloadedError());
    }

    public function test_claude_exception_identifies_auth_error(): void
    {
        $response = [
            'error' => [
                'message' => 'Invalid API key',
                'type' => 'authentication_error',
            ],
        ];

        $exception = ClaudeException::fromResponse($response, 401);

        $this->assertTrue($exception->isAuthenticationError());
    }

    // Gemini Exception Tests
    public function test_gemini_exception_from_response(): void
    {
        $response = [
            'error' => [
                'message' => 'Invalid API key',
                'status' => 'INVALID_ARGUMENT',
                'code' => 400,
            ],
        ];

        $exception = GeminiException::fromResponse($response, 400);

        $this->assertEquals('Invalid API key', $exception->getMessage());
        $this->assertEquals('INVALID_ARGUMENT', $exception->getErrorStatus());
        $this->assertEquals('400', $exception->getErrorCode());
    }

    public function test_gemini_exception_identifies_rate_limit(): void
    {
        $response = [
            'error' => [
                'message' => 'Resource exhausted',
                'status' => 'RESOURCE_EXHAUSTED',
            ],
        ];

        $exception = GeminiException::fromResponse($response, 429);

        $this->assertTrue($exception->isRateLimitError());
    }

    public function test_gemini_exception_identifies_auth_error(): void
    {
        $response = [
            'error' => [
                'message' => 'Permission denied',
                'status' => 'PERMISSION_DENIED',
            ],
        ];

        $exception = GeminiException::fromResponse($response, 403);

        $this->assertTrue($exception->isAuthenticationError());
    }

    public function test_gemini_exception_identifies_not_found(): void
    {
        $response = [
            'error' => [
                'message' => 'Model not found',
                'status' => 'NOT_FOUND',
            ],
        ];

        $exception = GeminiException::fromResponse($response, 404);

        $this->assertTrue($exception->isNotFoundError());
    }

    public function test_gemini_exception_handles_missing_fields(): void
    {
        $exception = GeminiException::fromResponse([], 500);

        $this->assertEquals('Unknown Gemini API error', $exception->getMessage());
        $this->assertNull($exception->getErrorStatus());
        $this->assertNull($exception->getErrorCode());
    }
}
