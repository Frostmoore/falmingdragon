<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use App\Services\Skill\SkillManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SkillEditorController extends Controller
{
    public function __construct(
        private readonly SkillManager $manager,
    ) {}

    /** GET /skills */
    public function index(): View
    {
        $skills = Skill::orderBy('name')->get();
        return view('dashboard.skills', compact('skills'));
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
