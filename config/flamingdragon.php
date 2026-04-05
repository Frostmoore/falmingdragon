<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Telegram Configuration
    |--------------------------------------------------------------------------
    */
    'telegram' => [
        'bot_token'        => env('FD_TELEGRAM_BOT_TOKEN'),
        'webhook_secret'   => env('FD_TELEGRAM_WEBHOOK_SECRET'),
        // SECURITY: Only these Telegram chat IDs can issue commands.
        // Comma-separated list in .env (e.g., "82472920,123456789").
        // All other messages are silently ignored.
        'allowed_chat_ids' => array_map(
            'intval',
            array_filter(explode(',', (string) env('FD_TELEGRAM_ALLOWED_CHAT_IDS', '')))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Execution Settings
    |--------------------------------------------------------------------------
    */
    'execution' => [
        'default_timeout'        => (int) env('FD_DEFAULT_TIMEOUT', 300),   // 5 minutes
        'max_timeout'            => 3600,                                    // 1 hour absolute maximum
        'sync_threshold'         => 30,                                      // Commands < 30s estimated run sync
        'max_concurrent_agents'  => (int) env('FD_MAX_CONCURRENT_AGENTS', 3),
        'session_dir'            => storage_path('app/flamingdragon/sessions'),
        'cleanup_after_hours'    => 24,
    ],

    /*
    |--------------------------------------------------------------------------
    | Large Language Model Settings
    |--------------------------------------------------------------------------
    */
    'llm' => [
        'default_provider'   => env('FD_LLM_DEFAULT_PROVIDER', 'anthropic'),
        'default_model'      => env('FD_LLM_DEFAULT_MODEL', 'claude-sonnet-4-20250514'),
        'max_tokens'         => 4096,
        'temperature'        => 0.3,
        'system_prompt_path' => resource_path('prompts/agent_system.md'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Skills
    |--------------------------------------------------------------------------
    */
    'skills' => [
        'directory'      => base_path('skills'),
        'workspace'      => storage_path('app/flamingdragon/skill-workspace'),
        'auto_generate'  => true,
        'require_approval' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */
    'security' => [
        'allow_list_strict'                      => true,
        'dangerous_commands_require_confirmation' => true,
        'max_tool_calls_per_session'             => 50,
        'blocked_paths'                          => [
            '/etc/shadow',
            '/etc/passwd',
            '/root/.ssh',
        ],
        'allowed_base_paths' => [
            '/home/ubuntu',
            '/var/www',
            '/tmp/flamingdragon',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Heartbeat
    |--------------------------------------------------------------------------
    */
    'heartbeat' => [
        'enabled'           => true,
        'interval_minutes'  => 30,
        'tasks'             => [
            'cleanup_expired_sessions',
            'check_queue_health',
            'memory_expiration_sweep',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    | Access control is handled at Nginx level, not application level.
    */
    'dashboard' => [
        'enabled' => true,
    ],

];
