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
        $messagesTableName = config('vizra-adk.tables.agent_messages', 'agent_messages');

        Schema::table($messagesTableName, function (Blueprint $table) {
            $table->longText('content')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $messagesTableName = config('vizra-adk.tables.agent_messages', 'agent_messages');

        Schema::table($messagesTableName, function (Blueprint $table) {
            $table->text('content')->change();
        });
    }
};
