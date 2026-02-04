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
        $tableName = config('vizra-adk.tables.agent_interrupts', 'agent_interrupts');

        Schema::create($tableName, function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('session_id')->index();
            $table->string('workflow_id')->nullable()->index();
            $table->string('step_name')->nullable();
            $table->string('agent_name')->index();
            $table->string('type')->default('approval')->index(); // approval, confirmation, input, feedback
            $table->string('reason');
            $table->json('data')->nullable();
            $table->string('status')->default('pending')->index(); // pending, approved, rejected, expired, cancelled
            $table->json('modifications')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('user_response')->nullable();
            $table->string('resolved_by', 255)->nullable()->comment('User ID who resolved the interrupt');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Composite indexes for common queries
            $table->index(['status', 'expires_at']);
            $table->index(['session_id', 'status']);
            $table->index(['agent_name', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('vizra-adk.tables.agent_interrupts', 'agent_interrupts');
        Schema::dropIfExists($tableName);
    }
};
