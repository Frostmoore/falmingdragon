<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use App\Services\Dashboard\AIEditorService;
use App\Services\Dashboard\EnvEditor;
use App\Services\Skill\SkillManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class SkillEditorController extends Controller
{
    public function __construct(
        private readonly SkillManager   $manager,
        private readonly AIEditorService $editor,
        private readonly EnvEditor       $env,
    ) {}

    /** GET /skills */
    public function index(): View
    {
        $skills = Skill::orderBy('name')->get();
        return view('dashboard.skills', compact('skills'));
    }

    /** GET /skills/{id} */
    public function show(int $id): View
    {
        $skill      = Skill::findOrFail($id);
        $content    = $skill->readMarkdown() ?? '';
        $envKeys    = $skill->getEnvRequiredList();
        $envValues  = $this->env->read($envKeys);
        $toolsReq   = $skill->getToolsRequiredList();

        return view('dashboard.skill-detail', compact('skill', 'content', 'envKeys', 'envValues', 'toolsReq'));
    }

    /** GET /skills/{id}/edit */
    public function edit(int $id): View
    {
        $skill   = Skill::findOrFail($id);
        $content = $skill->readMarkdown() ?? '';
        return view('dashboard.skill-edit', compact('skill', 'content'));
    }

    /** POST /skills/{id}/edit */
    public function update(Request $request, int $id): RedirectResponse
    {
        $skill   = Skill::findOrFail($id);
        $content = $request->input('content', '');

        if (! empty($skill->skill_md_path)) {
            file_put_contents($skill->skill_md_path, $content);
        }

        return redirect()->route('skills.index')->with('success', 'Skill updated successfully.');
    }

    /** POST /skills/{id}/ai-suggest — returns JSON {suggestion} */
    public function aiSuggest(Request $request, int $id): JsonResponse
    {
        $skill       = Skill::findOrFail($id);
        $instruction = $request->input('instruction', '');

        if (empty($instruction)) {
            return response()->json(['error' => 'Instruction is required.'], 422);
        }

        try {
            $suggestion = $this->editor->suggestSkillModification($skill, $instruction);
            return response()->json(['suggestion' => $suggestion]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /** POST /skills/{id}/ai-apply — writes the suggestion to SKILL.md */
    public function aiApply(Request $request, int $id): JsonResponse
    {
        $skill   = Skill::findOrFail($id);
        $content = $request->input('content', '');

        if (empty($content)) {
            return response()->json(['error' => 'No content provided.'], 422);
        }

        try {
            $this->editor->applySkillModification($skill, $content);
            // Refresh env_required and tools_required from updated frontmatter
            $frontmatter = $skill->parseFrontmatter();
            $skill->update([
                'env_required'   => $frontmatter['env_required']   ?? null,
                'tools_required' => $frontmatter['tools_required'] ?? null,
            ]);
            return response()->json(['ok' => true, 'message' => 'SKILL.md updated.']);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /** POST /skills/{id}/config — save .env values for this skill */
    public function saveConfig(Request $request, int $id): RedirectResponse
    {
        $skill   = Skill::findOrFail($id);
        $envKeys = $skill->getEnvRequiredList();
        $pairs   = [];

        foreach ($envKeys as $key) {
            if ($request->has($key)) {
                $pairs[$key] = $request->input($key, '');
            }
        }

        if (! empty($pairs)) {
            $this->env->write($pairs);
        }

        return redirect()->route('skills.show', $id)->with('success', 'Configuration saved.');
    }

    /** POST /skills/install */
    public function install(Request $request): RedirectResponse
    {
        $request->validate(['path' => 'required|string']);

        try {
            $this->manager->install($request->input('path'));
            return redirect()->route('skills.index')->with('success', 'Skill installed successfully.');
        } catch (\Throwable $e) {
            return redirect()->route('skills.index')->with('error', $e->getMessage());
        }
    }
}
