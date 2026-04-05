<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memory', function (Blueprint $table) {
            $table->id();
            $table->string('namespace', 100)->default('general');
            $table->string('key', 255);
            $table->longText('value');
            $table->enum('memory_type', ['fact', 'preference', 'context', 'instruction'])->default('fact');
            $table->string('source', 255)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['namespace', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memory');
    }
};
