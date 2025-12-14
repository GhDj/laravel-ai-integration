<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Tests\Feature;

use Ghdj\AIIntegration\Exceptions\RateLimitExceededException;
use Ghdj\AIIntegration\Services\RateLimiter;
use Ghdj\AIIntegration\Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class RateLimitingIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before each test
        Cache::flush();
    }

    public function test_rate_limiter_uses_cache(): void
    {
        $rateLimiter = new RateLimiter(Cache::store(), [
            'enabled' => true,
            'default_limit' => 3,
            'default_window' => 60,
            'cache_prefix' => 'test_rl',
        ]);

        $rateLimiter->check('test-provider');
        $rateLimiter->check('test-provider');

        $this->assertEquals(1, $rateLimiter->remaining('test-provider'));
    }

    public function test_rate_limiter_respects_provider_specific_limits(): void
    {
        $rateLimiter = new RateLimiter(Cache::store(), [
            'enabled' => true,
            'default_limit' => 100,
            'default_window' => 60,
            'cache_prefix' => 'test_rl',
            'providers' => [
                'openai' => ['limit' => 5, 'window' => 60],
                'claude' => ['limit' => 3, 'window' => 60],
            ],
        ]);

        $this->assertEquals(5, $rateLimiter->remaining('openai'));
        $this->assertEquals(3, $rateLimiter->remaining('claude'));
        $this->assertEquals(100, $rateLimiter->remaining('unknown'));
    }

    public function test_rate_limiter_throws_when_exceeded(): void
    {
        $rateLimiter = new RateLimiter(Cache::store(), [
            'enabled' => true,
            'default_limit' => 2,
            'default_window' => 60,
            'cache_prefix' => 'test_rl',
        ]);

        $rateLimiter->check('test');
        $rateLimiter->check('test');

        $this->expectException(RateLimitExceededException::class);

        $rateLimiter->check('test');
    }

    public function test_rate_limiter_reset_clears_count(): void
    {
        $rateLimiter = new RateLimiter(Cache::store(), [
            'enabled' => true,
            'default_limit' => 5,
            'default_window' => 60,
            'cache_prefix' => 'test_rl',
        ]);

        $rateLimiter->check('test');
        $rateLimiter->check('test');
        $rateLimiter->check('test');

        $this->assertEquals(2, $rateLimiter->remaining('test'));

        $rateLimiter->reset('test');

        $this->assertEquals(5, $rateLimiter->remaining('test'));
    }

    public function test_rate_limiter_isolates_providers(): void
    {
        $rateLimiter = new RateLimiter(Cache::store(), [
            'enabled' => true,
            'default_limit' => 5,
            'default_window' => 60,
            'cache_prefix' => 'test_rl',
        ]);

        $rateLimiter->check('openai');
        $rateLimiter->check('openai');
        $rateLimiter->check('claude');

        $this->assertEquals(3, $rateLimiter->remaining('openai'));
        $this->assertEquals(4, $rateLimiter->remaining('claude'));
    }

    public function test_disabled_rate_limiter_allows_all(): void
    {
        $rateLimiter = new RateLimiter(Cache::store(), [
            'enabled' => false,
            'default_limit' => 1,
        ]);

        // Should not throw even though limit is 1
        for ($i = 0; $i < 100; $i++) {
            $rateLimiter->check('test');
        }

        $this->assertTrue(true); // If we get here, test passed
    }
}
