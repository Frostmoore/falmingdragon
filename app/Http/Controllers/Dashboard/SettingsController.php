<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AllowedCommand;
use App\Models\LlmProvider;
use App\Models\Memory;
use App\Models\Skill;
use App\Models\Tool;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /** GET /settings */
    public function index(): View
    {
        $providers = LlmProvider::orderBy('name')->get();
        $commands  = AllowedCommand::orderBy('category')->orderBy('name')->get();
        $tools     = Tool::orderBy('name')->get();
        $skills    = Skill::orderBy('name')->get();
        $memories  = Memory::orderByDesc('updated_at')->paginate(30);

        $defaultProvider = config('flamingdragon.llm.default_provider', 'anthropic');
        $defaultModel    = config('flamingdragon.llm.default_model', 'claude-sonnet-4-6');

        return view('dashboard.settings', compact(
            'providers',
            'commands',
            'tools',
            'skills',
            'memories',
            'defaultProvider',
            'defaultModel',
        ));
    }

    /** GET /commands */
    public function commands(): View
    {
        $commands = AllowedCommand::orderBy('category')->orderBy('name')->get();
        $tools    = Tool::where('is_active', true)->orderBy('name')->get();
        return view('dashboard.commands', compact('commands', 'tools'));
    }

    /** GET /tools */
    public function tools(): View
    {
        $tools = Tool::orderBy('name')->get();
        return view('dashboard.tools', compact('tools'));
    }

    /** GET /memory */
    public function memory(): View
    {
        $memories   = Memory::orderByDesc('updated_at')->paginate(30);
        $namespaces = Memory::distinct()->pluck('namespace');
        return view('dashboard.memory', compact('memories', 'namespaces'));
    }
}
