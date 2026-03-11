<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class EmbeddingService
{
    private string $modelName;
    private int $cacheTTL;
    private int $dimensions;

    public function __construct()
    {
        $this->modelName = (string) config('services.gemini.embedding_model', 'gemini-embedding-001');
        $this->cacheTTL = (int) config('services.gemini.embedding_cache_ttl', 86400);
        $this->dimensions = (int) config('services.gemini.embedding_dimensions', 1536);
    }

    public function embed(string $text): array
    {
        $key    = $this->cacheKey($text);
        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached; // free & instant from Redis
        }

        $vector = $this->embedBatch([$text])[0];
        Cache::put($key, $vector, $this->cacheTTL);

        return $vector;
    }

    public function embedBatch(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        $result = array_fill(0, count($texts), null);
        $toFetch = [];

        foreach ($texts as $index => $text) {
            $content = Cache::get($this->cacheKey($text));

            if ($content !== null) {
                $result[$index] = $content;
            } else {
                $toFetch[$index] = $text;
            }
        }

        if (empty($toFetch)) {
            return $result;
        }

        /**
         * Keep original input indices as keys so vectors are written back to the
         * matching position in the final result array.
         */
        $requests = $this->prepareBeforeEmbedding(array_values($toFetch));

        $apiKey = (string) config('services.gemini.api_key');
        if ($apiKey === '') {
            throw new \RuntimeException('Gemini API key is not configured. Set GEMINI_API_KEY in your environment.');
        }

        $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $apiKey,
            ])
            ->timeout(60)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->modelName}:batchEmbedContents", [
                'requests' => $requests,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Gemini API error: ' . $response->body());
        }

        $embeddings = $response->json('embeddings', []);
        $originalIndexes = array_keys($toFetch);

        foreach ($embeddings as $responseIndex => $item) {
            $originalIndex = $originalIndexes[$responseIndex] ?? null;
            if ($originalIndex === null) {
                continue;
            }

            $vector = Arr::get($item, 'values', []);
            $result[$originalIndex] = $vector;
            Cache::put($this->cacheKey((string) $toFetch[$originalIndex]), $vector, $this->cacheTTL);
        }

        return $result;
    }

    private function cacheKey(string $text): string
    {
        return 'embedding:' . hash('sha256', $this->modelName . '||' . $text);
    }

    private function prepareBeforeEmbedding(array $texts): array
    {
        return array_map(fn($text) => [
            'model' => 'models/' . $this->modelName,
            'content' => [
                'parts' => [
                    ['text' => $text],
                ],
            ],
            'taskType' => 'SEMANTIC_SIMILARITY',
            // Must match the database vector column dimensions.
            'output_dimensionality' => $this->dimensions,
        ], $texts);
    }

    
    // ── Cosine similarity (fallback when not using pgvector operators) ────────
    public function cosineSimilarity(array $a, array $b): float
    {
        $dot = $magA = $magB = 0.0;

        foreach ($a as $i => $valA) {
            $dot  += $valA * $b[$i];
            $magA += $valA * $valA;
            $magB += $b[$i] * $b[$i];
        }

        $magA = sqrt($magA);
        $magB = sqrt($magB);

        return ($magA == 0 || $magB == 0) ? 0.0 : $dot / ($magA * $magB);
    }

}
