<?php

namespace Tests\Feature;

use App\Services\ChunkService;
use Tests\TestCase;

class ChunkServiceTest extends TestCase
{
    public function test_it_chunks_with_word_overlap(): void
    {
        $chunkService = new ChunkService();
        $chunkService->chunkSize = 4;
        $chunkService->overlapSize = 1;

        $chunks = $chunkService->chunk('one two three four five six seven');

        $this->assertCount(2, $chunks);
        $this->assertSame('one two three four', $chunks[0]['content']);
        $this->assertSame('four five six seven', $chunks[1]['content']);
    }
}
