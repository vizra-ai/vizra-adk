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
        $sessionsTableName = config('vizra-adk.tables.agent_sessions', 'agent_sessions');
        $messagesTableName = config('vizra-adk.tables.agent_messages', 'agent_messages');

        Schema::create($messagesTableName, function (Blueprint $table) use ($sessionsTableName) {
            $table->id();
            $table->foreignId('agent_session_id')->constrained($sessionsTableName)->onDelete('cascade');
            $table->string('role'); // e.g., user, assistant, tool_call, tool_result
            $table->text('content'); // Using text for flexibility, could be JSON string for tool calls/results
            $table->string('tool_name')->nullable();
            $table->index('agent_session_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $messagesTableName = config('vizra-adk.tables.agent_messages', 'agent_messages');
        Schema::dropIfExists($messagesTableName);
    }
};
