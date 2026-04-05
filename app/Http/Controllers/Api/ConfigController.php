<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AllowedCommand;
use App\Models\LlmProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    // -------------------------------------------------------------------------
    // Allowed Commands
    // -------------------------------------------------------------------------

    /** GET /api/fd/commands */
    public function commandsIndex(): JsonResponse
    {
        return response()->json(AllowedCommand::orderBy('category')->orderBy('name')->get());
    }

    /** POST /api/fd/commands */
    public function commandsStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'           => 'required|string|max:100|unique:allowed_commands,name',
            'description'    => 'required|string',
            'category'       => 'required|string|max:50',
            'execution_mode' => 'required|in:sync,async,auto',
            'timeout_seconds' => 'nullable|integer|min:1|max:3600',
            'tools_allowed'  => 'nullable|array',
            'is_dangerous'   => 'nullable|boolean',
            'is_active'      => 'nullable|boolean',
            'skill_required' => 'nullable|string|max:100',
        ]);

        $command = AllowedCommand::create($data);
        return response()->json($command, 201);
    }

    /** PUT /api/fd/commands/{id} */
    public function commandsUpdate(Request $request, int $id): JsonResponse
    {
        $command = AllowedCommand::findOrFail($id);

        $data = $request->validate([
            'description'    => 'nullable|string',
            'category'       => 'nullable|string|max:50',
            'execution_mode' => 'nullable|in:sync,async,auto',
            'timeout_seconds' => 'nullable|integer|min:1|max:3600',
            'tools_allowed'  => 'nullable|array',
            'is_dangerous'   => 'nullable|boolean',
            'is_active'      => 'nullable|boolean',
            'skill_required' => 'nullable|string|max:100',
        ]);

        $command->update($data);
        return response()->json($command);
    }

    /** DELETE /api/fd/commands/{id} */
    public function commandsDestroy(int $id): JsonResponse
    {
        AllowedCommand::findOrFail($id)->delete();
        return response()->json(['message' => 'Command deleted.']);
    }

    // -------------------------------------------------------------------------
    // LLM Providers
    // -------------------------------------------------------------------------

    /** GET /api/fd/providers */
    public function providersIndex(): JsonResponse
    {
        return response()->json(LlmProvider::orderBy('name')->get());
    }

    /** PUT /api/fd/providers/{id} */
    public function providersUpdate(Request $request, int $id): JsonResponse
    {
        $provider = LlmProvider::findOrFail($id);

        $data = $request->validate([
            'display_name'  => 'nullable|string|max:255',
            'api_base_url'  => 'nullable|url|max:500',
            'default_model' => 'nullable|string|max:100',
            'is_active'     => 'nullable|boolean',
            'config'        => 'nullable|array',
        ]);

        $provider->update($data);
        return response()->json($provider);
    }

    /** POST /api/fd/providers/{id}/set-default */
    public function providersSetDefault(int $id): JsonResponse
    {
        LlmProvider::where('is_default', true)->update(['is_default' => false]);
        $provider = LlmProvider::findOrFail($id);
        $provider->update(['is_default' => true]);
        return response()->json(['message' => "Provider '{$provider->name}' set as default."]);
    }

    // -------------------------------------------------------------------------
    // Stats & Health
    // -------------------------------------------------------------------------

    /** GET /api/fd/stats */
    public function stats(): JsonResponse
    {
        $sessions = \App\Models\AgentSession::selectRaw(
            'status, count(*) as count, sum(tokens_input) as tokens_in, sum(tokens_output) as tokens_out'
        )->groupBy('status')->get();

        return response()->json([
            'sessions' => $sessions,
            'memory_entries' => \App\Models\Memory::count(),
            'active_skills'  => \App\Models\Skill::where('is_active', true)->count(),
            'active_tools'   => \App\Models\Tool::where('is_active', true)->count(),
        ]);
    }

    /** GET /api/fd/health */
    public function health(): JsonResponse
    {
        return response()->json([
            'status'    => 'ok',
            'timestamp' => now()->toIso8601String(),
            'queue'     => $this->checkQueueHealth(),
        ]);
    }

    private function checkQueueHealth(): string
    {
        try {
            $pending = \Illuminate\Support\Facades\DB::table('jobs')->count();
            return "ok ({$pending} pending jobs)";
        } catch (\Throwable) {
            return 'unknown';
        }
    }
}
