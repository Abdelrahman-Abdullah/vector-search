<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    protected $fillable = [
        'title',
        'body',
        'source_file',
        'file_type',
        'total_chunks',
        'status',
        'error_message',
        'processing_started_at',
        'processing_completed_at',
    ];

    protected $casts = [
        'processing_started_at'   => 'datetime',
        'processing_completed_at' => 'datetime',
    ];

    public function chunks()
    {
        return $this->hasMany(ArticleChunk::class)->orderBy('chunk_index');
    }
}
