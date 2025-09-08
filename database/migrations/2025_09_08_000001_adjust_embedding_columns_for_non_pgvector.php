<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only relevant when primary DB is PostgreSQL
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $table = config('vizra-adk.tables.agent_vector_memories', 'agent_vector_memories');

        // Make vector column nullable so non-pgvector drivers don't fail inserts
        try {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN embedding DROP NOT NULL");
        } catch (\Throwable $e) {
            // Ignore if already nullable or column missing
        }

        // Ensure JSON fallback column exists for portability
        try {
            if (! Schema::hasColumn($table, 'embedding_vector')) {
                Schema::table($table, function ($table) {
                    $table->json('embedding_vector')->nullable();
                });
            }
        } catch (\Throwable $e) {
            // Ignore on failure; package logic avoids requiring this column for pgsql
        }
    }

    public function down(): void
    {
        // No-op: we won't revert nullability or drop extra column to avoid data loss
    }
};

