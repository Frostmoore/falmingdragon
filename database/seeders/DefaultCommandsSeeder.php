<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AllowedCommand;
use Illuminate\Database\Seeder;

class DefaultCommandsSeeder extends Seeder
{
    public function run(): void
    {
        $commands = [
            [
                'name'           => 'help',
                'description'    => 'List all available commands and their descriptions.',
                'category'       => 'system',
                'execution_mode' => 'sync',
                'timeout_seconds' => 30,
                'tools_allowed'  => [],
                'is_dangerous'   => false,
            ],
            [
                'name'           => 'status',
                'description'    => 'Show current system status including running agents and queue health.',
                'category'       => 'system',
                'execution_mode' => 'sync',
                'timeout_seconds' => 30,
                'tools_allowed'  => ['process_status'],
                'is_dangerous'   => false,
            ],
            [
                'name'           => 'deploy_site',
                'description'    => 'Deploy a website: git pull, composer install, npm build, artisan cache clear.',
                'category'       => 'server',
                'execution_mode' => 'async',
                'timeout_seconds' => 900,
                'tools_allowed'  => ['bash', 'git_operation', 'composer_operation', 'npm_operation'],
                'is_dangerous'   => true,
            ],
            [
                'name'           => 'server_status',
                'description'    => 'Report server resource usage: CPU, memory, disk, and running processes.',
                'category'       => 'server',
                'execution_mode' => 'sync',
                'timeout_seconds' => 30,
                'tools_allowed'  => ['bash', 'process_status'],
                'is_dangerous'   => false,
            ],
            [
                'name'           => 'read_file',
                'description'    => 'Read and return the contents of a file from an allowed path.',
                'category'       => 'dev',
                'execution_mode' => 'sync',
                'timeout_seconds' => 30,
                'tools_allowed'  => ['file_read'],
                'is_dangerous'   => false,
            ],
            [
                'name'           => 'write_file',
                'description'    => 'Write or create a file at an allowed path.',
                'category'       => 'dev',
                'execution_mode' => 'sync',
                'timeout_seconds' => 60,
                'tools_allowed'  => ['file_write'],
                'is_dangerous'   => false,
            ],
            [
                'name'           => 'run_artisan',
                'description'    => 'Run a Laravel Artisan command on the server.',
                'category'       => 'dev',
                'execution_mode' => 'sync',
                'timeout_seconds' => 120,
                'tools_allowed'  => ['laravel_artisan'],
                'is_dangerous'   => true,
            ],
            [
                'name'           => 'git_pull',
                'description'    => 'Pull the latest changes from the remote git repository.',
                'category'       => 'dev',
                'execution_mode' => 'sync',
                'timeout_seconds' => 120,
                'tools_allowed'  => ['git_operation'],
                'is_dangerous'   => false,
            ],
            [
                'name'           => 'search_memory',
                'description'    => 'Search stored memories for a given term.',
                'category'       => 'personal',
                'execution_mode' => 'sync',
                'timeout_seconds' => 30,
                'tools_allowed'  => ['memory_read'],
                'is_dangerous'   => false,
            ],
            [
                'name'           => 'create_skill',
                'description'    => 'Generate and install a new skill based on a description.',
                'category'       => 'dev',
                'execution_mode' => 'async',
                'timeout_seconds' => 600,
                'tools_allowed'  => ['file_write', 'file_read', 'bash'],
                'is_dangerous'   => false,
            ],
            [
                'name'           => 'morning_briefing',
                'description'    => 'Compile and send a morning briefing with today\'s summary.',
                'category'       => 'personal',
                'execution_mode' => 'async',
                'timeout_seconds' => 120,
                'tools_allowed'  => ['memory_read', 'http_get', 'telegram_send'],
                'is_dangerous'   => false,
            ],
            [
                'name'           => 'run_script',
                'description'    => 'Execute a specific script file on the server.',
                'category'       => 'dev',
                'execution_mode' => 'async',
                'timeout_seconds' => 300,
                'tools_allowed'  => ['bash', 'file_read'],
                'is_dangerous'   => true,
            ],
            [
                'name'           => 'chat',
                'description'    => 'Free-form conversation and questions. The agent answers using the LLM without executing any tools unless explicitly asked.',
                'category'       => 'personal',
                'execution_mode' => 'sync',
                'timeout_seconds' => 60,
                'tools_allowed'  => ['memory_read', 'memory_write'],
                'is_dangerous'   => false,
            ],
        ];

        foreach ($commands as $command) {
            AllowedCommand::updateOrCreate(
                ['name' => $command['name']],
                $command
            );
        }
    }
}
