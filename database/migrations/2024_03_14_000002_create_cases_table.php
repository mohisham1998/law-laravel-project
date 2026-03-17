<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 500);
            $table->text('intake_text');
            $table->string('status', 50)->default('phase1_pending');
            $table->tinyInteger('phase')->default(1);
            $table->tinyInteger('current_agent')->nullable();
            $table->tinyInteger('progress_percentage')->default(0);
            $table->string('skill_version', 50);
            $table->string('skill_hash', 64);
            $table->string('model_used');
            $table->unsignedBigInteger('total_tokens')->default(0);
            $table->decimal('total_cost_usd', 10, 4)->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('status');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cases');
    }
};
