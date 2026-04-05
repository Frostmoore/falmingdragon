<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use App\Services\Skill\SkillManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkillController extends Controller
{
    public function __construct(
        private readonly SkillManager $manager,
    ) {}

    /** GET /api/fd/skills */
    public function index(): JsonResponse
    {
        return response()->json(Skill::orderBy('name')->get());
    }

    /** POST /api/fd/skills/install */
    public function install(Request $request): JsonResponse
    {
        $request->validate(['path' => 'required|string']);

        try {
            $skill = $this->manager->install($request->input('path'));
            return response()->json($skill, 201);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /** POST /api/fd/skills/{id}/toggle */
    public function toggle(int $id): JsonResponse
    {
        $newState = $this->manager->toggle($id);
        return response()->json(['is_active' => $newState]);
    }

    /** DELETE /api/fd/skills/{id} */
    public function destroy(int $id): JsonResponse
    {
        $this->manager->uninstall($id);
        return response()->json(['message' => 'Skill deactivated.']);
    }
}
