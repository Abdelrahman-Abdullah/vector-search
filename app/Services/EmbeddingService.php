<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class EmbeddingService
{
    private string $modelName = 'text-embedding-3-small';
    private int $cacheTTL     = 86400; // 24 hours

    public function embed(string $text)
    {
        $key = $this->cacheKey($text);
        $content = Cache::get($key);
        if ($content) {
            return $content;
        }

        // Simulate embedding generation (replace with actual API call)
        $embedding = array_map('floatval', str_split(substr(hash('sha256', $text), 0, 64), 8));

        Cache::put($key, $embedding, $this->cacheTTL);

        return $embedding;
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

        // Simulate embedding generation (replace with actual API call)
        $response = Http::withToken(env('OPENAI_API_KEY'))
            ->timeout(60)
            ->post('https://api.openai.com/v1/embeddings', [
                'model' => $this->modelName,
                'input' => $toFitch,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('OpenAI API error: ' . $response->body());
        }

        $embeddings = $response->json('data');
        $toFitchKeys = array_keys($toFitch);

        foreach ($embeddings as $item) {
            $originalIndex = $toFitchKeys[$item['index']];
            $vector = $item['embedding'];
            $result[$originalIndex] = $vector;
        }

        Cache::put($this->cacheKey($toFitch[$originalIndex]), $vector, $this->cacheTTL);

        return $result;
    }

    private function cacheKey(string $text): string
    {
        return 'embedding:' . hash('sha256', $this->modelName . '||' . $text);
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
