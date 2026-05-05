<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MemoryType;
use App\Models\Memory;
use App\Services\Embeddings\EmbeddingService;
use Illuminate\Console\Command;

class SyncEmbeddingsCommand extends Command
{
    protected $signature = 'fd:sync-embeddings
        {--file= : Sync only this file (e.g. FLAMINGDRAGON.md). Omit to sync all 6 system files.}';

    protected $description = 'Generate/update embeddings for the 6 system .md files in the memory table (key prefix: system:).';

    private const SYSTEM_FILES = [
        'FLAMINGDRAGON.md',
        'USER.md',
        'MEMORY.md',
        'WORKINGMEMORY.md',
        'TOOLS.md',
        'SKILLS.md',
    ];

    public function handle(EmbeddingService $embeddings): int
    {
        if (! $embeddings->isAvailable()) {
            $this->error("OPENAI_API_KEY is not configured — cannot generate embeddings.");
            return self::FAILURE;
        }

        $targetFile = $this->option('file');

        $files = $targetFile
            ? [$targetFile]
            : self::SYSTEM_FILES;

        $results = [];
        $failed  = 0;

        foreach ($files as $filename) {
            $path = base_path($filename);

            if (! is_file($path)) {
                $this->warn("  SKIPPED: {$filename} — file not found.");
                $results[] = ['File' => $filename, 'Status' => 'NOT FOUND', 'Tokens (est.)' => '-'];
                $failed++;
                continue;
            }

            $content    = file_get_contents($path);
            $tokenEst   = (int) ceil(mb_strlen($content, 'UTF-8') / 4);
            $memoryKey  = $filename;                       // stored under namespace='system'

            $this->line("  Syncing: {$filename} (~{$tokenEst} tokens)...");

            $vector = $embeddings->generate($content);

            Memory::updateOrCreate(
                ['namespace' => 'system', 'key' => $memoryKey],
                [
                    'value'       => $content,
                    'embedding'   => $vector,
                    'memory_type' => MemoryType::Context,
                    'source'      => 'fd:sync-embeddings',
                    'is_important' => true,
                ]
            );

            $status    = $vector !== null ? 'OK' : 'OK (no embedding — API unavailable)';
            $results[] = ['File' => $filename, 'Status' => $status, 'Tokens (est.)' => $tokenEst];

            $this->line("    → {$status}");
        }

        $this->table(['File', 'Status', 'Tokens (est.)'], $results);

        if ($failed > 0) {
            $this->warn("[fd:sync-embeddings] {$failed} file(s) skipped.");
        } else {
            $this->info("[fd:sync-embeddings] Done. " . count($files) . " file(s) synced.");
        }

        return $failed === count($files) ? self::FAILURE : self::SUCCESS;
    }
}
