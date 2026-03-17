<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->string('last_failed_phase', 50)->nullable()->after('progress_percentage');
            $table->text('last_error_message')->nullable()->after('last_failed_phase');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn(['last_failed_phase', 'last_error_message']);
        });
    }
};
