<?php

namespace App\Services;

class ChunkService
{
    public int $chunkSize = 400; // Number of words per chunk
    public int $overlapSize = 50; // Number of characters to overlap between chunks

    public function chunk(string $content)
    {
        $content = preg_replace('/\s+/', ' ', trim($content)); // Normalize whitespace
        $words = explode(' ', $content);
        $total = count($words);

        if ($total === 0) return [];

        $index = 0;
        $start = 0;
        $chunks = [];

        while ($start < $total){
            $end = min($start + $this->chunkSize, $total); 
            /**
             * STEP 1 => START = 0, END = 400 / array_slice($words, 0, 400) => words[0] to words[399]
             * STEP 2 => START = 350, END = 750 / array_slice($words, 350, 400) => words[350] to words[749]
             * STEP 3 => START = 700, END = 1100 / array_slice($words, 700, 400) => words[700] to words[1099]
             */
            $contentChunk = implode(' ', array_slice($words, $start, $end - $start));
            $chunks[] = [
                'index' => $index++,
                'content' => $contentChunk,
                'word_count' => $end - $start, // 400 for the first chunk, 350 for the second chunk, etc.
            ];

            if ($end >= $total) break; // Reached the end of the content

            $start += ($this->chunkSize - $this->overlapSize); // Move start forward by chunk size minus overlap (400 - 50 = 350)

        }

        return $chunks;


    }
        // Rough token estimate: 1 word ≈ 1.3 tokens
    public function estimateTokens(string $text): int
    {
        return (int) (str_word_count($text) * 1.3);
    }
}