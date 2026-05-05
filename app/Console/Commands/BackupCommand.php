<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BackupCommand extends Command
{
    protected $signature = 'fd:backup
        {--tag= : Optional label appended to the backup directory name}';

    protected $description = 'Snapshot the FlamingDragon project to _backup/<timestamp>[_tag]/ (blacklist-based).';

    /**
     * Relative paths (forward-slash) excluded entirely — subdirectories also skipped.
     */
    private const EXCLUDE_DIRS = [
        'vendor',
        'node_modules',
        '_backup',
        'storage/logs',
        'storage/framework/cache',
        'storage/framework/sessions',
        'storage/framework/views',
        'bootstrap/cache',
        '.git',
        'storage/app/public/media',
    ];

    /** Exact filenames excluded regardless of location. */
    private const EXCLUDE_FILENAMES = ['.env'];

    /**
     * Binary media extensions excluded everywhere.
     * PDFs are NOT excluded (discovery docs are text-equivalent).
     */
    private const EXCLUDE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'mp3', 'mp4', 'wav', 'ogg'];

    /** @var array{timestamp:string, tag:string|null, files:list<array{path:string,sha256:string}>} */
    private array $manifest;

    private int $copied  = 0;
    private int $skipped = 0;

    public function handle(): int
    {
        $root = base_path();
        $tag  = $this->option('tag');

        $dirName    = now()->format('Y-m-d_His') . ($tag ? "_{$tag}" : '');
        $backupRoot = $root . DIRECTORY_SEPARATOR . '_backup';
        $backupPath = $backupRoot . DIRECTORY_SEPARATOR . $dirName;

        $this->info("[fd:backup] Destination: {$backupPath}");

        if (! is_dir($backupRoot) && ! mkdir($backupRoot, 0755, true)) {
            $this->error("Cannot create _backup/ directory.");
            return self::FAILURE;
        }
        if (! mkdir($backupPath, 0755, true)) {
            $this->error("Cannot create backup directory: {$backupPath}");
            return self::FAILURE;
        }

        $this->manifest = [
            'timestamp' => now()->toIso8601String(),
            'tag'       => $tag,
            'files'     => [],
        ];
        $this->copied  = 0;
        $this->skipped = 0;

        $this->copyDir($root, $backupPath, $root);

        file_put_contents(
            $backupPath . DIRECTORY_SEPARATOR . 'MANIFEST.json',
            json_encode($this->manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        $this->info("[fd:backup] Done. Copied: {$this->copied} files, skipped: {$this->skipped}.");
        $this->line($backupPath);

        return self::SUCCESS;
    }

    private function copyDir(string $src, string $dest, string $root): void
    {
        $entries = @scandir($src);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $srcPath = $src . DIRECTORY_SEPARATOR . $entry;
            $rel     = $this->rel($srcPath, $root);
            $isDir   = is_dir($srcPath);

            if ($this->shouldExclude($rel, $isDir)) {
                $this->skipped++;
                continue;
            }

            $destPath = $dest . DIRECTORY_SEPARATOR . $entry;

            if ($isDir) {
                if (! is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
                $this->copyDir($srcPath, $destPath, $root);
                continue;
            }

            // Only copy regular files (skip junctions, sockets, etc.)
            if (! is_file($srcPath)) {
                $this->skipped++;
                continue;
            }

            if (! copy($srcPath, $destPath)) {
                $this->warn("[fd:backup] Could not copy: {$rel}");
                $this->skipped++;
                continue;
            }

            $this->manifest['files'][] = [
                'path'   => $rel,
                'sha256' => hash_file('sha256', $srcPath),
            ];
            $this->copied++;
        }
    }

    private function rel(string $absolute, string $root): string
    {
        $rel = ltrim(substr($absolute, strlen($root)), DIRECTORY_SEPARATOR);
        return str_replace('\\', '/', $rel);
    }

    private function shouldExclude(string $rel, bool $isDir): bool
    {
        foreach (self::EXCLUDE_DIRS as $excl) {
            if ($rel === $excl || str_starts_with($rel, $excl . '/')) {
                return true;
            }
        }

        if ($isDir) {
            return false;
        }

        $name = basename($rel);

        if (in_array($name, self::EXCLUDE_FILENAMES, true)) {
            return true;
        }

        // .env.* files except .env.example
        if (str_starts_with($name, '.env.') && $name !== '.env.example') {
            return true;
        }

        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        return in_array($ext, self::EXCLUDE_EXTENSIONS, true);
    }
}
