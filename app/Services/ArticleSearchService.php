<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ArticleSearchService
{
    public function __construct(private EmbeddingService $embedder)
    {
    }

    /**
     * Search best-matching article per document using pgvector cosine distance.
     */
    public function searchAcrossArticles(string $query, int $top = 5): array
    {
        $queryVector = $this->embedder->embed($query);
        $queryStr = '[' . implode(',', $queryVector) . ']';

        // Pull extra rows then deduplicate by article_id to keep top N unique articles.
        $rows = DB::select(
            "
            SELECT
                ac.article_id,
                ac.chunk_index,
                ac.content AS chunk_content,
                a.title,
                (ac.embedding <=> :vector) AS distance
            FROM article_chunks ac
            JOIN articles a ON a.id = ac.article_id
            WHERE a.status = 'completed'
            ORDER BY ac.embedding <=> :vector2
            LIMIT :limit
            ",
            ['vector' => $queryStr, 'vector2' => $queryStr, 'limit' => $top * 3]
        );

        $seen = [];
        $results = [];

        foreach ($rows as $row) {
            if (isset($seen[$row->article_id])) {
                continue;
            }

            $seen[$row->article_id] = true;
            $results[] = [
                'article_id' => $row->article_id,
                'title' => $row->title,
                'similarity' => round(1 - $row->distance, 4),
                'matched_chunk' => [
                    'index' => $row->chunk_index,
                    'preview' => mb_substr($row->chunk_content, 0, 300) . '...',
                ],
            ];

            if (count($results) >= $top) {
                break;
            }
        }

        return $results;
    }

    public function searchWithinArticle(int $articleId, string $query, int $top = 10): array
    {
        $queryVector = $this->embedder->embed($query);
        $queryStr = '[' . implode(',', $queryVector) . ']';

        $rows = DB::select(
            "
            SELECT
                ac.chunk_index,
                ac.content,
                (ac.embedding <=> :vector) AS distance
            FROM article_chunks ac
            WHERE ac.article_id = :articleId
            ORDER BY ac.embedding <=> :vector2
            LIMIT :limit
            ",
            ['vector' => $queryStr, 'vector2' => $queryStr, 'articleId' => $articleId, 'limit' => $top]
        );

        return array_map(
            fn ($row) => [
                'chunk_index' => $row->chunk_index,
                'content' => $row->content,
                'similarity' => round(1 - $row->distance, 4),
            ],
            $rows
        );
    }
}
