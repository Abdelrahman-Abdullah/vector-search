<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadFileRequest;
use App\Models\Article;
use App\Services\FileParserService;

class ArticleController extends Controller
{
    public function __construct(
        public FileParserService $fileParserService
    ){}

   public function upload(UploadFileRequest $request)
   {
        $file = $request->file('file');

        try {
            $content = $this->fileParserService->extract($file);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to parse file: ' . $e->getMessage()], 500);
        }

        if (trim($content) === '') {
            return response()->json(['error' => 'No text could be extracted from the file.'], 400);
        }

        $this->dispatchProcessContentJob(
            content: $content,
            title: $request->input('title', $file->getClientOriginalName()),
            fileType: $this->fileParserService->getFileType($file),
            fileName: $file->getClientOriginalName()
        );


   }
   private function dispatchProcessContentJob(
       string $content,
       string $title,
       ?string $fileType,
       ?string $fileName

   )
   {
        $article = Article::create([
            'title' => $title,
            'content' => $content,
            'file_type' => $fileType,
            'file_name' => $fileName,
            'status' => 'pending',
            'processing_started_at' => now(),
        ]);

        // Dispatch the job to process the article content
        // ----
        
        return response()->json([
            'message'    => 'Received! Processing in background...',
            'article_id' => $article->id,
            'status'     => 'pending',
            'poll_url'   => "/api/articles/{$article->id}/status",
        ], 202);
   }
}
