<?php

declare(strict_types=1);

namespace App\Services\Security;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Per-session sandboxing: isolated working directory, tool call counter, path validation.
 */
class SessionSandbox
{
    private int $toolCallCount = 0;
    private readonly int $maxToolCalls;
    private readonly string $sessionDir;

    public function __construct(
        private readonly string $sessionUuid,
    ) {
        $this->maxToolCalls = config('flamingdragon.security.max_tool_calls_per_session', 50);
        $baseDir = config('flamingdragon.execution.session_dir', storage_path('app/flamingdragon/sessions'));
        $this->sessionDir = $baseDir . DIRECTORY_SEPARATOR . $sessionUuid;
    }

    /**
     * Create the isolated session working directory.
     */
    public function initialize(): void
    {
        if (! is_dir($this->sessionDir)) {
            mkdir($this->sessionDir, 0755, true);
        }
    }

    /**
     * Increment and check the tool call counter.
     *
     * @throws RuntimeException when the maximum is exceeded.
     */
    public function recordToolCall(): void
    {
        $this->toolCallCount++;

        if ($this->toolCallCount > $this->maxToolCalls) {
            throw new RuntimeException(
                "Session {$this->sessionUuid} exceeded maximum tool call limit of {$this->maxToolCalls}."
            );
        }
    }

    public function getToolCallCount(): int
    {
        return $this->toolCallCount;
    }

    /**
     * Validate that a file path is within an allowed base path and not blocked.
     *
     * @throws RuntimeException if the path is disallowed.
     */
    public function validatePath(string $path): void
    {
        // Resolve symlinks to prevent traversal attacks.
        $resolved = realpath($path) ?: $path;

        $blockedPaths  = config('flamingdragon.security.blocked_paths', []);
        $allowedPaths  = config('flamingdragon.security.allowed_base_paths', []);

        foreach ($blockedPaths as $blocked) {
            if (str_starts_with($resolved, $blocked)) {
                Log::warning('[SessionSandbox] Blocked path access attempt.', [
                    'session' => $this->sessionUuid,
                    'path'    => $path,
                    'resolved' => $resolved,
                ]);
                throw new RuntimeException("Access to path '{$path}' is not permitted.");
            }
        }

        // When running on a dev environment (Windows/XAMPP) there may be no allowed
        // base paths configured — skip enforcement in that case.
        if (empty($allowedPaths)) {
            return;
        }

        foreach ($allowedPaths as $allowed) {
            if (str_starts_with($resolved, $allowed)) {
                return;
            }
        }

        // Also always allow within the session working directory itself.
        if (str_starts_with($resolved, $this->sessionDir)) {
            return;
        }

        Log::warning('[SessionSandbox] Path outside allowed base paths.', [
            'session'       => $this->sessionUuid,
            'path'          => $path,
            'resolved'      => $resolved,
            'allowed_paths' => $allowedPaths,
        ]);
        throw new RuntimeException("Path '{$path}' is outside allowed base paths.");
    }

    public function getSessionDir(): string
    {
        return $this->sessionDir;
    }

    /**
     * Remove the session working directory after completion.
     */
    public function cleanup(): void
    {
        $this->removeDirectory($this->sessionDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
