<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            // JSON map of agent_number => model_id, e.g. {"4": "openai/gpt-4o", "6": "anthropic/claude-3.5-sonnet"}
            $table->json('agent_model_overrides')->nullable()->after('model_used');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn('agent_model_overrides');
        });
    }
};
