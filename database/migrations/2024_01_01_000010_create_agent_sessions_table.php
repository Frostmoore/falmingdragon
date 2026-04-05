<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_sessions', function (Blueprint $table) {
            $table->id();
            $table->char('session_uuid', 36)->unique();
            $table->bigInteger('telegram_message_id')->nullable();
            $table->string('command', 255);
            $table->text('raw_input');
            $table->enum('status', ['queued', 'running', 'completed', 'failed', 'timeout', 'cancelled'])->default('queued');
            $table->enum('execution_mode', ['sync', 'async'])->default('sync');
            $table->integer('agent_pid')->nullable();
            $table->string('queue_job_id', 255)->nullable();
            $table->json('tools_granted')->nullable();
            $table->string('llm_provider', 50)->nullable();
            $table->string('llm_model', 100)->nullable();
            $table->integer('tokens_input')->default(0);
            $table->integer('tokens_output')->default(0);
            $table->text('result_summary')->nullable();
            $table->longText('result_full')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('timeout_seconds')->default(300);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_sessions');
    }
};
