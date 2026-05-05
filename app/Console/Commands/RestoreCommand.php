<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RestoreCommand extends Command
{
    protected $signature = 'fd:restore
        {backup : Name of the backup directory inside _backup/ (e.g. 2026-05-05_150000_tag)}
        {--dry-run : Preview what would be restored without touching any file}
        {--force  : Overwrite files without interactive confirmation}';

    protected $description = 'Restore a FlamingDragon backup created by fd:backup. Verifies SHA-256 hashes before writing.';

    public function handle(): int
    {
        $backupName = $this->argument('backup');
        $backupPath = base_path('_backup' . DIRECTORY_SEPARATOR . $backupName);
        $isDryRun   = (bool) $this->option('dry-run');
        $isForce    = (bool) $this->option('force');

        $manifestPath = $backupPath . DIRECTORY_SEPARATOR . 'MANIFEST.json';

        if (! is_dir($backupPath)) {
            $this->error("Backup directory not found: {$backupPath}");
            return self::FAILURE;
        }
        if (! is_file($manifestPath)) {
            $this->error("MANIFEST.json missing in backup — cannot restore safely.");
            return self::FAILURE;
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        if (! is_array($manifest) || ! isset($manifest['files'])) {
            $this->error("MANIFEST.json is malformed.");
            return self::FAILURE;
        }

        $this->info("[fd:restore] Backup: {$backupName}");
        $this->info("[fd:restore] Timestamp: " . ($manifest['timestamp'] ?? '?'));
        $this->info("[fd:restore] Files in manifest: " . count($manifest['files']));
        if ($isDryRun) {
            $this->warn("[fd:restore] DRY-RUN mode — no files will be written.");
        }

        // Verify all hashes first
        $this->info("[fd:restore] Verifying SHA-256 hashes...");
        $hashErrors = [];
        foreach ($manifest['files'] as $entry) {
            $srcPath = $backupPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry['path']);
            if (! is_file($srcPath)) {
                $hashErrors[] = "MISSING in backup: {$entry['path']}";
                continue;
            }
            $actual = hash_file('sha256', $srcPath);
            if ($actual !== $entry['sha256']) {
                $hashErrors[] = "HASH MISMATCH: {$entry['path']} (expected {$entry['sha256']}, got {$actual})";
            }
        }

        if (! empty($hashErrors)) {
            $this->error("[fd:restore] Hash verification failed:");
            foreach ($hashErrors as $err) {
                $this->error("  {$err}");
            }
            return self::FAILURE;
        }
        $this->info("[fd:restore] All hashes OK.");

        // Build restore preview
        $root    = base_path();
        $toWrite = [];
        $toSkip  = [];

        foreach ($manifest['files'] as $entry) {
            $rel      = $entry['path'];
            $destPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            $srcPath  = $backupPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);

            if (is_file($destPath)) {
                $currentHash = hash_file('sha256', $destPath);
                if ($currentHash === $entry['sha256']) {
                    $toSkip[] = ['rel' => $rel, 'reason' => 'unchanged'];
                    continue;
                }
            }
            $toWrite[] = ['rel' => $rel, 'src' => $srcPath, 'dest' => $destPath];
        }

        // Show preview table
        $this->table(['Status', 'File'], array_merge(
            array_map(fn ($f) => ['RESTORE', $f['rel']], $toWrite),
            array_map(fn ($f) => ['skip (same)', $f['rel']], array_slice($toSkip, 0, 5)),
            count($toSkip) > 5 ? [['...', count($toSkip) . ' more unchanged files']] : []
        ));

        if (empty($toWrite)) {
            $this->info("[fd:restore] Nothing to restore — all files match the backup.");
            return self::SUCCESS;
        }

        if ($isDryRun) {
            $this->warn("[fd:restore] DRY-RUN: would restore " . count($toWrite) . " file(s).");
            return self::SUCCESS;
        }

        if (! $isForce) {
            if (! $this->confirm("Restore " . count($toWrite) . " file(s)? This will overwrite current versions.")) {
                $this->info("[fd:restore] Aborted.");
                return self::SUCCESS;
            }
        }

        $restored = 0;
        $failed   = 0;
        foreach ($toWrite as $f) {
            $destDir = dirname($f['dest']);
            if (! is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }
            if (copy($f['src'], $f['dest'])) {
                $this->line("  RESTORED: {$f['rel']}");
                $restored++;
            } else {
                $this->error("  FAILED: {$f['rel']}");
                $failed++;
            }
        }

        $this->info("[fd:restore] Done. Restored: {$restored}, failed: {$failed}, unchanged: " . count($toSkip) . ".");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
