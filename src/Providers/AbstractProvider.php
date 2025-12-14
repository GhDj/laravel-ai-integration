<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Ghdj\AIIntegration\Contracts\AIProviderInterface;
use Ghdj\AIIntegration\Exceptions\APIException;

abstract class AbstractProvider implements AIProviderInterface
{
    protected Client $client;
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->client = $this->createHttpClient();
    }

    abstract protected function createHttpClient(): Client;

    abstract public function getName(): string;

    abstract public function getModels(): array;

    public function supportsStreaming(): bool
    {
        return false;
    }

    protected function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    protected function request(string $method, string $endpoint, array $options = []): array
    {
        try {
            $response = $this->client->request($method, $endpoint, $options);
            $body = $response->getBody()->getContents();

            return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (GuzzleException $e) {
            throw new APIException(
                "API request failed: {$e->getMessage()}",
                $e->getCode(),
                null,
                $e
            );
        } catch (\JsonException $e) {
            throw new APIException(
                "Failed to parse API response: {$e->getMessage()}",
                0,
                null,
                $e
            );
        }
    }
}
