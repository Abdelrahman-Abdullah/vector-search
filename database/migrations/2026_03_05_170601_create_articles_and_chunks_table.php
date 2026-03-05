<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // install the pgvector extension for Postgres to enable vector search capabilities
        // This adds the `vector` type and similarity operators (<=> <#> <+>)
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        // Articles table — stores the original content + file metadata
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('body')->nullable();       // raw text content
            $table->string('source_file')->nullable();  // original uploaded filename
            $table->string('file_type')->nullable();    // pdf, txt, docx...
            $table->integer('total_chunks')->default(0);
            $table->timestamps();
        });

        // Chunks table — one row per chunk of an article
        // Each chunk has its OWN embedding vector
        Schema::create('article_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->onDelete('cascade');
            $table->integer('chunk_index');              // order of the chunk in the article
            $table->text('chunk_text');                 // the actual text of the chunk
            $table->vector('embedding', 1536);         // vector embedding (1536 dimensions for OpenAI's models)
            $table->timestamps();   
        });

        DB::statement('
            CREATE INDEX article_chunks_embedding_hnsw_idx
            ON article_chunks
            USING hnsw (embedding vector_cosine_ops)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
        Schema::dropIfExists('article_chunks');
    }
};
