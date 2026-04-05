<?php

declare(strict_types=1);

namespace App\Services\Agent;

use App\Models\ExecutionLog;
use App\Services\Security\SessionSandbox;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Executes tools within a session sandbox with full logging.
 */
class ToolExecutor
{
    public function __construct(
        private readonly SessionSandbox $sandbox,
    ) {}

    /**
     * Execute a named tool with the given arguments.
     *
     * @param  string                $toolName
     * @param  array<string, mixed>  $arguments
     * @param  int                   $sessionId
     * @param  int                   $stepNumber
     * @return array{output: string, success: bool}
     */
    public function execute(
        string $toolName,
        array $arguments,
        int $sessionId,
        int $stepNumber,
    ): array {
        $startTime = microtime(true);

        try {
            $this->sandbox->recordToolCall();

            $output = $this->dispatch($toolName, $arguments);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $this->logStep(
                sessionId:  $sessionId,
                stepNumber: $stepNumber,
                toolName:   $toolName,
                input:      json_encode($arguments),
                output:     $output,
                durationMs: $durationMs,
            );

            return ['output' => $output, 'success' => true];
        } catch (Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $errorMessage = $e->getMessage();

            $this->logStep(
                sessionId:  $sessionId,
                stepNumber: $stepNumber,
                toolName:   $toolName,
                input:      json_encode($arguments),
                output:     "ERROR: {$errorMessage}",
                durationMs: $durationMs,
                isError:    true,
            );

            Log::warning('[ToolExecutor] Tool execution failed.', [
                'tool'  => $toolName,
                'error' => $errorMessage,
            ]);

            return ['output' => "Tool '{$toolName}' failed: {$errorMessage}", 'success' => false];
        }
    }

    /**
     * Dispatch to the appropriate built-in tool handler.
     *
     * @param  string                $toolName
     * @param  array<string, mixed>  $args
     * @return string
     * @throws RuntimeException
     */
    private function dispatch(string $toolName, array $args): string
    {
        return match($toolName) {
            'bash'               => $this->runBash($args),
            'file_read'          => $this->runFileRead($args),
            'file_write'         => $this->runFileWrite($args),
            'file_list'          => $this->runFileList($args),
            'http_get'           => $this->runHttpGet($args),
            'http_post'          => $this->runHttpPost($args),
            'memory_read'        => $this->runMemoryRead($args),
            'memory_write'       => $this->runMemoryWrite($args),
            'telegram_send'      => $this->runTelegramSend($args),
            'skill_read'         => $this->runSkillRead($args),
            'process_status'     => $this->runProcessStatus($args),
            'laravel_artisan'    => $this->runArtisan($args),
            'git_operation'      => $this->runGit($args),
            'composer_operation' => $this->runComposer($args),
            'npm_operation'      => $this->runNpm($args),
            default              => throw new RuntimeException("Unknown tool: {$toolName}"),
        };
    }

    // -------------------------------------------------------------------------
    // Tool implementations
    // -------------------------------------------------------------------------

    private function runBash(array $args): string
    {
        $command = $args['command'] ?? throw new RuntimeException('bash: missing required argument "command".');
        // Path validation not applicable for commands themselves, but outputs may reference paths
        $output = [];
        $code   = 0;
        exec($command . ' 2>&1', $output, $code);
        $result = implode("\n", $output);
        if ($code !== 0) {
            return "Exit code {$code}:\n{$result}";
        }
        return $result;
    }

    private function runFileRead(array $args): string
    {
        $path = $args['path'] ?? throw new RuntimeException('file_read: missing required argument "path".');
        $this->sandbox->validatePath($path);
        if (! file_exists($path)) {
            throw new RuntimeException("file_read: file not found at '{$path}'.");
        }
        $content = file_get_contents($path);
        return $content !== false ? $content : throw new RuntimeException("file_read: unable to read '{$path}'.");
    }

    private function runFileWrite(array $args): string
    {
        $path    = $args['path']    ?? throw new RuntimeException('file_write: missing required argument "path".');
        $content = $args['content'] ?? throw new RuntimeException('file_write: missing required argument "content".');
        $this->sandbox->validatePath(dirname($path));
        $result = file_put_contents($path, $content);
        if ($result === false) {
            throw new RuntimeException("file_write: unable to write to '{$path}'.");
        }
        return "Written {$result} bytes to '{$path}'.";
    }

    private function runFileList(array $args): string
    {
        $path = $args['path'] ?? '.';
        $this->sandbox->validatePath($path);
        if (! is_dir($path)) {
            throw new RuntimeException("file_list: '{$path}' is not a directory.");
        }
        $items = scandir($path);
        return implode("\n", array_diff($items ?: [], ['.', '..']));
    }

    private function runHttpGet(array $args): string
    {
        $url = $args['url'] ?? throw new RuntimeException('http_get: missing required argument "url".');
        $response = \Illuminate\Support\Facades\Http::timeout(30)->get($url);
        return substr($response->body(), 0, 8000);
    }

    private function runHttpPost(array $args): string
    {
        $url     = $args['url']     ?? throw new RuntimeException('http_post: missing required argument "url".');
        $payload = $args['payload'] ?? [];
        $response = \Illuminate\Support\Facades\Http::timeout(30)->post($url, $payload);
        return substr($response->body(), 0, 8000);
    }

