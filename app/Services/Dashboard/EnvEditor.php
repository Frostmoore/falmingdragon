<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

/**
 * Safe .env file reader / writer.
 */
class EnvEditor
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? base_path('.env');
    }

    /** Read the current value of one or more keys. Returns [key => value, ...] */
    public function read(array $keys): array
    {
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $this->readOne($key);
        }
        return $values;
    }

    /** Read a single key. Returns empty string if not found. */
    public function readOne(string $key): string
    {
        if (! file_exists($this->path)) {
            return '';
        }

        $lines = file($this->path, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, $key . '=')) {
                $value = substr($line, strlen($key) + 1);
                // Strip surrounding quotes
                if (
                    (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))
                ) {
                    $value = substr($value, 1, -1);
                }
                return $value;
            }
        }

        return '';
    }

    /**
     * Write multiple key=>value pairs to the .env file.
     * Updates existing keys in place; appends missing ones at the end.
     *
     * @param  array<string, string>  $pairs
     */
    public function write(array $pairs): void
    {
        $content = file_exists($this->path) ? file_get_contents($this->path) : '';
        $lines   = explode("\n", str_replace("\r\n", "\n", $content));

        foreach ($pairs as $key => $value) {
            // Quote if value contains spaces or special chars
            $needsQuotes = preg_match('/[\s#"\'\\\\]/', $value) || $value === '';
            $formatted   = $needsQuotes ? '"' . addslashes($value) . '"' : $value;
            $newLine     = "{$key}={$formatted}";

            $found = false;
            foreach ($lines as &$line) {
                if (preg_match('/^' . preg_quote($key, '/') . '\s*=/', $line)) {
                    $line  = $newLine;
                    $found = true;
                    break;
                }
            }
            unset($line);

            if (! $found) {
                $lines[] = $newLine;
            }
        }

        file_put_contents($this->path, implode("\n", $lines));
    }

    /** Check if the .env file is writable. */
    public function isWritable(): bool
    {
        return file_exists($this->path) ? is_writable($this->path) : is_writable(dirname($this->path));
    }
}
