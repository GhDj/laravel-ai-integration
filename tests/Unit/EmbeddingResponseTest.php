<?php

declare(strict_types=1);

namespace Ghdj\AIIntegration\Tests\Unit;

use Ghdj\AIIntegration\DTOs\EmbeddingResponse;
use Ghdj\AIIntegration\Tests\TestCase;

class EmbeddingResponseTest extends TestCase
{
    public function test_it_creates_response_with_embeddings(): void
    {
        $embeddings = [
            array_fill(0, 1536, 0.1),
            array_fill(0, 1536, 0.2),
        ];

        $response = new EmbeddingResponse(
            embeddings: $embeddings,
            model: 'text-embedding-3-small',
            usage: ['total_tokens' => 10],
            raw: []
        );

        $this->assertEquals($embeddings, $response->getEmbeddings());
        $this->assertEquals('text-embedding-3-small', $response->getModel());
    }

    public function test_it_returns_first_embedding(): void
    {
        $first = array_fill(0, 1536, 0.1);
        $second = array_fill(0, 1536, 0.2);

        $response = new EmbeddingResponse(
            embeddings: [$first, $second],
            model: 'text-embedding-3-small',
            usage: [],
            raw: []
        );

        $this->assertEquals($first, $response->getFirstEmbedding());
    }

    public function test_it_returns_empty_array_when_no_embeddings(): void
    {
        $response = new EmbeddingResponse(
            embeddings: [],
            model: 'text-embedding-3-small',
            usage: [],
            raw: []
        );

        $this->assertEmpty($response->getFirstEmbedding());
    }

    public function test_it_returns_usage_data(): void
    {
        $usage = ['total_tokens' => 25, 'prompt_tokens' => 25];

        $response = new EmbeddingResponse(
            embeddings: [],
            model: 'text-embedding-3-small',
            usage: $usage,
            raw: []
        );

        $this->assertEquals($usage, $response->getUsage());
        $this->assertEquals(25, $response->getTotalTokens());
    }

    public function test_it_returns_zero_for_missing_total_tokens(): void
    {
        $response = new EmbeddingResponse(
            embeddings: [],
            model: 'text-embedding-3-small',
            usage: [],
            raw: []
        );

        $this->assertEquals(0, $response->getTotalTokens());
    }

    public function test_it_returns_raw_response(): void
    {
        $raw = [
            'object' => 'list',
            'data' => [['embedding' => [0.1, 0.2]]],
        ];

        $response = new EmbeddingResponse(
            embeddings: [[0.1, 0.2]],
            model: 'text-embedding-3-small',
            usage: [],
            raw: $raw
        );

        $this->assertEquals($raw, $response->getRaw());
    }

    public function test_it_converts_to_array(): void
    {
        $embeddings = [[0.1, 0.2, 0.3]];
        $usage = ['total_tokens' => 5];

        $response = new EmbeddingResponse(
            embeddings: $embeddings,
            model: 'text-embedding-3-small',
            usage: $usage,
            raw: []
        );

        $array = $response->toArray();

        $this->assertEquals($embeddings, $array['embeddings']);
        $this->assertEquals('text-embedding-3-small', $array['model']);
        $this->assertEquals($usage, $array['usage']);
    }

    public function test_it_handles_single_embedding(): void
    {
        $embedding = array_fill(0, 768, 0.05);

        $response = new EmbeddingResponse(
            embeddings: [$embedding],
            model: 'text-embedding-004',
            usage: ['total_tokens' => 3],
            raw: []
        );

        $this->assertCount(1, $response->getEmbeddings());
        $this->assertEquals($embedding, $response->getFirstEmbedding());
    }
}
