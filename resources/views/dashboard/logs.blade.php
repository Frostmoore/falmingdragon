@extends('layouts.app')
@section('title', 'Execution Logs')

@section('content')
<div class="space-y-4">

    {{-- Filters --}}
    <form method="GET" class="flex gap-3 items-center">
        <input type="text" name="command" value="{{ request('command') }}"
               placeholder="Filter by command…"
               class="bg-gray-800 border border-gray-700 rounded px-3 py-1.5 text-sm text-gray-200 placeholder-gray-600 focus:outline-none focus:border-brand w-48">
        <select name="status" class="bg-gray-800 border border-gray-700 rounded px-3 py-1.5 text-sm text-gray-200 focus:outline-none">
            <option value="">All statuses</option>
            @foreach(['queued','running','completed','failed','timeout','cancelled'] as $s)
                <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
        <button type="submit" class="px-3 py-1.5 bg-brand hover:bg-brand-dark text-white text-sm rounded transition">Filter</button>
        <a href="{{ route('logs.index') }}" class="text-xs text-gray-500 hover:text-gray-300">Reset</a>
    </form>

    {{-- Table --}}
    <div class="bg-gray-900 border border-gray-800 rounded-lg overflow-hidden">
        <table class="w-full text-xs">
            <thead class="bg-gray-800 text-gray-500 uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-left">Command</th>
                    <th class="px-4 py-3 text-left">UUID</th>
                    <th class="px-4 py-3 text-left">Mode</th>
                    <th class="px-4 py-3 text-right">Tokens</th>
                    <th class="px-4 py-3 text-right">Duration</th>
                    <th class="px-4 py-3 text-right">Created</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
                @forelse($sessions as $session)
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
                    <tr class="hover:bg-gray-800/50">
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded text-xs {{ $color }}">{{ $session->status->label() }}</span>
                        </td>
                        <td class="px-4 py-3 text-brand font-mono">{{ $session->command }}</td>
                        <td class="px-4 py-3 text-gray-600 font-mono">{{ substr($session->session_uuid, 0, 12) }}…</td>
                        <td class="px-4 py-3 text-gray-500">{{ $session->execution_mode->value }}</td>
                        <td class="px-4 py-3 text-right text-purple-400">{{ number_format($session->totalTokens()) }}</td>
                        <td class="px-4 py-3 text-right text-gray-500">
                            {{ $session->durationSeconds() !== null ? $session->durationSeconds() . 's' : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right text-gray-600">{{ $session->created_at->diffForHumans() }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('logs.show', $session->session_uuid) }}" class="text-brand hover:underline">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-gray-600">No sessions found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="text-xs">{{ $sessions->withQueryString()->links() }}</div>

</div>
@endsection
