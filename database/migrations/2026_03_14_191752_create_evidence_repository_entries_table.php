<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('evidence_repository_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('case_id');
            $table->foreignId('document_id')->constrained('case_documents')->cascadeOnDelete();
            $table->string('evidence_type', 100);
            $table->decimal('relevance_score', 3, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('case_id')->references('id')->on('cases')->cascadeOnDelete();

            $table->unique(['case_id', 'document_id']);

            $table->index('evidence_type');
            $table->index('relevance_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evidence_repository_entries');
    }
};
