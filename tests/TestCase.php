<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Tests;

use Ghdj\AIIntegration\AIIntegrationServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            AIIntegrationServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'AI' => \Ghdj\AIIntegration\Facades\AI::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('ai.default', 'openai');
        $app['config']->set('ai.providers.openai.api_key', 'test-api-key');
        $app['config']->set('ai.providers.openai.default_model', 'gpt-4o');
    }
}
