<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration;

use Illuminate\Support\ServiceProvider;
use Ghdj\AIIntegration\Contracts\AIManagerInterface;
use Ghdj\AIIntegration\Prompts\PromptManager;
use Ghdj\AIIntegration\Services\AIManager;
use Ghdj\AIIntegration\Services\CostTracker;
use Ghdj\AIIntegration\Services\RateLimiter;

class AIIntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/ai.php',
            'ai'
        );

        $this->app->singleton(RateLimiter::class, function ($app) {
            return new RateLimiter(
                $app['cache.store'],
                $app['config']->get('ai.rate_limiting', [])
            );
        });

        $this->app->singleton(CostTracker::class, function ($app) {
            return new CostTracker(
                $app['config']->get('ai.cost_tracking', [])
            );
        });

        $this->app->singleton(PromptManager::class, function ($app) {
            return new PromptManager(
                $app['config']->get('ai.prompts', [])
            );
        });

        $this->app->singleton(AIManagerInterface::class, function ($app) {
            return new AIManager(
                $app['config']->get('ai', []),
                $app->make(RateLimiter::class),
                $app->make(CostTracker::class),
                $app->make(PromptManager::class)
            );
        });

        $this->app->alias(AIManagerInterface::class, 'ai');
        $this->app->alias(AIManagerInterface::class, AIManager::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/ai.php' => config_path('ai.php'),
            ], 'ai-config');

            $this->publishes([
                __DIR__ . '/../database/migrations/' => database_path('migrations'),
            ], 'ai-migrations');
        }
    }

    public function provides(): array
    {
        return [
            AIManagerInterface::class,
            AIManager::class,
            RateLimiter::class,
            CostTracker::class,
            PromptManager::class,
            'ai',
        ];
    }
}
