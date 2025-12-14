<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Tests\Unit;

use Ghdj\AIIntegration\AIIntegrationServiceProvider;
use Ghdj\AIIntegration\Contracts\AIManagerInterface;
use Ghdj\AIIntegration\Prompts\PromptManager;
use Ghdj\AIIntegration\Services\AIManager;
use Ghdj\AIIntegration\Services\CostTracker;
use Ghdj\AIIntegration\Services\RateLimiter;
use Ghdj\AIIntegration\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_it_registers_ai_manager_interface(): void
    {
        $manager = $this->app->make(AIManagerInterface::class);

        $this->assertInstanceOf(AIManager::class, $manager);
    }

    public function test_it_registers_ai_manager_as_singleton(): void
    {
        $manager1 = $this->app->make(AIManagerInterface::class);
        $manager2 = $this->app->make(AIManagerInterface::class);

        $this->assertSame($manager1, $manager2);
    }

    public function test_it_registers_ai_alias(): void
    {
        $manager = $this->app->make('ai');

        $this->assertInstanceOf(AIManager::class, $manager);
    }

    public function test_it_registers_rate_limiter(): void
    {
        $rateLimiter = $this->app->make(RateLimiter::class);

        $this->assertInstanceOf(RateLimiter::class, $rateLimiter);
    }

    public function test_it_registers_rate_limiter_as_singleton(): void
    {
        $limiter1 = $this->app->make(RateLimiter::class);
        $limiter2 = $this->app->make(RateLimiter::class);

        $this->assertSame($limiter1, $limiter2);
    }

    public function test_it_registers_cost_tracker(): void
    {
        $tracker = $this->app->make(CostTracker::class);

        $this->assertInstanceOf(CostTracker::class, $tracker);
    }

    public function test_it_registers_cost_tracker_as_singleton(): void
    {
        $tracker1 = $this->app->make(CostTracker::class);
        $tracker2 = $this->app->make(CostTracker::class);

        $this->assertSame($tracker1, $tracker2);
    }

    public function test_it_registers_prompt_manager(): void
    {
        $manager = $this->app->make(PromptManager::class);

        $this->assertInstanceOf(PromptManager::class, $manager);
    }

    public function test_it_registers_prompt_manager_as_singleton(): void
    {
        $manager1 = $this->app->make(PromptManager::class);
        $manager2 = $this->app->make(PromptManager::class);

        $this->assertSame($manager1, $manager2);
    }

    public function test_it_merges_config(): void
    {
        $config = $this->app['config']->get('ai');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('default', $config);
        $this->assertArrayHasKey('providers', $config);
        $this->assertArrayHasKey('rate_limiting', $config);
        $this->assertArrayHasKey('cost_tracking', $config);
    }

    public function test_it_provides_services(): void
    {
        $provider = new AIIntegrationServiceProvider($this->app);
        $provides = $provider->provides();

        $this->assertContains(AIManagerInterface::class, $provides);
        $this->assertContains(AIManager::class, $provides);
        $this->assertContains(RateLimiter::class, $provides);
        $this->assertContains(CostTracker::class, $provides);
        $this->assertContains(PromptManager::class, $provides);
        $this->assertContains('ai', $provides);
    }

    public function test_config_has_openai_provider(): void
    {
        $config = $this->app['config']->get('ai.providers.openai');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('api_key', $config);
        $this->assertArrayHasKey('default_model', $config);
    }

    public function test_config_has_claude_provider(): void
    {
        $config = $this->app['config']->get('ai.providers.claude');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('api_key', $config);
        $this->assertArrayHasKey('default_model', $config);
    }

    public function test_config_has_gemini_provider(): void
    {
        $config = $this->app['config']->get('ai.providers.gemini');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('api_key', $config);
        $this->assertArrayHasKey('default_model', $config);
    }
}
