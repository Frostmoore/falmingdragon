<?php

declare(strict_types=1);

namespace App\Services\Memory;

use App\Enums\MemoryType;
use App\Models\Memory;
use App\Services\Embeddings\EmbeddingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * CRUD, full-text search, and semantic (vector) search for the persistent memory store.
 */
class MemoryService
{
    /** Minimum content length (chars) that triggers automatic embedding generation. */
    private const AUTO_EMBED_THRESHOLD = 150;

    public function __construct(
        private readonly EmbeddingService $embeddings,
    ) {}

    /**
     * Store or update a memory entry.
     * Automatically generates an embedding when the content is long or marked important.
     */
    public function remember(
        string $namespace,
        string $key,
        string $value,
        MemoryType $type = MemoryType::Fact,
        ?string $source = null,
        ?int $expiresInSeconds = null,
        bool $important = false,
    ): Memory {
        $expiresAt = $expiresInSeconds !== null
            ? now()->addSeconds($expiresInSeconds)
            : null;

        $memory = Memory::updateOrCreate(
            ['namespace' => $namespace, 'key' => $key],
            [
                'value'        => $value,
                'memory_type'  => $type,
                'source'       => $source,
                'expires_at'   => $expiresAt,
                'is_important' => $important,
            ]
        );

        // Auto-generate embedding for important or long-form memories
        $shouldEmbed = $important || mb_strlen($value) >= self::AUTO_EMBED_THRESHOLD;

        if ($shouldEmbed && $this->embeddings->isAvailable()) {
            try {
                $vector = $this->embeddings->generate($value);
                if ($vector !== null) {
                    $memory->update(['embedding' => $vector]);
                }
            } catch (Throwable $e) {
                Log::warning('[MemoryService] Embedding generation failed.', [
                    'key'   => "{$namespace}/{$key}",
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $memory;
    }

    /**
     * Retrieve a single memory entry (null if not found or expired).
     */
    public function recall(string $namespace, string $key): ?Memory
    {
        $memory = Memory::where('namespace', $namespace)->where('key', $key)->first();

        if ($memory === null || $memory->isExpired()) {
            return null;
        }

        return $memory;
    }

    /**
     * Full-text search across memory values (keyword fallback).
     *
     * @return Collection<int, Memory>
     */
    public function search(string $query, ?string $namespace = null, int $limit = 20): Collection
    {
        $builder = Memory::where('value', 'like', '%' . $query . '%')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });

        if ($namespace !== null) {
            $builder->where('namespace', $namespace);
        }

        return $builder->orderByDesc('updated_at')->limit($limit)->get();
    }

    /**
     * Semantic search using cosine similarity on stored embeddings.
     * Falls back to full-text search if embeddings are unavailable.
     *
     * @return array<Memory>
     */
    public function semanticSearch(string $query, int $limit = 10): array
    {
        if (! $this->embeddings->isAvailable()) {
            return $this->search($query, limit: $limit)->all();
        }

        $queryVector = $this->embeddings->generate($query);

        if ($queryVector === null) {
            // API call failed — fall back to keyword search
            return $this->search($query, limit: $limit)->all();
        }

        // Load all non-expired memories that have an embedding
        $withVectors = Memory::whereNotNull('embedding')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get();

        if ($withVectors->isEmpty()) {
            // No vectors yet — fall back to most-recent memories
            return $this->getContext(limit: $limit);
        }

        // Score every memory by cosine similarity
        $scored = $withVectors
            ->map(function (Memory $memory) use ($queryVector): array {
                /** @var array<float> $vec */
                $vec = is_array($memory->embedding) ? $memory->embedding : json_decode((string) $memory->embedding, true);
                return [
                    'memory' => $memory,
                    'score'  => $vec ? $this->embeddings->cosineSimilarity($queryVector, $vec) : 0.0,
                ];
            })
            ->sortByDesc('score')
            ->take($limit);

        return $scored->pluck('memory')->values()->all();
    }

    /**
     * Retrieve context memories for an agent session.
     * Uses semantic search when a query is provided and embeddings are available,
     * otherwise falls back to most-recent memories.
     *
     * @return array<Memory>
     */
    public function getContext(string $query = '', ?string $namespace = null, int $limit = 10): array
    {
        // If we have a meaningful query and embeddings, do semantic search
        if ($query !== '' && $this->embeddings->isAvailable()) {
            $results = $this->semanticSearch($query, $limit);
            if (! empty($results)) {
                return $results;
            }
        }

        // Fallback: most-recent non-expired memories
        $builder = Memory::where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        })->orderByDesc('updated_at')->limit($limit);

        if ($namespace !== null) {
            $builder->where('namespace', $namespace);
        }

        return $builder->get()->all();
    }

    /**
     * Delete a memory entry.
     */
    public function forget(string $namespace, string $key): bool
    {
        return (bool) Memory::where('namespace', $namespace)->where('key', $key)->delete();
    }

    /**
     * Delete all expired memory entries.
     */
    public function sweep(): int
    {
        try {
            $count = Memory::where('expires_at', '<=', now())->delete();
            Log::info('[MemoryService] Swept expired memories.', ['count' => $count]);
            return (int) $count;
        } catch (Throwable $e) {
            Log::error('[MemoryService] sweep() failed.', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Generate (or regenerate) embeddings for all memories that are missing one.
     * Useful for backfilling after enabling the OpenAI key.
     *
     * @return array{generated: int, skipped: int, failed: int}
     */
    public function backfillEmbeddings(): array
    {
        $stats = ['generated' => 0, 'skipped' => 0, 'failed' => 0];

        if (! $this->embeddings->isAvailable()) {
            return $stats;
        }

        $memories = Memory::whereNull('embedding')
            ->where(function ($q) {
                $q->where('is_important', true)
                  ->orWhereRaw('CHAR_LENGTH(value) >= ?', [self::AUTO_EMBED_THRESHOLD]);
            })
            ->get();

        foreach ($memories as $memory) {
            try {
                $vector = $this->embeddings->generate($memory->value);
                if ($vector !== null) {
                    $memory->update(['embedding' => $vector]);
                    $stats['generated']++;
                } else {
                    $stats['skipped']++;
                }
            } catch (Throwable $e) {
                $stats['failed']++;
            }
        }

        return $stats;
    }
}
