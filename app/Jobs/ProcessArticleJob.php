<?php

namespace App\Jobs;

use App\Models\Article;
use App\Services\ChunkService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessArticleJob implements ShouldQueue
{
    use Queueable, SerializesModels, InteractsWithQueue, Queueable;

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
    public function handle(ChunkService $chunkService): void
    {
        Log::info("Processing article ID: {$this->article->id}");
        $this->article->update([
            'status' => 'processing',
            'processing_started_at' => now(),
        ]);

        $chunks = $chunkService->chunk($this->article->body ?? '');
        if (empty($chunks)) {
            throw new \RuntimeException('No chunks generated.');
        }



    }
}
