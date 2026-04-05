<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('agent_sessions')->cascadeOnDelete();
            $table->integer('step_number')->default(0);
            $table->enum('action_type', ['llm_call', 'tool_use', 'skill_invoke', 'decision', 'error', 'user_prompt']);
            $table->string('tool_name', 100)->nullable();
            $table->longText('input_data')->nullable();
            $table->longText('output_data')->nullable();
            $table->integer('tokens_used')->default(0);
            $table->integer('duration_ms')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_logs');
    }
};
