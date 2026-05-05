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
                'description'    => 'Free-form conversation, questions, calculations, and general requests. Has access to weather, web search, and memory.',
                'category'       => 'personal',
                'execution_mode' => 'sync',
                'timeout_seconds' => 60,
                'tools_allowed'  => ['memory_read', 'memory_write', 'weather', 'web_search', 'generate_qr', 'summarize_url'],
                'is_dangerous'   => false,
            ],
            [
                'name'           => 'generateskill',
                'description'    => 'Interactively generate and install a new skill (SKILL.md) using Claude Opus.',
                'category'       => 'dev',
                'execution_mode' => 'sync',
                'timeout_seconds' => 120,
                'tools_allowed'  => [],
                'is_dangerous'   => false,
            ],
            [
                'name'           => 'generatetool',
                'description'    => 'Interactively generate PHP code for a new tool using Claude Opus.',
                'category'       => 'dev',
                'execution_mode' => 'sync',
                'timeout_seconds' => 120,
                'tools_allowed'  => [],
                'is_dangerous'   => false,
            ],
            [
                'name'           => 'generateskilltool',
                'description'    => 'Interactively generate both a skill and a tool together using Claude Opus.',
                'category'       => 'dev',
                'execution_mode' => 'sync',
                'timeout_seconds' => 120,
                'tools_allowed'  => [],
                'is_dangerous'   => false,
            ],

            // ── Gmail ─────────────────────────────────────────────────────────

            [
                'name'           => 'gmail',
                'description'    => 'Read, search, and send emails via Gmail. Understands natural language requests like "show my unread emails" or "send a reply to Marco".',
                'category'       => 'personal',
                'execution_mode' => 'sync',
                'timeout_seconds' => 60,
                'tools_allowed'  => ['gmail_list', 'gmail_read', 'gmail_send', 'gmail_search', 'gmail_mark_read', 'gmail_trash', 'memory_read'],
                'is_dangerous'   => false,
                'skill_required' => 'gmail',
            ],

            // ── Calendar ──────────────────────────────────────────────────────

            [
                'name'           => 'calendar',
                'description'    => 'View, create, and delete Google Calendar events.',
                'category'       => 'personal',
                'execution_mode' => 'sync',
                'timeout_seconds' => 60,
                'tools_allowed'  => ['google_calendar_list', 'google_calendar_create', 'google_calendar_delete'],
                'is_dangerous'   => false,
                'skill_required' => 'google-calendar',
            ],

            // ── Todos ─────────────────────────────────────────────────────────

            [
                'name'           => 'todo',
                'description'    => 'Manage your personal to-do lists: add, list, complete, and delete tasks.',
                'category'       => 'personal',
                'execution_mode' => 'sync',
                'timeout_seconds' => 30,
                'tools_allowed'  => ['todo_create', 'todo_list', 'todo_complete', 'todo_delete'],
                'is_dangerous'   => false,
                'skill_required' => 'todo',
            ],

            // ── Shopping list ─────────────────────────────────────────────────

            [
                'name'           => 'shopping',
                'description'    => 'Manage grocery and shopping lists: add items, view, mark bought, clear.',
                'category'       => 'personal',
                'execution_mode' => 'sync',
                'timeout_seconds' => 30,
                'tools_allowed'  => ['shopping_add', 'shopping_items', 'shopping_bought', 'shopping_clear'],
                'is_dangerous'   => false,
                'skill_required' => 'shopping-list',
            ],

            // ── Messaging ─────────────────────────────────────────────────────

            [
                'name'           => 'whatsapp',
                'description'    => 'Send WhatsApp messages via Meta WhatsApp Business API.',
                'category'       => 'personal',
                'execution_mode' => 'sync',
                'timeout_seconds' => 30,
                'tools_allowed'  => ['whatsapp_send', 'memory_read'],
                'is_dangerous'   => false,
                'skill_required' => 'whatsapp',
            ],

            // ── Social media ──────────────────────────────────────────────────

            [
                'name'           => 'social',
                'description'    => 'Post to Facebook/Instagram and read your Facebook feed.',
                'category'       => 'personal',
                'execution_mode' => 'sync',
                'timeout_seconds' => 60,
                'tools_allowed'  => ['facebook_post', 'facebook_feed', 'instagram_post'],
                'is_dangerous'   => false,
                'skill_required' => 'social-media',
            ],

            // ── Vision ───────────────────────────────────────────────────────

            [
                'name'           => 'vision',
                'description'    => 'Analyze images sent to Telegram (describe, read text, identify objects) and generate new images with DALL-E 3.',
                'category'       => 'personal',
                'execution_mode' => 'sync',
                'timeout_seconds' => 60,
                'tools_allowed'  => ['analyze_image', 'generate_image', 'send_telegram_image'],
                'is_dangerous'   => false,
                'skill_required' => 'vision',
            ],

            // ── Audio ─────────────────────────────────────────────────────────

            [
                'name'           => 'audio',
                'description'    => 'Transcribe voice messages with Whisper, generate speech from text with OpenAI TTS, and send audio back via Telegram.',
                'category'       => 'personal',
                'execution_mode' => 'sync',
                'timeout_seconds' => 60,
                'tools_allowed'  => ['transcribe_audio', 'generate_audio', 'send_telegram_voice'],
                'is_dangerous'   => false,
                'skill_required' => 'audio',
            ],

            // ── Document generation ───────────────────────────────────────────

            [
                'name'           => 'document',
                'description'    => 'Generate QR codes, PDFs, Word documents (.docx), and Excel spreadsheets (.xlsx).',
                'category'       => 'personal',
                'execution_mode' => 'sync',
                'timeout_seconds' => 60,
                'tools_allowed'  => ['generate_qr', 'generate_pdf', 'generate_docx', 'generate_xlsx'],
                'is_dangerous'   => false,
                'skill_required' => 'document-generator',
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
