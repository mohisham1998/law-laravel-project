<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            // Encrypted Puter auth token stored per-case so it persists across all phase job dispatches.
            // Stored as TEXT (encrypted); NULL when provider is openrouter.
            $table->text('puter_token')->nullable()->after('model_used');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn('puter_token');
        });
    }
};
