<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('case_id');
            $table->foreign('case_id')->references('id')->on('cases')->cascadeOnDelete();
            $table->foreignId('agent_execution_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('agent_number');
            $table->string('error_type', 50);
            $table->text('error_details');
            $table->text('fix_applied');
            $table->text('lesson_learned')->nullable();
            $table->decimal('confidence_score', 4, 3)->nullable();
            $table->timestamps();

            $table->index(['case_id', 'error_type']);
            $table->index('agent_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_logs');
    }
};
