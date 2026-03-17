<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('case_id')->unique()->constrained('cases')->cascadeOnDelete();
            $table->integer('total_duration_seconds')->default(0);
            $table->integer('total_tokens')->default(0);
            $table->integer('statutes_matched')->default(0);
            $table->decimal('average_confidence', 5, 2)->default(0);
            $table->integer('corrections_count')->default(0);
            $table->json('items_for_review')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_metrics');
    }
};
