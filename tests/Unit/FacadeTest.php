<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Tests\Unit;

use Ghdj\AIIntegration\Contracts\AIProviderInterface;
use Ghdj\AIIntegration\Facades\AI;
use Ghdj\AIIntegration\Prompts\PromptManager;
use Ghdj\AIIntegration\Providers\OpenAIProvider;
use Ghdj\AIIntegration\Services\AIManager;
use Ghdj\AIIntegration\Tests\TestCase;

class FacadeTest extends TestCase
{
    public function test_facade_resolves_to_ai_manager(): void
    {
        $resolved = AI::getFacadeRoot();

        $this->assertInstanceOf(AIManager::class, $resolved);
    }

    public function test_facade_returns_default_provider_name(): void
    {
        $default = AI::getDefaultProvider();

        $this->assertEquals('openai', $default);
    }

    public function test_facade_returns_provider_instance(): void
    {
        $provider = AI::provider('openai');

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
        $this->assertInstanceOf(OpenAIProvider::class, $provider);
    }

    public function test_facade_returns_default_provider_when_null(): void
    {
        $provider = AI::provider();

        $this->assertInstanceOf(OpenAIProvider::class, $provider);
    }

    public function test_facade_returns_prompt_manager(): void
    {
        $prompts = AI::prompts();

        $this->assertInstanceOf(PromptManager::class, $prompts);
    }

    public function test_facade_can_register_prompt_template(): void
    {
        AI::prompts()->register('test-facade', 'Hello, {{ name }}!');

        $this->assertTrue(AI::prompts()->has('test-facade'));

        $result = AI::prompts()->render('test-facade', ['name' => 'World']);

        $this->assertEquals('Hello, World!', $result);
    }

    public function test_facade_can_extend_with_custom_provider(): void
    {
        $customProvider = $this->createMock(AIProviderInterface::class);
        $customProvider->method('getName')->willReturn('custom');

        AI::extend('custom', fn() => $customProvider);

        $this->app['config']->set('ai.providers.custom', ['api_key' => 'test']);

        $resolved = AI::provider('custom');

        $this->assertSame($customProvider, $resolved);
    }
}
