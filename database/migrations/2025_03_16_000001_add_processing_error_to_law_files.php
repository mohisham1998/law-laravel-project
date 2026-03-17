<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('law_files', function (Blueprint $table) {
            $table->text('processing_error')->nullable()->after('processed_at');
        });
    }

    public function down(): void
    {
        Schema::table('law_files', function (Blueprint $table) {
            $table->dropColumn('processing_error');
        });
    }
};
