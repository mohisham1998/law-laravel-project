<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_laws', function (Blueprint $table) {
            $table->id();
            $table->uuid('case_id');
            $table->foreignId('required_law_id')->nullable()->constrained('required_laws')->nullOnDelete();
            $table->string('law_name', 500);
            $table->string('filename');
            $table->string('file_path', 500);
            $table->unsignedBigInteger('file_size');
            $table->string('encoding', 50)->default('UTF-8');
            $table->timestamps();

            $table->foreign('case_id')->references('id')->on('cases')->cascadeOnDelete();
            $table->index('case_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_laws');
    }
};
