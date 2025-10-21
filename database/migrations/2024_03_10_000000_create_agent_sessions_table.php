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
        $tableName = config('vizra-adk.tables.agent_sessions', 'agent_sessions');

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->index();
            $table->string('user_id', 255)->nullable()->index()->comment('Optional link to users table or custom identifier');
            $table->foreignId('agent_memory_id')->nullable()->index()->comment('Link to agent memory');
            $table->string('agent_name')->index();
            $table->json('state_data')->nullable();
            $table->timestamps();

            // Composite unique constraint to allow multiple agents per session
            $table->unique(['session_id', 'agent_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('vizra-adk.tables.agent_sessions', 'agent_sessions');
        Schema::dropIfExists($tableName);
    }
};
