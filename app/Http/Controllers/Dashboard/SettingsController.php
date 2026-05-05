<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AllowedCommand;
use App\Models\LlmProvider;
use App\Models\Memory;
use App\Models\Skill;
use App\Models\Tool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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

    /** GET /prompts */
    public function prompts(): View
    {
        $promptPath   = config('flamingdragon.llm.system_prompt_path');
        $globalPrompt = file_exists($promptPath) ? file_get_contents($promptPath) : '';
        $commands     = AllowedCommand::orderBy('category')->orderBy('name')->get();

        return view('dashboard.prompts', compact('globalPrompt', 'commands', 'promptPath'));
    }

    /** POST /settings/update-webhook */
    public function updateWebhook(Request $request): JsonResponse
    {
        $data  = $request->validate(['webhook_url' => 'required|url']);
        $token = config('flamingdragon.telegram.bot_token', '');
        $secret = config('flamingdragon.telegram.webhook_secret', '');

        if (empty($token)) {
            return response()->json(['success' => false, 'message' => 'Bot token not configured.']);
        }

        try {
            $payload = ['url' => $data['webhook_url']];
            if (! empty($secret)) {
                $payload['secret_token'] = $secret;
            }

            $response = Http::timeout(10)->post(
                "https://api.telegram.org/bot{$token}/setWebhook",
                $payload,
            );

            $body = $response->json();

            if ($body['ok'] ?? false) {
                return response()->json([
                    'success'     => true,
                    'description' => $body['description'] ?? 'Webhook updated.',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $body['description'] ?? 'Telegram returned an error.',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /** POST /prompts/global */
    public function saveGlobalPrompt(Request $request): RedirectResponse
    {
        $data = $request->validate(['prompt' => 'required|string']);

        $path = config('flamingdragon.llm.system_prompt_path');

        if (! is_writable(dirname($path))) {
            return back()->with('error', 'Cannot write to ' . $path . ' — check directory permissions.');
        }

        file_put_contents($path, $data['prompt']);

        return back()->with('success', 'Global system prompt saved.');
    }

    /** POST /prompts/command/{id} */
    public function saveCommandPrompt(Request $request, int $id): RedirectResponse
    {
        $command = AllowedCommand::findOrFail($id);
        $data    = $request->validate(['system_prompt' => 'nullable|string']);

        $command->update(['system_prompt' => $data['system_prompt'] ?? null]);

        return back()->with('success', "Prompt for /{$command->name} saved.");
    }
}
