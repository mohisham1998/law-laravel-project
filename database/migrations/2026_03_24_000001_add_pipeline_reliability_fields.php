<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add pipeline reliability fields to legal_cases table
        Schema::table('cases', function (Blueprint $table) {
            $table->timestamp('pipeline_started_at')->nullable()->after('completed_at');
            $table->timestamp('halted_at')->nullable()->after('pipeline_started_at');
            $table->unsignedInteger('halted_at_agent')->nullable()->after('halted_at');
            $table->string('halt_reason')->nullable()->after('halted_at_agent');
            $table->unsignedInteger('retry_budget_max')->default(5)->after('halt_reason');
            $table->unsignedInteger('retry_budget_used')->default(0)->after('retry_budget_max');
        });

        // Add confidence scoring fields to agent_executions table
        Schema::table('agent_executions', function (Blueprint $table) {
            $table->float('confidence_score')->nullable()->after('correction_details');
            $table->boolean('below_threshold')->default(false)->after('confidence_score');
            $table->boolean('self_correction_exhausted')->default(false)->after('below_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn([
                'pipeline_started_at',
                'halted_at',
                'halted_at_agent',
                'halt_reason',
                'retry_budget_max',
                'retry_budget_used',
            ]);
        });

        Schema::table('agent_executions', function (Blueprint $table) {
            $table->dropColumn([
                'confidence_score',
                'below_threshold',
                'self_correction_exhausted',
            ]);
        });
    }
};