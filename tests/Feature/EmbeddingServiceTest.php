<?php

namespace Tests\Feature;

use App\Services\EmbeddingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmbeddingServiceTest extends TestCase
{
    public function test_embed_batch_preserves_original_input_indexes_when_cache_hits_exist(): void
    {
        config([
            'cache.default' => 'array',
            'services.gemini.api_key' => 'test-key',
            'services.gemini.embedding_model' => 'gemini-embedding-001',
            'services.gemini.embedding_dimensions' => 1536,
        ]);

        Cache::flush();
        Cache::put('embedding:' . hash('sha256', 'gemini-embedding-001||cached-text'), [9.0, 9.0], 60);

        Http::fake([
            '*' => Http::response([
                'embeddings' => [
                    ['values' => [1.0, 1.1]],
                    ['values' => [2.0, 2.2]],
                ],
            ], 200),
        ]);

        $service = app(EmbeddingService::class);

        $result = $service->embedBatch(['new-one', 'cached-text', 'new-two']);

        $this->assertSame([1.0, 1.1], array_map('floatval', $result[0]));
        $this->assertSame([9.0, 9.0], array_map('floatval', $result[1]));
        $this->assertSame([2.0, 2.2], array_map('floatval', $result[2]));
    }
}
