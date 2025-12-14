<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Facades;

use Ghdj\AIIntegration\Contracts\AIManagerInterface;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Ghdj\AIIntegration\Contracts\AIProviderInterface provider(?string $name = null)
 * @method static \Ghdj\AIIntegration\Contracts\AIResponseInterface chat(array $messages, array $options = [])
 * @method static \Ghdj\AIIntegration\Contracts\AIResponseInterface complete(string $prompt, array $options = [])
 * @method static \Ghdj\AIIntegration\Contracts\EmbeddingResponseInterface embed(string|array $input, array $options = [])
 * @method static string getDefaultProvider()
 * @method static \Ghdj\AIIntegration\Services\AIManager extend(string $name, callable $callback)
 * @method static \Ghdj\AIIntegration\Prompts\PromptManager prompts()
 * @method static \Ghdj\AIIntegration\Contracts\PromptTemplateInterface prompt(string $name, array $variables = [])
 * @method static \Ghdj\AIIntegration\Contracts\AIResponseInterface chatWithTemplate(string $template, array $variables = [], array $options = [])
 * @method static \Ghdj\AIIntegration\Contracts\AIResponseInterface completeWithTemplate(string $template, array $variables = [], array $options = [])
 *
 * @see \Ghdj\AIIntegration\Services\AIManager
 */
class AI extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AIManagerInterface::class;
    }
}
