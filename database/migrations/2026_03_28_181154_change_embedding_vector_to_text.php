<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Change embedding_vector from bytea (binary) to text (JSON-encoded floats).
     * Raw binary pack('f*', ...) produces bytes that PostgreSQL rejects as invalid UTF-8.
     * JSON text is portable and avoids all encoding issues.
     */
    public function up(): void
    {
        // Drop existing binary embeddings (they can't be converted; will be regenerated)
        DB::table('law_embeddings')->delete();

        // Change column from binary (bytea) to text
        DB::statement('ALTER TABLE law_embeddings ALTER COLUMN embedding_vector TYPE TEXT USING NULL');
    }

    public function down(): void
    {
        DB::table('law_embeddings')->delete();
        DB::statement('ALTER TABLE law_embeddings ALTER COLUMN embedding_vector TYPE BYTEA USING NULL');
    }
};
