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
        Schema::create('agent_prompt_usage', function (Blueprint $table) {
            $table->id();
            $table->string('agent_name')->index();
            $table->string('version')->index();
            $table->string('session_id')->index();
            $table->json('evaluation_results')->nullable();
            $table->timestamps();

            // Index for analytics queries
            $table->index(['agent_name', 'version', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_prompt_usage');
    }
};
