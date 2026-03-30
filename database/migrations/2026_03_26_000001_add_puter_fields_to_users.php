<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('llm_provider')->default('openrouter')->after('selected_model');
            $table->string('puter_model')->default('gpt-5-nano')->after('llm_provider');
            $table->boolean('puter_disclosure_acknowledged')->default(false)->after('puter_model');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['llm_provider', 'puter_model', 'puter_disclosure_acknowledged']);
        });
    }
};
