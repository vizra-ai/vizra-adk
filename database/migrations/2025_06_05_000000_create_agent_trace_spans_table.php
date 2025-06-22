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
        $tableName = config('vizra-adk.tables.agent_trace_spans', 'agent_trace_spans');

        Schema::create($tableName, function (Blueprint $table) {
            // Primary key using ULID for better performance and ordering
            $table->string('id', 26)->primary()->comment('ULID primary key for this span');

            // Trace hierarchy fields
            $table->string('trace_id', 26)->index()->comment('ULID identifying the entire agent run trace');
            $table->string('parent_span_id', 26)->nullable()->index()->comment('Parent span ID for hierarchy, null for root spans');
            $table->string('span_id', 26)->unique()->comment('Unique ULID for this specific span');

            // Context identification
            $table->string('session_id')->index()->comment('Session ID from AgentContext');
            $table->string('agent_name')->index()->comment('Name of the agent executing this span');

            // Span details
            $table->string('type', 50)->index()->comment('Type of operation: agent_run, llm_call, tool_call, sub_agent_delegation');
            $table->string('name')->comment('Specific name: model name, tool name, sub-agent name, etc.');

            // Input/Output data
            $table->json('input')->nullable()->comment('Input data for the operation');
            $table->json('output')->nullable()->comment('Result/output of the operation');
            $table->json('metadata')->nullable()->comment('Additional contextual information');

            // Execution status and error handling
            $table->string('status', 20)->default('running')->index()->comment('Execution status: running, success, error');
            $table->text('error_message')->nullable()->comment('Error message if status is error');

            // Timing information with microsecond precision
            $table->decimal('start_time', 16, 6)->comment('Start timestamp with microseconds');
            $table->decimal('end_time', 16, 6)->nullable()->comment('End timestamp with microseconds');
            $table->unsignedInteger('duration_ms')->nullable()->index()->comment('Duration in milliseconds');

            // Standard timestamps
            $table->timestamps();

            // Indexes for efficient querying
            $table->index(['trace_id', 'start_time'], 'trace_chronological_idx');
            $table->index(['session_id', 'start_time'], 'session_chronological_idx');
            $table->index(['agent_name', 'type', 'start_time'], 'agent_type_chronological_idx');
            $table->index(['status', 'type'], 'status_type_idx');
        });

        // Add foreign key constraint after table creation (skip in testing environment)
        if (! app()->environment('testing')) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->foreign('parent_span_id')->references('span_id')->on($tableName)->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('vizra-adk.tables.agent_trace_spans', 'agent_trace_spans');
        Schema::dropIfExists($tableName);
    }
};