    private function runMemoryRead(array $args): string
    {
        $key       = $args['key']       ?? null;
        $namespace = $args['namespace'] ?? 'general';

        if ($key !== null) {
            $memory = \App\Models\Memory::where('namespace', $namespace)->where('key', $key)->first();
            return $memory?->value ?? "No memory found for {$namespace}/{$key}.";
        }

        $memories = \App\Models\Memory::where('namespace', $namespace)->get();
        return $memories->map(fn ($m) => "{$m->key}: {$m->value}")->implode("\n");
    }

    private function runMemoryWrite(array $args): string
    {
        $key       = $args['key']       ?? throw new RuntimeException('memory_write: missing argument "key".');
        $value     = $args['value']     ?? throw new RuntimeException('memory_write: missing argument "value".');
        $namespace = $args['namespace'] ?? 'general';

        \App\Models\Memory::updateOrCreate(
            ['namespace' => $namespace, 'key' => $key],
            ['value' => $value, 'memory_type' => 'fact']
        );

        return "Memory stored: {$namespace}/{$key}";
    }

    private function runTelegramSend(array $args): string
    {
        $text = $args['text'] ?? throw new RuntimeException('telegram_send: missing argument "text".');
        $chatIds = config('flamingdragon.telegram.allowed_chat_ids', []);
        if (empty($chatIds)) {
            return 'telegram_send: no authorized chat IDs configured.';
        }
        $service = app(\App\Services\Telegram\TelegramService::class);
        $service->sendMessage($chatIds[0], $text);
        return 'Message sent to Telegram.';
    }

    private function runSkillRead(array $args): string
    {
        $name  = $args['name'] ?? throw new RuntimeException('skill_read: missing argument "name".');
        $skill = \App\Models\Skill::where('name', $name)->where('is_active', true)->first();
        if ($skill === null) {
            return "Skill '{$name}' not found.";
        }
        return $skill->readMarkdown() ?? "SKILL.md could not be read.";
    }

    private function runProcessStatus(array $args): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $output = [];
            exec('tasklist 2>&1', $output);
            return implode("\n", array_slice($output, 0, 40));
        }
        $output = [];
        exec('ps aux 2>&1', $output);
        return implode("\n", array_slice($output, 0, 40));
    }

    private function runArtisan(array $args): string
    {
        $command    = $args['command'] ?? throw new RuntimeException('laravel_artisan: missing argument "command".');
        $artisan    = base_path('artisan');
        $fullCmd    = PHP_BINARY . " {$artisan} {$command} 2>&1";
        $output     = [];
        $code       = 0;
        exec($fullCmd, $output, $code);
        return implode("\n", $output);
    }

    private function runGit(array $args): string
    {
        $op      = $args['operation'] ?? 'status';
        $path    = $args['path']      ?? base_path();

        // Restrict to safe operations
        $allowed = ['pull', 'status', 'log', 'diff', 'fetch'];
        if (! in_array($op, $allowed, true)) {
            throw new RuntimeException("git_operation: '{$op}' is not an allowed git operation.");
        }

        $output = [];
        $code   = 0;
        exec("git -C " . escapeshellarg($path) . " {$op} 2>&1", $output, $code);
        return implode("\n", $output);
    }

    private function runComposer(array $args): string
    {
        $op      = $args['operation'] ?? 'install';
        $path    = $args['path']      ?? base_path();
        $allowed = ['install', 'update', 'dump-autoload'];

        if (! in_array($op, $allowed, true)) {
            throw new RuntimeException("composer_operation: '{$op}' is not an allowed composer operation.");
        }

        $output = [];
        exec("composer {$op} --working-dir=" . escapeshellarg($path) . " 2>&1", $output);
        return implode("\n", $output);
    }

    private function runNpm(array $args): string
    {
        $op      = $args['operation'] ?? 'install';
        $path    = $args['path']      ?? base_path();
        $allowed = ['install', 'run', 'build', 'ci'];

        if (! in_array($op, $allowed, true)) {
            throw new RuntimeException("npm_operation: '{$op}' is not an allowed npm operation.");
        }

        $script  = $args['script'] ?? '';
        $cmd     = "npm {$op}" . ($script ? " {$script}" : '') . " --prefix " . escapeshellarg($path) . " 2>&1";
        $output  = [];
        exec($cmd, $output);
        return implode("\n", $output);
    }

    // -------------------------------------------------------------------------

    private function logStep(
        int $sessionId,
        int $stepNumber,
        string $toolName,
        ?string $input,
        ?string $output,
        int $durationMs,
        bool $isError = false,
    ): void {
        try {
            ExecutionLog::create([
                'session_id'  => $sessionId,
                'step_number' => $stepNumber,
                'action_type' => $isError ? 'error' : 'tool_use',
                'tool_name'   => $toolName,
                'input_data'  => $input,
                'output_data' => $output,
                'tokens_used' => 0,
                'duration_ms' => $durationMs,
                'created_at'  => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('[ToolExecutor] Failed to log step.', ['error' => $e->getMessage()]);
        }
    }
}
