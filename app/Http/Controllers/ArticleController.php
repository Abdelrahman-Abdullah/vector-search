<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadFileRequest;
use App\Jobs\ProcessArticleJob;
use App\Models\Article;
use App\Services\EmbeddingService;
use App\Services\FileParserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArticleController extends Controller
{
    public function __construct(
        public FileParserService $fileParserService,
        public EmbeddingService $embedder
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

       return $this->dispatchProcessContentJob(
            content: $content,
            title: $request->input('title', $file->getClientOriginalName()),
            fileType: $this->fileParserService->getFileType($file),
            fileName: $file->getClientOriginalName()
        );


   }

   public function vectorSearch(Request $request)
   {
       $query = $request->query('q', '');
       $top = max(1 , min(20, $request->query('top', 5 )));

       $queryVector = $this->embedder->embed($query);
       $queryStr = "[" . implode(',', $queryVector) . "]";

       $rows = DB::select("
       SELECT
            ac.article_id,
            ac.chunk_index,
            ac.content AS chunk_content,
            a.title,
            a.total_chunks,
            a.status,
            (ac.embedding <=> :vector) AS distance
       FROM article_chunks ac
       JOIN articles a ON a.id = ac.article_id
       WHERE a.status = 'completed'
       ORDER BY ac.embedding <=> :vector2
       LIMIT :limit
       ", ["vector" =>$queryStr, "vector2" => $queryStr, "limit"=> $top * 3 ]);


            $seen = [];
            $results = [];
    
            foreach ($rows as $row) {
                if (isset($seen[$row->article_id])) continue;
                    $seen[$row->article_id] = true;
                    $results[] = [
                        'article_id' => $row->article_id,
                        'title' => $row->title,
                        'similarity' => round(1 - $row->distance, 4),
                        'matched_chunk' => [
                                'index'   => $row->chunk_index,
                                'preview' => substr($row->chunk_content, 0, 300) . '...',
                            ],
                    ];
    
                    if (count($results) >= $top) break;
            }
            return response()->json(['method' => 'Vector Search (pgvector)', 'query' => $query, 'results' => $results]);
   }

   public function status($id)
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

   private function dispatchProcessContentJob(
       string $content,
       string $title,
       ?string $fileType,
       ?string $fileName

   )
   {
        $article = Article::create([
            'title' => $title,
            'body' => $content,
            'file_type' => $fileType,
            'file_name' => $fileName,
            'status' => 'pending',
            'processing_started_at' => now(),
        ]);

        // Dispatch the job to process the article content
        ProcessArticleJob::dispatch($article)->onQueue('embeddings');

        return response()->json([
            'message'    => 'Received! Processing in background...',
            'article_id' => $article->id,
            'status'     => 'pending',
            'poll_url'   => "/api/articles/{$article->id}/status",
        ], 202);
   }
}
