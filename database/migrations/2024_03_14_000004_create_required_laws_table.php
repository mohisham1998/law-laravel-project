<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('required_laws', function (Blueprint $table) {
            $table->id();
            $table->uuid('case_id');
            $table->string('law_name', 500);
            $table->text('reason');
            $table->boolean('is_uploaded')->default(false);
            $table->timestamps();

            $table->foreign('case_id')->references('id')->on('cases')->cascadeOnDelete();
            $table->index('case_id');
            $table->index(['case_id', 'is_uploaded']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('required_laws');
    }
};
