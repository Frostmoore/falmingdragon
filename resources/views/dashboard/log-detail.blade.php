@extends('layouts.app')
@section('title', 'Session: ' . substr($session->session_uuid, 0, 8))

@section('content')
<div class="space-y-4" x-data="logStream('{{ $session->session_uuid }}', {{ $session->isTerminal() ? 'true' : 'false' }})">

    {{-- Session header --}}
    <div class="bg-gray-900 border border-gray-800 rounded-lg p-4 grid grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
        <div>
            <div class="text-gray-500 mb-1">Command</div>
            <code class="text-brand text-sm">{{ $session->command }}</code>
        </div>
        <div>
            <div class="text-gray-500 mb-1">Status</div>
            <span class="px-2 py-0.5 rounded
                @if($session->status->value === 'completed') bg-green-900 text-green-300
                @elseif($session->status->value === 'running') bg-blue-900 text-blue-300
                @elseif($session->status->value === 'failed') bg-red-900 text-red-300
                @else bg-gray-700 text-gray-400 @endif">
                {{ $session->status->label() }}
            </span>
        </div>
        <div>
            <div class="text-gray-500 mb-1">Tokens</div>
            <span class="text-purple-400">{{ number_format($session->totalTokens()) }}</span>
        </div>
        <div>
            <div class="text-gray-500 mb-1">Duration</div>
            <span class="text-gray-300">
                {{ $session->durationSeconds() !== null ? $session->durationSeconds() . 's' : '—' }}
            </span>
        </div>
    </div>

    @if($session->result_summary)
    <div class="bg-gray-900 border border-gray-800 rounded-lg p-4">
        <div class="text-xs text-gray-500 mb-2">Result Summary</div>
        <div class="text-sm text-gray-200 whitespace-pre-wrap">{{ $session->result_summary }}</div>
    </div>
    @endif

    @if($session->error_message)
    <div class="bg-red-950 border border-red-800 rounded-lg p-4">
        <div class="text-xs text-red-400 mb-2">Error</div>
        <div class="text-sm text-red-300 font-mono">{{ $session->error_message }}</div>
    </div>
    @endif

    {{-- Execution steps --}}
    <div class="bg-gray-900 border border-gray-800 rounded-lg">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-800">
            <h2 class="text-sm font-semibold text-gray-300">Execution Steps</h2>
            @if(!$session->isTerminal())
                <span x-show="!done" class="text-xs text-blue-400 animate-pulse">● Live</span>
            @endif
        </div>

        <div class="divide-y divide-gray-800 text-xs font-mono" id="steps-container">
            @foreach($session->logs as $log)
                <div class="px-4 py-3 flex gap-4">
                    <span class="text-gray-600 w-6 text-right shrink-0">{{ $log->step_number }}</span>
                    <span class="px-1.5 py-0.5 rounded shrink-0
                        @if($log->action_type->value === 'llm_call') bg-purple-900 text-purple-300
                        @elseif($log->action_type->value === 'tool_use') bg-blue-900 text-blue-300
                        @elseif($log->action_type->value === 'error') bg-red-900 text-red-300
                        @else bg-gray-700 text-gray-400 @endif">
                        {{ $log->action_type->label() }}
                    </span>
                    @if($log->tool_name)
                        <span class="text-brand">{{ $log->tool_name }}</span>
                    @endif
                    <span class="text-gray-500 flex-1 truncate">{{ substr($log->output_data ?? '', 0, 120) }}</span>
                    <span class="text-gray-700 shrink-0">{{ $log->duration_ms }}ms</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Raw input --}}
    <details class="bg-gray-900 border border-gray-800 rounded-lg">
        <summary class="px-4 py-3 text-sm text-gray-400 cursor-pointer hover:text-gray-200">Raw Input</summary>
        <div class="px-4 pb-4 text-xs text-gray-500 font-mono whitespace-pre-wrap">{{ $session->raw_input }}</div>
    </details>

</div>
@endsection

@push('scripts')
<script>
function logStream(uuid, done) {
    return {
        done: done,
        init() {
            if (this.done) return;
            const es = new EventSource(`/logs/${uuid}/stream`);
            const container = document.getElementById('steps-container');
            es.onmessage = (e) => {
                const data = JSON.parse(e.data);
                if (data.done) { this.done = true; es.close(); return; }
                const div = document.createElement('div');
                div.className = 'px-4 py-3 flex gap-4 border-t border-gray-800';
                div.innerHTML = `<span class="text-gray-600 w-6 text-right shrink-0">${data.step}</span>
                    <span class="px-1.5 py-0.5 rounded bg-blue-900 text-blue-300 shrink-0 text-xs font-mono">${data.action_type}</span>
                    ${data.tool_name ? `<span class="text-orange-400 text-xs font-mono">${data.tool_name}</span>` : ''}
                    <span class="text-gray-500 flex-1 truncate text-xs font-mono">${data.output.substring(0, 120)}</span>
                    <span class="text-gray-700 shrink-0 text-xs font-mono">${data.duration_ms}ms</span>`;
                container.appendChild(div);
            };
            es.onerror = () => es.close();
        }
    }
}
</script>
@endpush
