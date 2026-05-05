<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tools', function (Blueprint $table) {
            // Env var names this tool requires (e.g. ["GOOGLE_CLIENT_ID","GOOGLE_CLIENT_SECRET"])
            $table->json('config_keys')->nullable()->after('config');
            // JSON schema of the tool's input parameters
            $table->json('input_schema')->nullable()->after('config_keys');
        });

        Schema::table('skills', function (Blueprint $table) {
            // Parsed from SKILL.md frontmatter
            $table->json('env_required')->nullable()->after('dependencies');
            $table->json('tools_required')->nullable()->after('env_required');
        });
    }

    public function down(): void
    {
        Schema::table('tools', function (Blueprint $table) {
            $table->dropColumn(['config_keys', 'input_schema']);
        });
        Schema::table('skills', function (Blueprint $table) {
            $table->dropColumn(['env_required', 'tools_required']);
        });
    }
};
