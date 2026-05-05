<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Enums\AgentStatus;
use App\Http\Controllers\Controller;
use App\Models\AgentSession;
use App\Services\Telegram\TelegramService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly TelegramService $telegram,
    ) {}

    /** GET / */
    public function index(): View
    {
        $recentSessions = AgentSession::with('logs')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $runningCount = AgentSession::where('status', AgentStatus::Running)->count();

        $tokenStats = AgentSession::selectRaw(
            'sum(tokens_input) as total_in, sum(tokens_output) as total_out'
        )->where('created_at', '>=', now()->startOfDay())->first();

        // Read from config/env — same source used by LlmRouter at runtime
        $defaultProvider = (object) [
            'name'          => config('flamingdragon.llm.default_provider', 'anthropic'),
            'default_model' => config('flamingdragon.llm.default_model', ''),
        ];

        $webhookInfo = [];
        try {
            $webhookInfo = $this->telegram->getWebhookInfo()['result'] ?? [];
        } catch (\Throwable) {
            // Telegram may not be configured yet
        }

        return view('dashboard.index', compact(
            'recentSessions',
            'runningCount',
            'tokenStats',
            'defaultProvider',
            'webhookInfo',
        ));
    }
}
