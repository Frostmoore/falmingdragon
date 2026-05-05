<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memory', function (Blueprint $table) {
            $table->json('embedding')->nullable()->after('value')
                ->comment('OpenAI text-embedding-3-small vector (1536 dims), stored as JSON array.');
            $table->boolean('is_important')->default(false)->after('embedding')
                ->comment('Important memories always get an embedding and are prioritised in semantic search.');
        });
    }

    public function down(): void
    {
        Schema::table('memory', function (Blueprint $table) {
            $table->dropColumn(['embedding', 'is_important']);
        });
    }
};
