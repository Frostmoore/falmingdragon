<?php

declare(strict_types=1);

namespace App\Services\Memory;

use App\Enums\MemoryType;
use App\Models\Memory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * CRUD and search operations for the persistent memory store.
 */
class MemoryService
{
    /**
     * Store or update a memory entry.
     *
     * @param  string       $namespace
     * @param  string       $key
     * @param  string       $value
     * @param  MemoryType   $type
     * @param  string|null  $source
     * @param  int|null     $expiresInSeconds
     * @return Memory
     */
    public function remember(
        string $namespace,
        string $key,
        string $value,
        MemoryType $type = MemoryType::Fact,
        ?string $source = null,
        ?int $expiresInSeconds = null,
    ): Memory {
        $expiresAt = $expiresInSeconds !== null
            ? now()->addSeconds($expiresInSeconds)
            : null;

        return Memory::updateOrCreate(
            ['namespace' => $namespace, 'key' => $key],
            [
                'value'       => $value,
                'memory_type' => $type,
                'source'      => $source,
                'expires_at'  => $expiresAt,
            ]
        );
    }

    /**
     * Retrieve a single memory entry (null if not found or expired).
     *
     * @param  string  $namespace
     * @param  string  $key
     * @return Memory|null
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
     * Full-text search across memory values.
     *
     * @param  string       $query
     * @param  string|null  $namespace
     * @param  int          $limit
     * @return Collection<Memory>
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
     * Delete a memory entry.
     *
     * @param  string  $namespace
     * @param  string  $key
     * @return bool
     */
    public function forget(string $namespace, string $key): bool
    {
        return (bool) Memory::where('namespace', $namespace)->where('key', $key)->delete();
    }

    /**
     * Retrieve recent memories for context assembly.
     *
     * @param  string|null  $namespace
     * @param  int          $limit
     * @return array<Memory>
     */
    public function getContext(?string $namespace = null, int $limit = 10): array
    {
        $builder = Memory::where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        })->orderByDesc('updated_at')->limit($limit);

        if ($namespace !== null) {
            $builder->where('namespace', $namespace);
        }

        return $builder->get()->all();
    }

    /**
     * Delete all expired memory entries.
     *
     * @return int Number of deleted records.
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
}
