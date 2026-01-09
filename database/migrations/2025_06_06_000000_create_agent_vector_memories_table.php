<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Get the database connection that should be used by the migration.
     * Uses the configured pgvector connection if set.
     */
    public function getConnection()
    {
        return config('vizra-adk.vector_memory.drivers.pgvector.connection', null);
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableName = config('vizra-adk.tables.agent_vector_memories', 'agent_vector_memories');
        $connection = DB::connection($this->getConnection());

        // First, check if we're using PostgreSQL and if pgvector extension is available
        if ($connection->getDriverName() === 'pgsql') {
            try {
                $connection->statement('CREATE EXTENSION IF NOT EXISTS vector');
            } catch (\Exception $e) {
                // In test environments, pgvector might not be available, that's ok
                if (! app()->environment('testing')) {
                    throw $e;
                }
            }
        }

        Schema::connection($this->getConnection())->create($tableName, function (Blueprint $table) use ($connection) {
            $table->ulid('id')->primary();
            $table->string('agent_name')->index();
            $table->string('namespace')->default('default')->index(); // For organizing different memory types
            $table->text('content'); // The original text content
            $table->text('metadata')->nullable(); // JSON metadata about the content
            $table->string('source')->nullable(); // Source reference (file, url, etc.)
            $table->string('source_id')->nullable(); // External reference ID
            $table->integer('chunk_index')->default(0); // For tracking chunks from same source
            $table->string('embedding_provider'); // Which provider generated the embedding
            $table->string('embedding_model'); // Which model generated the embedding
            $table->integer('embedding_dimensions'); // Vector dimensions

            // This will be the vector column for pgvector, or JSON for other databases
            if ($connection->getDriverName() === 'pgsql') {
                // For PostgreSQL with pgvector extension
                $table->addColumn('vector', 'embedding', ['dimensions' => 1536]); // Default OpenAI dimensions
            } else {
                // For other databases, store as JSON (less efficient but compatible)
                $table->json('embedding_vector');
            }

            $table->float('embedding_norm')->nullable(); // Vector magnitude for optimization
            $table->string('content_hash', 64)->index(); // SHA-256 hash for deduplication
            $table->integer('token_count')->nullable(); // Estimated token count
            $table->timestamps();

            // Indexes for efficient querying
            $table->index(['agent_name', 'namespace']);
            $table->index(['source', 'source_id']);
            $table->index(['embedding_provider', 'embedding_model']);
            $table->unique(['agent_name', 'content_hash']); // Prevent duplicate content per agent
        });

        // Create vector index for PostgreSQL
        if ($connection->getDriverName() === 'pgsql' && ! app()->environment('testing')) {
            try {
                // Create IVFFlat index for fast approximate similarity search
                $connection->statement('CREATE INDEX agent_vector_memories_embedding_idx ON '.$tableName.' USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');

                // You can also create HNSW index (requires pgvector 0.5.0+)
                // $connection->statement('CREATE INDEX agent_vector_memories_embedding_hnsw_idx ON ' . $tableName . ' USING hnsw (embedding vector_cosine_ops)');
            } catch (\Exception $e) {
                // In case pgvector is not properly installed, skip vector indexes
                // This will still allow the table to be created
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('vizra-adk.tables.agent_vector_memories', 'agent_vector_memories');
        Schema::connection($this->getConnection())->dropIfExists($tableName);
    }
};
