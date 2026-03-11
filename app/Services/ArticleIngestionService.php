<?php

namespace App\Services;

use App\Jobs\ProcessArticleJob;
use App\Models\Article;
use Illuminate\Http\JsonResponse;

class ArticleIngestionService
{
    public function dispatchProcessContentJob(
        string $content,
        string $title,
        ?string $fileType,
        ?string $fileName,
    ): JsonResponse {
        $article = Article::create([
            'title' => $title,
            'body' => $content,
            'file_type' => $fileType,
            'source_file' => $fileName,
            'status' => 'pending',
            'processing_started_at' => now(),
        ]);

        // Queue embeddings work away from the request lifecycle.
        ProcessArticleJob::dispatch($article)->onQueue('embeddings');

        return response()->json([
            'message' => 'Received! Processing in background...',
            'article_id' => $article->id,
            'status' => 'pending',
            'poll_url' => "/api/articles/{$article->id}/status",
        ], 202);
    }
}
