<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tools', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('display_name', 255);
            $table->text('description');
            $table->enum('type', ['builtin', 'script', 'api', 'composite'])->default('builtin');
            $table->string('handler_class', 255)->nullable();
            $table->json('config')->nullable();
            $table->enum('risk_level', ['safe', 'moderate', 'dangerous'])->default('safe');
            $table->boolean('requires_confirmation')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tools');
    }
};
