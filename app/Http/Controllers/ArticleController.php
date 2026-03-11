<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArticleChunksSearchRequest;
use App\Http\Requests\SearchVectorRequest;
use App\Http\Requests\UploadFileRequest;
use App\Models\Article;
use App\Services\ArticleIngestionService;
use App\Services\ArticleSearchService;
use App\Services\FileParserService;

class ArticleController extends Controller
{
    public function __construct(
        public FileParserService $fileParserService,
        public ArticleIngestionService $ingestionService,
        public ArticleSearchService $searchService,
    ){}

   public function upload(UploadFileRequest $request)
   {
        $file = $request->file('file');

        try {
            $content = $this->fileParserService->extract($file);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to parse file: ' . $e->getMessage()], 500);
        }

        if (trim($content) === '') {
            return response()->json(['error' => 'No text could be extracted from the file.'], 400);
        }

       return $this->ingestionService->dispatchProcessContentJob(
            content: $content,
            title: $request->input('title', $file->getClientOriginalName()),
            fileType: $this->fileParserService->getFileType($file),
            fileName: $file->getClientOriginalName()
        );


   }

   public function vectorSearch(SearchVectorRequest $request)
   {
       $query = (string) $request->validated('q');
       $top = (int) $request->validated('top', 5);

       return response()->json([
            'method' => 'Vector Search (pgvector)',
            'query' => $query,
            'results' => $this->searchService->searchAcrossArticles($query, $top),
       ]);
   }

   public function status(int $id)
   {
        $article = Article::find($id);
        if (!$article)
        {
            return response()->json([
                'error' => 'Article not found'
             ], 404);
            
        }
        return response()->json([
            'article_id'   => $article->id,
            'title'        => $article->title,
            'status'       => $article->status,
            'total_chunks' => $article->total_chunks,
            'started_at'   => $article->processing_started_at,
            'finished_at'  => $article->processing_completed_at,
            'error'        => $article->error_message,
            ]);
   }

   public function checkChunks(ArticleChunksSearchRequest $request, int $id)
   {
        $article = Article::find($id);
        $query = (string) $request->validated('q');
        $top = (int) $request->validated('top', 10);

        if (!$article)
        {
            return response()->json([
                'error' => 'Article not found'
             ], 404);
            
        }

        $chunks = $this->searchService->searchWithinArticle($article->id, $query, $top);

        return response()->json([
            'article_id'   => $article->id,
            'title'        => $article->title,
            'total_chunks' => $article->total_chunks,
            'chunks'       => $chunks,
        ]);

   }
}
