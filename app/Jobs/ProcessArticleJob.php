<?php

namespace App\Jobs;

use App\Models\Article;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessArticleJob implements ShouldQueue
{
    use Queueable, SerializesModels, InteractsWithQueue, Queueable;

    public int $tries = 3;
    public array $backoff = [10, 30, 60]; // seconds between retries
    public int $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(Article $article){}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //
    }
}
