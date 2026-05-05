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
            $table->boolean('skip_confirmation')->default(false)->after('is_dangerous')
                ->comment('When true, skip the /confirm step even for dangerous commands.');
        });
    }

    public function down(): void
    {
        Schema::table('allowed_commands', function (Blueprint $table) {
            $table->dropColumn('skip_confirmation');
        });
    }
};
