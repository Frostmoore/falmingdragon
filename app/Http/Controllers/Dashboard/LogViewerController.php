<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AgentSession;
use App\Models\ExecutionLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LogViewerController extends Controller
{
    /** GET /logs */
    public function index(Request $request): View
    {
        $sessions = AgentSession::orderByDesc('created_at')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('command'), fn ($q) => $q->where('command', 'like', '%' . $request->input('command') . '%'))
            ->paginate(25);

        return view('dashboard.logs', compact('sessions'));
    }

    /** GET /logs/{uuid} */
    public function show(string $uuid): View
    {
        $session = AgentSession::where('session_uuid', $uuid)
            ->with(['logs' => fn ($q) => $q->orderBy('step_number')])
            ->firstOrFail();

        return view('dashboard.log-detail', compact('session'));
    }

    /** GET /logs/{uuid}/stream (SSE) */
    public function stream(string $uuid)
    {
        $session = AgentSession::where('session_uuid', $uuid)->firstOrFail();

        return response()->stream(function () use ($session) {
            $lastId = 0;
            $maxIter = 120; // 2 minutes max

            for ($i = 0; $i < $maxIter; $i++) {
                $logs = ExecutionLog::where('session_id', $session->id)
                    ->where('id', '>', $lastId)
                    ->orderBy('id')
                    ->get();

                foreach ($logs as $log) {
                    $lastId = $log->id;
                    $data   = json_encode([
                        'step'        => $log->step_number,
                        'action_type' => $log->action_type->value,
                        'tool_name'   => $log->tool_name,
                        'output'      => substr($log->output_data ?? '', 0, 1000),
                        'duration_ms' => $log->duration_ms,
                    ]);
                    echo "data: {$data}\n\n";
                }

                $session->refresh();
                if ($session->isTerminal()) {
                    echo "data: {\"done\":true}\n\n";
                    break;
                }

                ob_flush();
                flush();
                sleep(1);
            }
        }, 200, [
            'Content-Type'  => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
