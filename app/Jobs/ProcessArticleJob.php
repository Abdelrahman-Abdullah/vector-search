<?php

namespace App\Jobs;

use App\Models\Article;
use App\Models\ArticleChunk;
use App\Services\ChunkService;
use App\Services\EmbeddingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessArticleJob implements ShouldQueue
{
    use Queueable, SerializesModels, InteractsWithQueue;

    public int $tries = 3;
    public array $backoff = [10, 30, 60]; // seconds between retries
    public int $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Article $article,
    ){}

    /**
     * Execute the job.
     */
    public function handle(ChunkService $chunker, EmbeddingService $embedder): void
    {
        Log::info("Processing article ID: {$this->article->id}");
        $this->article->update([
            'status' => 'processing',
            'processing_started_at' => now(),
        ]);

        $chunks = $chunker->chunk($this->article->body ?? '');
        if (empty($chunks)) {
            throw new \RuntimeException('No chunks generated.');
        }

        $embeddings = $embedder->embedBatch(array_column($chunks, 'content'));
        DB::transaction(function () use ($chunks ,$embeddings) {
            // Make retries idempotent: rebuild chunk rows from scratch.
            $this->article->chunks()->delete();

            foreach ($chunks as $index => $chunk) {
                ArticleChunk::create([
                    'article_id'  => $this->article->id,
                    'chunk_index' => $chunk['index'],
                    'content'     => $chunk['content'],
                    'embedding'   => $embeddings[$index] ?? null,
                ]);

            }

            $this->article->update([
                'total_chunks' => count($chunks),
                'status' => 'completed',
                'processing_completed_at' => now(),
                'error_message' => null
            ]);
        });
        Log::info("Job completed", ['article_id' => $this->article->id, 'chunks' => count($chunks)]);

    }

    // Called only when ALL retries are exhausted
    public function failed(\Throwable $e): void
    {
        $this->article->update([
            'status'        => 'failed',
            'error_message' => $e->getMessage(),
        ]);
    }}
