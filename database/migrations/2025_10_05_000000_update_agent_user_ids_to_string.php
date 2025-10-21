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
        $memoriesTableName = config('vizra-adk.tables.agent_memories', 'agent_memories');

        Schema::table($sessionsTableName, function (Blueprint $table) {
            $table->string('user_id', 255)->nullable()->comment('Optional link to users table or custom identifier')->change();
        });

        Schema::table($memoriesTableName, function (Blueprint $table) {
            $table->string('user_id', 255)->nullable()->comment('Optional link to users table or custom identifier for user-specific memories')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $sessionsTableName = config('vizra-adk.tables.agent_sessions', 'agent_sessions');
        $memoriesTableName = config('vizra-adk.tables.agent_memories', 'agent_memories');

        Schema::table($sessionsTableName, function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->comment('Optional link to users table')->change();
        });

        Schema::table($memoriesTableName, function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->comment('Optional link to users table for user-specific memories')->change();
        });
    }
};
