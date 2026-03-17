<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_outputs', function (Blueprint $table) {
            $table->id();
            $table->uuid('case_id');
            $table->foreign('case_id')->references('id')->on('cases')->cascadeOnDelete();
            $table->tinyInteger('agent_number');
            $table->string('filename');
            $table->string('file_path', 500);
            $table->string('content_type', 20);
            $table->text('content')->nullable();
            $table->json('content_json')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->timestamps();

            $table->index(['case_id', 'agent_number']);
            $table->index(['case_id', 'filename']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_outputs');
    }
};
