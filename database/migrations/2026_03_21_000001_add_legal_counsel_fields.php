<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->tinyInteger('resume_from_agent')->nullable()->after('progress_percentage');
        });

        Schema::table('case_outputs', function (Blueprint $table) {
            $table->string('output_type', 20)->default('primary')->after('file_size');
        });

        Schema::table('agent_executions', function (Blueprint $table) {
            $table->tinyInteger('corrections_count')->default(0)->after('retry_count');
            $table->json('correction_details')->nullable()->after('corrections_count');
        });

        Schema::table('required_laws', function (Blueprint $table) {
            $table->unsignedBigInteger('law_registry_id')->nullable()->after('is_uploaded');
            $table->string('subject_area')->nullable()->after('law_registry_id');

            $table->foreign('law_registry_id')
                ->references('id')
                ->on('law_registry')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('required_laws', function (Blueprint $table) {
            $table->dropForeign(['law_registry_id']);
            $table->dropColumn(['law_registry_id', 'subject_area']);
        });

        Schema::table('agent_executions', function (Blueprint $table) {
            $table->dropColumn(['corrections_count', 'correction_details']);
        });

        Schema::table('case_outputs', function (Blueprint $table) {
            $table->dropColumn('output_type');
        });

        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn('resume_from_agent');
        });
    }
};
