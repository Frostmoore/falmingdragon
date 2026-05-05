<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Tool;
use App\Services\Dashboard\AIEditorService;
use App\Services\Dashboard\EnvEditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class ToolDetailController extends Controller
{
    public function __construct(
        private readonly AIEditorService $editor,
        private readonly EnvEditor       $env,
    ) {}

    /** GET /tools/{id} */
    public function show(int $id): View
    {
        $tool       = Tool::findOrFail($id);
        $configKeys = $tool->config_keys ?? [];
        $envValues  = $this->env->read($configKeys);
        $methodCode = $tool->type->value === 'builtin'
            ? $this->editor->extractToolMethod($tool)
            : null;

        return view('dashboard.tool-detail', compact('tool', 'configKeys', 'envValues', 'methodCode'));
    }

    /** POST /tools/{id}/ai-suggest  — returns JSON {suggestion} */
    public function aiSuggest(Request $request, int $id): JsonResponse
    {
        $tool        = Tool::findOrFail($id);
        $instruction = $request->input('instruction', '');

        if (empty($instruction)) {
            return response()->json(['error' => 'Instruction is required.'], 422);
        }

        try {
            $suggestion = $this->editor->suggestToolModification($tool, $instruction);
            return response()->json(['suggestion' => $suggestion]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /** POST /tools/{id}/ai-apply  — applies the code suggestion to ToolExecutor.php */
    public function aiApply(Request $request, int $id): JsonResponse
    {
        $tool    = Tool::findOrFail($id);
        $newCode = $request->input('code', '');

        if (empty($newCode)) {
            return response()->json(['error' => 'No code provided.'], 422);
        }

        try {
            $this->editor->applyToolModification($tool, $newCode);
            return response()->json(['ok' => true, 'message' => 'Tool implementation updated.']);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /** POST /tools/{id}/config  — save .env values for this tool */
    public function saveConfig(Request $request, int $id): RedirectResponse
    {
        $tool       = Tool::findOrFail($id);
        $configKeys = $tool->config_keys ?? [];
        $pairs      = [];

        foreach ($configKeys as $key) {
            if ($request->has($key)) {
                $pairs[$key] = $request->input($key, '');
            }
        }

        if (! empty($pairs)) {
            $this->env->write($pairs);
        }

        // Also allow updating tool metadata
        $tool->update(array_filter([
            'display_name' => $request->input('display_name'),
            'description'  => $request->input('description'),
        ]));

        return redirect()->route('tools.show', $id)->with('success', 'Configuration saved.');
    }

    /** POST /tools/{id}/config-keys  — update which .env keys this tool declares */
    public function saveConfigKeys(Request $request, int $id): RedirectResponse
    {
        $tool = Tool::findOrFail($id);
        $raw  = $request->input('config_keys', '');

        // Accept comma-separated or JSON array
        if (str_starts_with(trim($raw), '[')) {
            $keys = json_decode($raw, true) ?? [];
        } else {
            $keys = array_filter(array_map('trim', explode(',', $raw)));
        }

        $tool->update(['config_keys' => array_values($keys)]);

        return redirect()->route('tools.show', $id)->with('success', 'Config keys updated.');
    }
}
