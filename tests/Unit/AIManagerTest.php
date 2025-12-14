<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Tests\Unit;

use Ghdj\AIIntegration\Contracts\AIManagerInterface;
use Ghdj\AIIntegration\Contracts\AIProviderInterface;
use Ghdj\AIIntegration\Contracts\PromptTemplateInterface;
use Ghdj\AIIntegration\Exceptions\ProviderNotFoundException;
use Ghdj\AIIntegration\Prompts\PromptManager;
use Ghdj\AIIntegration\Providers\ClaudeProvider;
use Ghdj\AIIntegration\Providers\GeminiProvider;
use Ghdj\AIIntegration\Providers\OpenAIProvider;
use Ghdj\AIIntegration\Services\AIManager;
use Ghdj\AIIntegration\Tests\TestCase;

class AIManagerTest extends TestCase
{
    public function test_it_resolves_from_container(): void
    {
        $manager = $this->app->make(AIManagerInterface::class);

        $this->assertInstanceOf(AIManager::class, $manager);
    }

    public function test_it_returns_default_provider(): void
    {
        $manager = $this->app->make(AIManagerInterface::class);

        $this->assertEquals('openai', $manager->getDefaultProvider());
    }

    public function test_it_resolves_openai_provider(): void
    {
        $manager = $this->app->make(AIManagerInterface::class);
        $provider = $manager->provider('openai');

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
        $this->assertInstanceOf(OpenAIProvider::class, $provider);
        $this->assertEquals('openai', $provider->getName());
    }

    public function test_it_throws_exception_for_unknown_provider(): void
    {
        $manager = $this->app->make(AIManagerInterface::class);

        $this->expectException(ProviderNotFoundException::class);

        $manager->provider('unknown');
    }

    public function test_it_caches_provider_instances(): void
    {
        $manager = $this->app->make(AIManagerInterface::class);

        $provider1 = $manager->provider('openai');
        $provider2 = $manager->provider('openai');

        $this->assertSame($provider1, $provider2);
    }

    public function test_it_allows_extending_with_custom_providers(): void
    {
        $manager = $this->app->make(AIManagerInterface::class);

        $customProvider = $this->createMock(AIProviderInterface::class);
        $customProvider->method('getName')->willReturn('custom');

        $manager->extend('custom', fn () => $customProvider);

        $this->app['config']->set('ai.providers.custom', ['api_key' => 'test']);

        $resolved = $manager->provider('custom');

        $this->assertSame($customProvider, $resolved);
    }

    public function test_it_resolves_claude_provider(): void
    {
        $this->app['config']->set('ai.providers.claude.api_key', 'test-key');

        $manager = $this->app->make(AIManagerInterface::class);
        $provider = $manager->provider('claude');

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
        $this->assertInstanceOf(ClaudeProvider::class, $provider);
        $this->assertEquals('claude', $provider->getName());
    }

    public function test_it_resolves_gemini_provider(): void
    {
        $this->app['config']->set('ai.providers.gemini.api_key', 'test-key');

        $manager = $this->app->make(AIManagerInterface::class);
        $provider = $manager->provider('gemini');

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
        $this->assertInstanceOf(GeminiProvider::class, $provider);
        $this->assertEquals('gemini', $provider->getName());
    }

    public function test_it_returns_prompt_manager(): void
    {
        $manager = $this->app->make(AIManagerInterface::class);

        $prompts = $manager->prompts();

        $this->assertInstanceOf(PromptManager::class, $prompts);
    }

    public function test_it_creates_prompt_template(): void
    {
        $manager = $this->app->make(AIManagerInterface::class);
        $manager->prompts()->register('greeting', 'Hello, {{ name }}!');

        $template = $manager->prompt('greeting', ['name' => 'World']);

        $this->assertInstanceOf(PromptTemplateInterface::class, $template);
        $this->assertEquals('Hello, World!', $template->render());
    }

    public function test_provider_returns_default_when_null(): void
    {
        $manager = $this->app->make(AIManagerInterface::class);

        $provider = $manager->provider(null);

        $this->assertInstanceOf(OpenAIProvider::class, $provider);
    }

    public function test_custom_provider_receives_config(): void
    {
        $manager = $this->app->make(AIManagerInterface::class);
        $receivedConfig = null;

        $manager->extend('test', function ($config) use (&$receivedConfig) {
            $receivedConfig = $config;
            $mock = $this->createMock(AIProviderInterface::class);
            $mock->method('getName')->willReturn('test');

            return $mock;
        });

        $this->app['config']->set('ai.providers.test', ['api_key' => 'my-key']);

        $manager->provider('test');

        $this->assertIsArray($receivedConfig);
        $this->assertArrayHasKey('providers', $receivedConfig);
    }

    public function test_extend_returns_self_for_chaining(): void
    {
        $manager = $this->app->make(AIManagerInterface::class);

        $mock = $this->createMock(AIProviderInterface::class);

        $result = $manager->extend('test1', fn () => $mock)
                          ->extend('test2', fn () => $mock);

        $this->assertSame($manager, $result);
    }
}
