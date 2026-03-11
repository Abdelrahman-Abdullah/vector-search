<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class EmbeddingService
{
    private string $modelName = 'gemini-embedding-001';
    private int $cacheTTL     = 86400; // 24 hours

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
        if (empty($texts))  return [];

        $result = array_fill(0, count($texts), null);
        $toFitch = [];

        foreach ($texts as $index => $text) {
            $content = Cache::get($this->cacheKey($text));

            if ($content) {
                $result[$index] = $content;
            } else {
                $toFitch[$index] = $text;
            }
        }

        if (empty($toFitch)) {
            return $result;
        }

        $requests = $this->prepareBeforeEmbedding($toFitch);
        
        // Simulate embedding generation (replace with actual API call)
        $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-goog-api-key' => env('API_KEY')
            ])
            ->timeout(60)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:batchEmbedContents", [
                'requests' => $requests,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Gemini API error: ' . $response->body());
        }

        foreach ($response->json('embeddings') as $index => $item) {
            $originalIndex = $index;
            $vector = $item['values'];
            $result[$originalIndex] = $vector;
        }

        Cache::put($this->cacheKey($toFitch[$originalIndex]), $vector, $this->cacheTTL);

        return $result;
    }

    private function cacheKey(string $text): string
    {
        return 'embedding:' . hash('sha256', $this->modelName . '||' . $text);
    }

    private function prepareBeforeEmbedding(array $texts): array
    {
        return array_map(fn($text) => [
            "model"=> "models/" . $this->modelName,
            "content" => [
                "parts" => [
                    ["text" => $text]
                ]
            ],
            "taskType" => "SEMANTIC_SIMILARITY",
            "output_dimensionality" => 1536
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
