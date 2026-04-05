@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="space-y-6">

    {{-- Status Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-4">
            <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Running Agents</div>
            <div class="text-3xl font-bold {{ $runningCount > 0 ? 'text-blue-400' : 'text-gray-400' }}">
                {{ $runningCount }}
            </div>
        </div>
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-4">
            <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Tokens In (today)</div>
            <div class="text-3xl font-bold text-purple-400">{{ number_format($tokenStats->total_in ?? 0) }}</div>
        </div>
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-4">
            <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Tokens Out (today)</div>
            <div class="text-3xl font-bold text-indigo-400">{{ number_format($tokenStats->total_out ?? 0) }}</div>
        </div>
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-4">
            <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Default Provider</div>
            <div class="text-lg font-bold text-brand">{{ $defaultProvider?->name ?? 'None' }}</div>
            <div class="text-xs text-gray-500">{{ $defaultProvider?->default_model ?? '' }}</div>
        </div>
    </div>

    {{-- Telegram Webhook Status --}}
    <div class="bg-gray-900 border border-gray-800 rounded-lg p-4">
        <h2 class="text-sm font-semibold text-gray-300 mb-3">Telegram Webhook</h2>
        @if(!empty($webhookInfo['url']))
            <div class="flex items-center gap-3 text-xs">
                <span class="text-green-400">● Connected</span>
                <span class="text-gray-500 font-mono">{{ $webhookInfo['url'] }}</span>
                <span class="text-gray-600">Pending: {{ $webhookInfo['pending_update_count'] ?? 0 }}</span>
            </div>
        @else
            <div class="text-xs text-yellow-500">⚠ Webhook not configured — go to Settings to set up your Telegram bot.</div>
        @endif
    </div>

    {{-- Recent Sessions --}}
    <div class="bg-gray-900 border border-gray-800 rounded-lg">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-800">
            <h2 class="text-sm font-semibold text-gray-300">Recent Sessions</h2>
            <a href="{{ route('logs.index') }}" class="text-xs text-brand hover:underline">View all</a>
        </div>

        <div class="divide-y divide-gray-800">
            @forelse($recentSessions as $session)
                <div class="px-4 py-3 flex items-center gap-4 hover:bg-gray-800/50 transition">
                    {{-- Status badge --}}
                    @php
                        $colors = [
                            'queued'    => 'bg-yellow-900 text-yellow-300',
                            'running'   => 'bg-blue-900 text-blue-300',
                            'completed' => 'bg-green-900 text-green-300',
                            'failed'    => 'bg-red-900 text-red-300',
                            'timeout'   => 'bg-orange-900 text-orange-300',
                            'cancelled' => 'bg-gray-700 text-gray-400',
                        ];
                        $color = $colors[$session->status->value] ?? 'bg-gray-700 text-gray-400';
                    @endphp
                    <span class="text-xs font-medium px-2 py-0.5 rounded {{ $color }}">
                        {{ $session->status->label() }}
                    </span>

                    {{-- Command --}}
                    <code class="text-sm text-brand">{{ $session->command }}</code>

                    {{-- UUID (short) --}}
                    <span class="text-xs text-gray-600 font-mono">{{ substr($session->session_uuid, 0, 8) }}…</span>

                    {{-- Time --}}
                    <span class="text-xs text-gray-600 ml-auto">{{ $session->created_at->diffForHumans() }}</span>

                    {{-- View link --}}
                    <a href="{{ route('logs.show', $session->session_uuid) }}"
                       class="text-xs text-gray-500 hover:text-brand">View →</a>
                </div>
            @empty
                <div class="px-4 py-8 text-center text-gray-600 text-sm">
                    No sessions yet. Send a command via Telegram to get started.
                </div>
            @endforelse
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="flex gap-3 text-xs">
        <a href="{{ route('api.fd.health') }}" target="_blank"
           class="px-3 py-2 bg-gray-800 hover:bg-gray-700 rounded text-gray-400 hover:text-white transition">
            Health Check
        </a>
        <a href="{{ route('api.fd.stats') }}" target="_blank"
           class="px-3 py-2 bg-gray-800 hover:bg-gray-700 rounded text-gray-400 hover:text-white transition">
            Stats API
        </a>
    </div>

</div>
@endsection
