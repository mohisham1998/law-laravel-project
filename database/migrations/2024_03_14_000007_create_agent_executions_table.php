<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_executions', function (Blueprint $table) {
            $table->id();
            $table->uuid('case_id');
            $table->foreign('case_id')->references('id')->on('cases')->cascadeOnDelete();
            $table->tinyInteger('agent_number');
            $table->string('agent_name');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->decimal('cost_usd', 10, 4)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('api_latency_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['case_id', 'agent_number']);
            $table->index('status');
            $table->index(['case_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_executions');
    }
};
