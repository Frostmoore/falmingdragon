<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgentSession;
use App\Services\Agent\SessionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    public function __construct(
        private readonly SessionManager $sessions,
    ) {}

    /** GET /api/fd/sessions */
    public function index(Request $request): JsonResponse
    {
        $sessions = AgentSession::with('logs')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($sessions);
    }

    /** GET /api/fd/sessions/{uuid} */
    public function show(string $uuid): JsonResponse
    {
        $session = AgentSession::where('session_uuid', $uuid)
            ->with('logs')
            ->firstOrFail();

        return response()->json($session);
    }

    /** POST /api/fd/sessions/{uuid}/cancel */
    public function cancel(string $uuid): JsonResponse
    {
        $session = AgentSession::where('session_uuid', $uuid)->firstOrFail();

        if ($session->isTerminal()) {
            return response()->json(['error' => 'Session is already in a terminal state.'], 422);
        }

        $this->sessions->markCancelled($session);

        return response()->json(['message' => 'Session cancelled.']);
    }
}
