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
            'namespace'   => 'required|string|max:100',
            'key'         => 'required|string|max:255',
            'value'       => 'required|string',
            'memory_type' => 'nullable|in:fact,preference,context,instruction',
            'source'      => 'nullable|string|max:255',
        ]);

        $memory = $this->memoryService->remember(
            namespace: $data['namespace'],
            key:       $data['key'],
            value:     $data['value'],
            type:      MemoryType::from($data['memory_type'] ?? 'fact'),
            source:    $data['source'] ?? null,
        );

        return response()->json($memory, 201);
    }

    /** DELETE /api/fd/memory/{id} */
    public function destroy(int $id): JsonResponse
    {
        $memory = Memory::findOrFail($id);
        $this->memoryService->forget($memory->namespace, $memory->key);
        return response()->json(['message' => 'Memory deleted.']);
    }
}
