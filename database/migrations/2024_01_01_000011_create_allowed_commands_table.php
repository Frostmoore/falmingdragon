<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allowed_commands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('description');
            $table->string('category', 50)->default('general');
            $table->enum('execution_mode', ['sync', 'async', 'auto'])->default('auto');
            $table->integer('timeout_seconds')->default(300);
            $table->json('tools_allowed')->nullable();
            $table->string('llm_provider_override', 50)->nullable();
            $table->string('llm_model_override', 100)->nullable();
            $table->string('skill_required', 100)->nullable();
            $table->boolean('is_dangerous')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allowed_commands');
    }
};
