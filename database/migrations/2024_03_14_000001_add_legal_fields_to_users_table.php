<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('selected_model')->nullable()->default('anthropic/claude-3.5-sonnet')->after('password');
            $table->decimal('confidence_threshold', 3, 2)->default(0.70)->after('selected_model');
            $table->unsignedBigInteger('total_tokens_consumed')->default(0)->after('confidence_threshold');
            $table->decimal('total_cost_usd', 10, 4)->default(0)->after('total_tokens_consumed');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['selected_model', 'confidence_threshold', 'total_tokens_consumed', 'total_cost_usd']);
        });
    }
};
