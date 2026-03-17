<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Main law registry table
        Schema::create('law_registry', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "نظام الإثبات"
            $table->text('description')->nullable();
            $table->string('category')->nullable(); // civil, criminal, commercial, etc.
            $table->string('effective_year')->nullable(); // e.g., "1443"
            $table->string('status')->default('active'); // active, superseded, draft
            $table->json('supersedes')->nullable(); // Array of law IDs this supersedes
            $table->json('metadata')->nullable(); // Additional metadata
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('name');
            $table->index('category');
            $table->index('status');
        });

        // Law files (multiple files per law)
        Schema::create('law_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('law_registry_id')->constrained('law_registry')->onDelete('cascade');
            $table->string('filename');
            $table->string('file_path');
            $table->bigInteger('file_size');
            $table->string('mime_type')->nullable();
            $table->string('encoding')->default('UTF-8');
            $table->integer('total_articles')->default(0);
            $table->boolean('is_processed')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            $table->index('law_registry_id');
            $table->index('is_processed');
        });

        // Parsed articles from laws (for indexing)
        Schema::create('law_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('law_registry_id')->constrained('law_registry')->onDelete('cascade');
            $table->foreignId('law_file_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('article_number'); // e.g., "11", "الحادية عشرة"
            $table->text('article_text');
            $table->text('article_context')->nullable(); // Surrounding context
            $table->integer('start_line')->nullable();
            $table->integer('end_line')->nullable();
            $table->json('keywords')->nullable(); // Extracted keywords
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['law_registry_id', 'article_number']);
            if (DB::connection()->getDriverName() !== 'sqlite') {
                $table->fullText(['article_text', 'article_context']);
            }
        });

        // Vector embeddings for semantic search
        Schema::create('law_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('law_article_id')->constrained()->onDelete('cascade');
            $table->string('embedding_model')->default('text-embedding-3-small'); // OpenAI model
            $table->integer('embedding_dimensions')->default(1536);
            $table->binary('embedding_vector'); // Store as binary blob
            $table->decimal('norm', 10, 6)->nullable(); // Vector norm for normalization
            $table->timestamps();
            
            $table->index('law_article_id');
            $table->index('embedding_model');
        });

        // Search cache for performance
        Schema::create('law_search_cache', function (Blueprint $table) {
            $table->id();
            $table->string('query_hash', 64)->unique(); // SHA256 of query
            $table->text('query_text');
            $table->json('result_article_ids'); // Array of article IDs
            $table->json('result_scores')->nullable(); // Similarity scores
            $table->integer('hit_count')->default(1);
            $table->timestamp('last_accessed_at');
            $table->timestamps();
            
            $table->index('query_hash');
            $table->index('last_accessed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('law_search_cache');
        Schema::dropIfExists('law_embeddings');
        Schema::dropIfExists('law_articles');
        Schema::dropIfExists('law_files');
        Schema::dropIfExists('law_registry');
    }
};
