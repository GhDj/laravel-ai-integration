<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Ghdj\AIIntegration\Exceptions\RateLimitExceededException;

class RateLimiter
{
    public function __construct(
        protected CacheRepository $cache,
        protected array $config
    ) {
    }

    public function check(string $provider): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $key = $this->getCacheKey($provider);
        $limit = $this->getLimit($provider);
        $window = $this->getWindow($provider);

        $current = (int) $this->cache->get($key, 0);

        if ($current >= $limit) {
            throw new RateLimitExceededException(
                "Rate limit exceeded for provider [{$provider}]. Limit: {$limit} requests per {$window} seconds."
            );
        }

        $this->increment($key, $window);
    }

    public function remaining(string $provider): int
    {
        $key = $this->getCacheKey($provider);
        $limit = $this->getLimit($provider);
        $current = (int) $this->cache->get($key, 0);

        return max(0, $limit - $current);
    }

    public function reset(string $provider): void
    {
        $key = $this->getCacheKey($provider);
        $this->cache->forget($key);
    }

    protected function increment(string $key, int $window): void
    {
        if ($this->cache->has($key)) {
            $this->cache->increment($key);
        } else {
            $this->cache->put($key, 1, $window);
        }
    }

    protected function isEnabled(): bool
    {
        return $this->config['enabled'] ?? false;
    }

    protected function getLimit(string $provider): int
    {
        return $this->config['providers'][$provider]['limit']
            ?? $this->config['default_limit']
            ?? 60;
    }

    protected function getWindow(string $provider): int
    {
        return $this->config['providers'][$provider]['window']
            ?? $this->config['default_window']
            ?? 60;
    }

    protected function getCacheKey(string $provider): string
    {
        $prefix = $this->config['cache_prefix'] ?? 'ai_rate_limit';

        return "{$prefix}:{$provider}";
    }
}
