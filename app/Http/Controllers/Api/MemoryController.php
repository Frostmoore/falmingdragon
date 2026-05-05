<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\MemoryType;
use App\Http\Controllers\Controller;
use App\Models\Memory;
use App\Services\Memory\MemoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemoryController extends Controller
{
    public function __construct(
        private readonly MemoryService $memoryService,
    ) {}

    /** GET /api/fd/memory */
    public function index(Request $request): JsonResponse
    {
        $query = Memory::query();

        if ($request->filled('search')) {
            $query->where('value', 'like', '%' . $request->input('search') . '%');
        }

        if ($request->filled('namespace')) {
            $query->where('namespace', $request->input('namespace'));
        }

        return response()->json($query->orderByDesc('updated_at')->paginate(30));
    }

    /** POST /api/fd/memory */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'namespace'    => 'required|string|max:100',
            'key'          => 'required|string|max:255',
            'value'        => 'required|string',
            'memory_type'  => 'nullable|in:fact,preference,context,instruction',
            'source'       => 'nullable|string|max:255',
            'is_important' => 'nullable|boolean',
        ]);

        $memory = $this->memoryService->remember(
            namespace:  $data['namespace'],
            key:        $data['key'],
            value:      $data['value'],
            type:       MemoryType::from($data['memory_type'] ?? 'fact'),
            source:     $data['source'] ?? null,
            important:  (bool) ($data['is_important'] ?? false),
        );

        return response()->json($memory, 201);
    }

    /** PATCH /api/fd/memory/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $memory = Memory::findOrFail($id);
        $data   = $request->validate([
            'value'        => 'required|string',
            'memory_type'  => 'nullable|in:fact,preference,context,instruction',
            'is_important' => 'nullable|boolean',
        ]);

        $important = isset($data['is_important']) ? (bool) $data['is_important'] : $memory->is_important;

        // Re-run through MemoryService so embedding is regenerated if needed
        $memory = $this->memoryService->remember(
            namespace:  $memory->namespace,
            key:        $memory->key,
            value:      $data['value'],
            type:       \App\Enums\MemoryType::from($data['memory_type'] ?? $memory->memory_type->value),
            source:     $memory->source,
            important:  $important,
        );

        return response()->json($memory->fresh());
    }

    /** DELETE /api/fd/memory/{id} */
    public function destroy(int $id): JsonResponse
    {
        $memory = Memory::findOrFail($id);
        $this->memoryService->forget($memory->namespace, $memory->key);
        return response()->json(['message' => 'Memory deleted.']);
    }

    /** POST /api/fd/memory/backfill-embeddings */
    public function backfillEmbeddings(): JsonResponse
    {
        $stats = $this->memoryService->backfillEmbeddings();
        return response()->json(['success' => true, 'stats' => $stats]);
    }

    /** DELETE /api/fd/memory?namespace=xxx  — wipe an entire namespace */
    public function destroyNamespace(Request $request): JsonResponse
    {
        $ns = $request->validate(['namespace' => 'required|string|max:100'])['namespace'];
        $count = Memory::where('namespace', $ns)->delete();
        return response()->json(['message' => "Deleted {$count} memories from namespace '{$ns}'."]);
    }
}
