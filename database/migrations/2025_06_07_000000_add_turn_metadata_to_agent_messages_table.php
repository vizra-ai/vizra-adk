<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $messagesTableName = config('vizra-adk.tables.agent_messages', 'agent_messages');

        Schema::table($messagesTableName, function (Blueprint $table) use ($messagesTableName) {
            $table->uuid('turn_uuid')->nullable()->after('agent_session_id');
            $table->unsignedBigInteger('user_message_id')->nullable()->after('turn_uuid');
            $table->unsignedSmallInteger('variant_index')->default(0)->after('user_message_id');

            $table->index(['agent_session_id', 'turn_uuid']);
            $table->index(['user_message_id', 'variant_index']);
            $table->index('turn_uuid');

            $table->foreign('user_message_id')
                ->references('id')
                ->on($messagesTableName)
                ->nullOnDelete();
        });

        // Backfill existing rows with sequential turn metadata per session
        DB::transaction(function () use ($messagesTableName) {
            $sessionColumn = 'agent_session_id';

            $sessions = DB::table($messagesTableName)
                ->select($sessionColumn)
                ->distinct()
                ->orderBy($sessionColumn)
                ->pluck($sessionColumn);

            foreach ($sessions as $sessionId) {
                $messages = DB::table($messagesTableName)
                    ->where($sessionColumn, $sessionId)
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->get();

                $currentTurnUuid = null;
                $currentUserId = null;
                $currentVariantIndex = 0;

                foreach ($messages as $message) {
                    $isUserMessage = $message->role === 'user';
                    $isAssistantMessage = $message->role === 'assistant';

                    if ($isUserMessage || $currentTurnUuid === null) {
                        $currentTurnUuid = (string) Str::uuid();
                        $currentVariantIndex = 0;
                    }

                    $update = [
                        'turn_uuid' => $currentTurnUuid,
                        'variant_index' => $currentVariantIndex,
                    ];

                    if ($isUserMessage) {
                        $currentUserId = $message->id;
                        $update['user_message_id'] = null;
                    } else {
                        $update['user_message_id'] = $currentUserId;
                    }

                    DB::table($messagesTableName)
                        ->where('id', $message->id)
                        ->update($update);

                    if ($isAssistantMessage) {
                        $currentVariantIndex++;
                    }
                }
            }
        });

    }

    public function down(): void
    {
        $messagesTableName = config('vizra-adk.tables.agent_messages', 'agent_messages');

        Schema::table($messagesTableName, function (Blueprint $table) use ($messagesTableName) {
            $table->dropForeign(['user_message_id']);
            $table->dropIndex($messagesTableName . '_agent_session_id_turn_uuid_index');
            $table->dropIndex($messagesTableName . '_user_message_id_variant_index_index');
            $table->dropIndex($messagesTableName . '_turn_uuid_index');
            $table->dropColumn(['turn_uuid', 'user_message_id', 'variant_index']);
        });
    }
};
