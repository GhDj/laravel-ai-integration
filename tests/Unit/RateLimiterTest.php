<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Ghdj\AIIntegration\Exceptions\RateLimitExceededException;
use Ghdj\AIIntegration\Services\RateLimiter;
use Ghdj\AIIntegration\Tests\TestCase;

class RateLimiterTest extends TestCase
{
    private RateLimiter $rateLimiter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rateLimiter = new RateLimiter(
            Cache::store(),
            [
                'enabled' => true,
                'default_limit' => 5,
                'default_window' => 60,
                'cache_prefix' => 'test_rate_limit',
            ]
        );
    }

    public function test_it_allows_requests_under_limit(): void
    {
        $this->rateLimiter->reset('openai');

        for ($i = 0; $i < 5; $i++) {
            $this->rateLimiter->check('openai');
        }

        $this->assertEquals(0, $this->rateLimiter->remaining('openai'));
    }

    public function test_it_throws_exception_when_limit_exceeded(): void
    {
        $this->rateLimiter->reset('openai');

        for ($i = 0; $i < 5; $i++) {
            $this->rateLimiter->check('openai');
        }

        $this->expectException(RateLimitExceededException::class);

        $this->rateLimiter->check('openai');
    }

    public function test_it_returns_remaining_requests(): void
    {
        $this->rateLimiter->reset('openai');

        $this->assertEquals(5, $this->rateLimiter->remaining('openai'));

        $this->rateLimiter->check('openai');

        $this->assertEquals(4, $this->rateLimiter->remaining('openai'));
    }

    public function test_it_can_reset_limits(): void
    {
        $this->rateLimiter->reset('openai');

        for ($i = 0; $i < 3; $i++) {
            $this->rateLimiter->check('openai');
        }

        $this->assertEquals(2, $this->rateLimiter->remaining('openai'));

        $this->rateLimiter->reset('openai');

        $this->assertEquals(5, $this->rateLimiter->remaining('openai'));
    }

    public function test_it_skips_when_disabled(): void
    {
        $rateLimiter = new RateLimiter(
            Cache::store(),
            ['enabled' => false, 'default_limit' => 1]
        );

        $rateLimiter->check('openai');
        $rateLimiter->check('openai');
        $rateLimiter->check('openai');

        $this->assertTrue(true);
    }
}
