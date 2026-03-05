<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleChunk extends Model
{
    protected $table = 'article_chunks';

    protected $fillable = [
        'article_id',
        'chunk_index',
        'content',
        'embedding',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    // Convert PHP array → pgvector string '[0.1,-0.2,...]' when saving
    public function setEmbeddingAttribute(array|string|null $value): void
    {
        if (is_array($value)) {
            $this->attributes['embedding'] = '[' . implode(',', $value) . ']';
        } else {
            $this->attributes['embedding'] = $value;
        }
    }

    // Convert pgvector string '[0.1,-0.2,...]' → PHP float array when reading
    public function getEmbeddingAttribute(?string $value): ?array
    {
        if ($value === null) return null;
        return array_map('floatval', explode(',', trim($value, '[]')));
    }

}
