<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tool;
use Illuminate\Http\JsonResponse;

class ToolController extends Controller
{
    /** GET /api/fd/tools */
    public function index(): JsonResponse
    {
        return response()->json(Tool::orderBy('name')->get());
    }

    /** POST /api/fd/tools/{id}/toggle */
    public function toggle(int $id): JsonResponse
    {
        $tool = Tool::findOrFail($id);
        $tool->update(['is_active' => ! $tool->is_active]);
        return response()->json(['is_active' => $tool->is_active]);
    }
}
