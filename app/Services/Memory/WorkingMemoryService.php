<?php

declare(strict_types=1);

namespace App\Services\Memory;

class WorkingMemoryService
{
    /**
     * Maximum token budget before truncation kicks in.
     * Approximation: GPT-4 style, 1 token ≈ 4 UTF-8 chars.
     */
    private const MAX_TOKENS  = 10_000;
    private const TARGET_TOKENS = 9_000;

    private string $path;

    public function __construct()
    {
        $this->path = base_path('WORKINGMEMORY.md');
    }

    /**
     * Append a line to WORKINGMEMORY.md with a UTC timestamp prefix.
     * Truncates oldest lines if the file exceeds MAX_TOKENS.
     * Operation is protected by an exclusive file lock.
     */
    public function append(string $line): void
    {
        $entry = '[' . now('UTC')->toIso8601String() . '] ' . trim($line);

        $fp = fopen($this->path, 'a+');
        if ($fp === false) {
            return;
        }

        try {
            flock($fp, LOCK_EX);

            fwrite($fp, $entry . "\n");

            // Re-read full content for truncation check
            rewind($fp);
            $content = stream_get_contents($fp);

            if ($this->estimateTokens($content) > self::MAX_TOKENS) {
                $truncated = $this->truncate($content);
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, $truncated);
            }
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * Read the working memory file.
     * Returns last $lastLines lines if specified, otherwise the full file.
     */
    public function read(?int $lastLines = null): string
    {
        if (! is_file($this->path)) {
            return '';
        }

        $content = file_get_contents($this->path);
        if ($content === false) {
            return '';
        }

        if ($lastLines !== null && $lastLines > 0) {
            $lines = explode("\n", rtrim($content));
            $slice = array_slice($lines, -$lastLines);
            return implode("\n", $slice);
        }

        return $content;
    }

    /**
     * Token estimate: 1 token ≈ 4 UTF-8 chars (GPT-4 heuristic).
     * Documented here: this is an approximation, not an exact tiktoken count.
     */
    private function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text, 'UTF-8') / 4);
    }

    /**
     * Remove oldest lines until the content fits within TARGET_TOKENS.
     * Preserves the header block (lines starting with # or >) untouched.
     */
    private function truncate(string $content): string
    {
        $lines = explode("\n", $content);

        // Separate header lines (# and >) from data lines
        $headerEnd = 0;
        foreach ($lines as $i => $line) {
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '>') || str_starts_with($line, '<!--')) {
                $headerEnd = $i + 1;
            } else {
                break;
            }
        }

        $header    = array_slice($lines, 0, $headerEnd);
        $dataLines = array_slice($lines, $headerEnd);

        // Remove oldest data lines until within target
        while (
            ! empty($dataLines)
            && $this->estimateTokens(implode("\n", array_merge($header, $dataLines))) > self::TARGET_TOKENS
        ) {
            array_shift($dataLines);
        }

        return implode("\n", array_merge($header, $dataLines));
    }
}
