<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Tests\Feature;

use Ghdj\AIIntegration\Contracts\AIProviderInterface;
use Ghdj\AIIntegration\Facades\AI;
use Ghdj\AIIntegration\Providers\ClaudeProvider;
use Ghdj\AIIntegration\Providers\GeminiProvider;
use Ghdj\AIIntegration\Providers\OpenAIProvider;
use Ghdj\AIIntegration\Tests\TestCase;

class ProviderIntegrationTest extends TestCase
{
    public function test_all_providers_implement_interface(): void
    {
        $this->app['config']->set('ai.providers.openai.api_key', 'test');
        $this->app['config']->set('ai.providers.claude.api_key', 'test');
        $this->app['config']->set('ai.providers.gemini.api_key', 'test');

        $openai = AI::provider('openai');
        $claude = AI::provider('claude');
        $gemini = AI::provider('gemini');

        $this->assertInstanceOf(AIProviderInterface::class, $openai);
        $this->assertInstanceOf(AIProviderInterface::class, $claude);
        $this->assertInstanceOf(AIProviderInterface::class, $gemini);
    }

    public function test_providers_have_correct_names(): void
    {
        $this->app['config']->set('ai.providers.openai.api_key', 'test');
        $this->app['config']->set('ai.providers.claude.api_key', 'test');
        $this->app['config']->set('ai.providers.gemini.api_key', 'test');

        $this->assertEquals('openai', AI::provider('openai')->getName());
        $this->assertEquals('claude', AI::provider('claude')->getName());
        $this->assertEquals('gemini', AI::provider('gemini')->getName());
    }

    public function test_providers_have_models(): void
    {
        $this->app['config']->set('ai.providers.openai.api_key', 'test');
        $this->app['config']->set('ai.providers.claude.api_key', 'test');
        $this->app['config']->set('ai.providers.gemini.api_key', 'test');

        $openaiModels = AI::provider('openai')->getModels();
        $claudeModels = AI::provider('claude')->getModels();
        $geminiModels = AI::provider('gemini')->getModels();

        $this->assertNotEmpty($openaiModels);
        $this->assertNotEmpty($claudeModels);
        $this->assertNotEmpty($geminiModels);

        $this->assertContains('gpt-4o', $openaiModels);
        $this->assertContains('claude-sonnet-4-20250514', $claudeModels);
        $this->assertContains('gemini-1.5-pro', $geminiModels);
    }

    public function test_openai_and_gemini_support_embeddings(): void
    {
        $openai = new OpenAIProvider(['api_key' => 'test']);
        $gemini = new GeminiProvider(['api_key' => 'test']);

        $this->assertNotEmpty($openai->getEmbeddingModels());
        $this->assertNotEmpty($gemini->getEmbeddingModels());
    }

    public function test_all_providers_support_streaming(): void
    {
        $openai = new OpenAIProvider(['api_key' => 'test']);
        $claude = new ClaudeProvider(['api_key' => 'test']);
        $gemini = new GeminiProvider(['api_key' => 'test']);

        $this->assertTrue($openai->supportsStreaming());
        $this->assertTrue($claude->supportsStreaming());
        $this->assertTrue($gemini->supportsStreaming());
    }

    public function test_switching_default_provider(): void
    {
        $this->app['config']->set('ai.default', 'openai');
        $this->assertEquals('openai', AI::getDefaultProvider());

        $this->app['config']->set('ai.default', 'claude');

        // Need to re-resolve to get new config
        $this->app->forgetInstance(\Ghdj\AIIntegration\Contracts\AIManagerInterface::class);
        AI::clearResolvedInstances();

        $this->assertEquals('claude', AI::getDefaultProvider());
    }

    public function test_custom_provider_registration(): void
    {
        $customProvider = $this->createMock(AIProviderInterface::class);
        $customProvider->method('getName')->willReturn('my-custom');
        $customProvider->method('getModels')->willReturn(['custom-model-1']);
        $customProvider->method('supportsStreaming')->willReturn(false);

        AI::extend('my-custom', fn() => $customProvider);
        $this->app['config']->set('ai.providers.my-custom', ['api_key' => 'test']);

        $resolved = AI::provider('my-custom');

        $this->assertEquals('my-custom', $resolved->getName());
        $this->assertEquals(['custom-model-1'], $resolved->getModels());
        $this->assertFalse($resolved->supportsStreaming());
    }

    public function test_provider_caching(): void
    {
        $this->app['config']->set('ai.providers.openai.api_key', 'test');

        $provider1 = AI::provider('openai');
        $provider2 = AI::provider('openai');
        $provider3 = AI::provider();

        $this->assertSame($provider1, $provider2);
        $this->assertSame($provider1, $provider3);
    }
}
