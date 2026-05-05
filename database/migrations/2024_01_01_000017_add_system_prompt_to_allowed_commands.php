<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('allowed_commands', function (Blueprint $table) {
            $table->text('system_prompt')->nullable()->after('description')
                ->comment('Additional system prompt injected for this specific command. Appended after the global prompt.');
        });
    }

    public function down(): void
    {
        Schema::table('allowed_commands', function (Blueprint $table) {
            $table->dropColumn('system_prompt');
        });
    }
};
