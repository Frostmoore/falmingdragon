<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Tool;
use App\Services\Generator\WebGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeneratorController extends Controller
{
    public function __construct(
        private readonly WebGeneratorService $generator,
    ) {}

    /**
     * POST /generator/run
     * Accepts JSON or form data; returns JSON result.
     */
    public function run(Request $request): JsonResponse
    {
        $type        = $request->input('type', 'skill');
        $name        = $request->input('name', '');
        $description = $request->input('description', '');
        $tools       = array_filter(array_map('trim', explode(',', $request->input('tools', ''))));
        $extraPrompt = $request->input('extra_prompt', '');

        // Normalise name to snake_case
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9]+/', '_', $name);
        $name = trim($name, '_');

        if (strlen($name) < 2) {
            return response()->json(['error' => 'Name must be at least 2 characters.'], 422);
        }

        if (strlen($description) < 10) {
            return response()->json(['error' => 'Description is too short.'], 422);
        }

        if (! in_array($type, ['skill', 'tool', 'skilltool'], true)) {
            return response()->json(['error' => 'Invalid type.'], 422);
        }

        try {
            $result = $this->generator->generate(
                type:        $type,
                name:        $name,
                description: $description,
                tools:       $tools,
                extraPrompt: $extraPrompt,
            );

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /generator/tools-list
     * Returns active tools for the checkbox selector.
     */
    public function toolsList(): JsonResponse
    {
        $tools = Tool::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'display_name']);

        return response()->json($tools);
    }
}
