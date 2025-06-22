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
        Schema::create('agent_prompt_versions', function (Blueprint $table) {
            $table->id();
            $table->string('agent_name')->index();
            $table->string('version')->index();
            $table->text('instructions');
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(false);
            $table->json('performance_metrics')->nullable();
            $table->timestamps();

            // Ensure only one active version per agent
            $table->unique(['agent_name', 'is_active']);
            $table->unique(['agent_name', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_prompt_versions');
    }
};
