<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tool;
use Illuminate\Database\Seeder;

class DefaultToolsSeeder extends Seeder
{
    public function run(): void
    {
        $tools = [
            [
                'name'                  => 'bash',
                'display_name'          => 'Bash Shell',
                'description'           => 'Execute a shell command with path restrictions enforced.',
                'type'                  => 'builtin',
                'handler_class'         => 'App\\Services\\Tools\\BashTool',
                'risk_level'            => 'dangerous',
                'requires_confirmation' => true,
            ],
            [
                'name'                  => 'file_read',
                'display_name'          => 'File Read',
                'description'           => 'Read the contents of a file at an allowed path.',
                'type'                  => 'builtin',
                'handler_class'         => 'App\\Services\\Tools\\FileReadTool',
                'risk_level'            => 'moderate',
                'requires_confirmation' => false,
            ],
            [
                'name'                  => 'file_write',
                'display_name'          => 'File Write',
                'description'           => 'Write or create a file at an allowed path.',
                'type'                  => 'builtin',
                'handler_class'         => 'App\\Services\\Tools\\FileWriteTool',
                'risk_level'            => 'moderate',
                'requires_confirmation' => false,
            ],
            [
                'name'                  => 'file_list',
                'display_name'          => 'File List',
                'description'           => 'List the contents of a directory.',
                'type'                  => 'builtin',
                'handler_class'         => 'App\\Services\\Tools\\FileListTool',
                'risk_level'            => 'safe',
                'requires_confirmation' => false,
            ],
            [
                'name'                  => 'http_get',
                'display_name'          => 'HTTP GET',
                'description'           => 'Perform an HTTP GET request to a URL.',
                'type'                  => 'builtin',
                'handler_class'         => 'App\\Services\\Tools\\HttpGetTool',
                'risk_level'            => 'moderate',
                'requires_confirmation' => false,
            ],
            [
                'name'                  => 'http_post',
                'display_name'          => 'HTTP POST',
                'description'           => 'Perform an HTTP POST request to a URL with a payload.',
                'type'                  => 'builtin',
                'handler_class'         => 'App\\Services\\Tools\\HttpPostTool',
                'risk_level'            => 'moderate',
                'requires_confirmation' => false,
            ],
            [
                'name'                  => 'memory_read',
                'display_name'          => 'Memory Read',
                'description'           => 'Read entries from the persistent memory store.',
                'type'                  => 'builtin',
                'handler_class'         => 'App\\Services\\Tools\\MemoryReadTool',
                'risk_level'            => 'safe',
                'requires_confirmation' => false,
            ],
            [
                'name'                  => 'memory_write',
                'display_name'          => 'Memory Write',
                'description'           => 'Write an entry to the persistent memory store.',
                'type'                  => 'builtin',
                'handler_class'         => 'App\\Services\\Tools\\MemoryWriteTool',
                'risk_level'            => 'safe',
                'requires_confirmation' => false,
            ],
            [
                'name'                  => 'telegram_send',
                'display_name'          => 'Telegram Send',
                'description'           => 'Send a message to the authorized Telegram user.',
                'type'                  => 'builtin',
                'handler_class'         => 'App\\Services\\Tools\\TelegramSendTool',
                'risk_level'            => 'safe',
                'requires_confirmation' => false,
            ],
            [
                'name'                  => 'skill_read',
                'display_name'          => 'Skill Read',
                'description'           => 'Read the SKILL.md file of an installed skill.',
                'type'                  => 'builtin',
                'handler_class'         => 'App\\Services\\Tools\\SkillReadTool',
                'risk_level'            => 'safe',
                'requires_confirmation' => false,
            ],
            [
                'name'                  => 'process_status',
                'display_name'          => 'Process Status',
                'description'           => 'Check the status of running processes on the server.',
                'type'                  => 'builtin',
                'handler_class'         => 'App\\Services\\Tools\\ProcessStatusTool',
                'risk_level'            => 'safe',
                'requires_confirmation' => false,
            ],
            [
                'name'                  => 'laravel_artisan',
                'display_name'          => 'Laravel Artisan',
                'description'           => 'Run a Laravel Artisan command.',
                'type'                  => 'builtin',
                'handler_class'         => 'App\\Services\\Tools\\LaravelArtisanTool',
                'risk_level'            => 'dangerous',
                'requires_confirmation' => true,
            ],
            [
                'name'                  => 'git_operation',
                'display_name'          => 'Git Operation',
                'description'           => 'Perform git operations: pull, status, log (no force push).',
                'type'                  => 'builtin',
                'handler_class'         => 'App\\Services\\Tools\\GitOperationTool',
                'risk_level'            => 'moderate',
                'requires_confirmation' => false,
            ],
            [
                'name'                  => 'composer_operation',
                'display_name'          => 'Composer Operation',
                'description'           => 'Run composer install or update.',
                'type'                  => 'builtin',
                'handler_class'         => 'App\\Services\\Tools\\ComposerOperationTool',
                'risk_level'            => 'moderate',
                'requires_confirmation' => false,
            ],
            [
                'name'                  => 'npm_operation',
                'display_name'          => 'NPM Operation',
                'description'           => 'Run npm install or npm run scripts.',
                'type'                  => 'builtin',
                'handler_class'         => 'App\\Services\\Tools\\NpmOperationTool',
                'risk_level'            => 'moderate',
                'requires_confirmation' => false,
            ],
        ];

        foreach ($tools as $tool) {
            Tool::updateOrCreate(
                ['name' => $tool['name']],
                $tool
            );
        }
    }
}
