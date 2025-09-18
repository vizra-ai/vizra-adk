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

        if (! Schema::hasColumn($messagesTableName, 'feedback')) {
            Schema::table($messagesTableName, function (Blueprint $table) {
                $table->enum('feedback', ['like', 'dislike'])->nullable()->after('tool_name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $messagesTableName = config('vizra-adk.tables.agent_messages', 'agent_messages');

        if (Schema::hasColumn($messagesTableName, 'feedback')) {
            Schema::table($messagesTableName, function (Blueprint $table) {
                $table->dropColumn('feedback');
            });
        }
    }
};
