<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Services;

use Ghdj\AIIntegration\Contracts\AIManagerInterface;
use Ghdj\AIIntegration\Contracts\AIProviderInterface;
use Ghdj\AIIntegration\Contracts\AIResponseInterface;
use Ghdj\AIIntegration\Contracts\EmbeddingResponseInterface;
use Ghdj\AIIntegration\Contracts\PromptTemplateInterface;
use Ghdj\AIIntegration\Exceptions\ProviderNotFoundException;
use Ghdj\AIIntegration\Prompts\PromptManager;
use Ghdj\AIIntegration\Providers\ClaudeProvider;
use Ghdj\AIIntegration\Providers\GeminiProvider;
use Ghdj\AIIntegration\Providers\OpenAIProvider;
use InvalidArgumentException;

class AIManager implements AIManagerInterface
{
    protected array $providers = [];
    protected array $customCreators = [];

    public function __construct(
        protected array $config,
        protected RateLimiter $rateLimiter,
        protected CostTracker $costTracker,
        protected ?PromptManager $promptManager = null
    ) {
        $this->promptManager ??= new PromptManager();
    }

    public function provider(?string $name = null): AIProviderInterface
    {
        $name = $name ?? $this->getDefaultProvider();

        if (isset($this->providers[$name])) {
            return $this->providers[$name];
        }

        return $this->providers[$name] = $this->resolve($name);
    }

    public function chat(array $messages, array $options = []): AIResponseInterface
    {
        $provider = $this->provider($options['provider'] ?? null);

        $this->rateLimiter->check($provider->getName());

        $response = $provider->chat($messages, $options);

        $this->costTracker->track($provider->getName(), $response);

        return $response;
    }

    public function complete(string $prompt, array $options = []): AIResponseInterface
    {
        $provider = $this->provider($options['provider'] ?? null);

        $this->rateLimiter->check($provider->getName());

        $response = $provider->complete($prompt, $options);

        $this->costTracker->track($provider->getName(), $response);

        return $response;
    }

    public function embed(string|array $input, array $options = []): EmbeddingResponseInterface
    {
        $provider = $this->provider($options['provider'] ?? null);

        $this->rateLimiter->check($provider->getName());

        $response = $provider->embed($input, $options);

        $this->costTracker->trackEmbedding($provider->getName(), $response);

        return $response;
    }

    public function getDefaultProvider(): string
    {
        return $this->config['default'] ?? 'openai';
    }

    public function extend(string $name, callable $callback): static
    {
        $this->customCreators[$name] = $callback;

        return $this;
    }

    public function prompts(): PromptManager
    {
        return $this->promptManager;
    }

    public function prompt(string $name, array $variables = []): PromptTemplateInterface
    {
        return $this->promptManager->make($name, $variables);
    }

    public function chatWithTemplate(string $template, array $variables = [], array $options = []): AIResponseInterface
    {
        $messages = $this->promptManager->toMessages($template, $variables);

        return $this->chat($messages, $options);
    }

    public function completeWithTemplate(string $template, array $variables = [], array $options = []): AIResponseInterface
    {
        $prompt = $this->promptManager->render($template, $variables);

        return $this->complete($prompt, $options);
    }

    protected function resolve(string $name): AIProviderInterface
    {
        if (isset($this->customCreators[$name])) {
            return $this->customCreators[$name]($this->config);
        }

        $config = $this->config['providers'][$name] ?? null;

        if ($config === null) {
            throw new ProviderNotFoundException("AI provider [{$name}] is not configured.");
        }

        return $this->createProvider($name, $config);
    }

    protected function createProvider(string $name, array $config): AIProviderInterface
    {
        return match ($name) {
            'openai' => new OpenAIProvider($config),
            'claude' => $this->createClaudeProvider($config),
            'gemini' => $this->createGeminiProvider($config),
            default => throw new InvalidArgumentException("Unsupported AI provider [{$name}]."),
        };
    }

    protected function createClaudeProvider(array $config): AIProviderInterface
    {
        return new ClaudeProvider($config);
    }

    protected function createGeminiProvider(array $config): AIProviderInterface
    {
        return new GeminiProvider($config);
    }
}
