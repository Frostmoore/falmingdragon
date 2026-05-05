@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<div x-data="dashboardApp()" x-init="init()" class="space-y-6">

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
                    <code class="text-sm text-brand">{{ $session->command }}</code>
                    <span class="text-xs text-gray-600 font-mono">{{ substr($session->session_uuid, 0, 8) }}…</span>
                    <span class="text-xs text-gray-600 ml-auto">{{ $session->created_at->diffForHumans() }}</span>
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
    <div class="flex flex-wrap gap-3 text-xs items-center">
        <button @click="showGenerator = true"
                class="px-4 py-2 bg-brand hover:bg-orange-600 text-white rounded-lg transition font-semibold flex items-center gap-2 text-sm">
            <span>✦</span> Crea Skill / Tool con AI
        </button>
        <a href="{{ route('api.fd.health') }}" target="_blank"
           class="px-3 py-2 bg-gray-800 hover:bg-gray-700 rounded text-gray-400 hover:text-white transition">
            Health Check
        </a>
        <a href="{{ route('api.fd.stats') }}" target="_blank"
           class="px-3 py-2 bg-gray-800 hover:bg-gray-700 rounded text-gray-400 hover:text-white transition">
            Stats API
        </a>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- AI Generator Modal                                                --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    <template x-teleport="body">
    <div x-show="showGenerator" x-cloak
         class="fixed inset-0 z-50 flex items-start justify-center bg-black/70 backdrop-blur-sm overflow-y-auto p-4"
         @keydown.escape.window="showGenerator = false">

        <div class="relative w-full max-w-3xl my-8 bg-gray-900 border border-gray-700 rounded-2xl shadow-2xl"
             @click.stop>

            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-800">
                <div>
                    <h2 class="text-base font-bold text-gray-100">✦ Genera con Claude Opus</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Descrivi cosa vuoi creare — l'AI scrive codice e documentazione</p>
                </div>
                <button @click="showGenerator = false" class="text-gray-600 hover:text-gray-300 text-xl leading-none">✕</button>
            </div>

            {{-- Body --}}
            <div class="px-6 py-5 space-y-5">

                {{-- Type selector --}}
                <div>
                    <label class="block text-xs text-gray-400 font-semibold mb-2">Cosa vuoi creare?</label>
                    <div class="grid grid-cols-3 gap-2">
                        <template x-for="opt in [{v:'skill',icon:'📄',label:'Skill',sub:'SKILL.md + comando'},{v:'tool',icon:'⚙️',label:'Tool',sub:'Codice PHP + registro'},{v:'skilltool',icon:'✦',label:'Skill + Tool',sub:'Entrambi insieme'}]" :key="opt.v">
                            <label :class="form.type === opt.v ? 'border-brand bg-brand/10 text-brand' : 'border-gray-700 text-gray-400 hover:border-gray-500'"
                                   class="cursor-pointer border rounded-lg px-3 py-3 text-center text-xs transition select-none">
                                <input type="radio" x-model="form.type" :value="opt.v" class="hidden">
                                <div class="text-lg mb-1" x-text="opt.icon"></div>
                                <div class="font-semibold" x-text="opt.label"></div>
                                <div class="text-gray-600 text-[10px] mt-0.5" x-text="opt.sub"></div>
                            </label>
                        </template>
                    </div>
                </div>

                {{-- Name + Tools row --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-gray-400 font-semibold mb-1">
                            Nome <span class="text-gray-600 font-normal">(snake_case)</span>
                        </label>
                        <input type="text" x-model="form.name"
                               placeholder="es. invoice_generator"
                               @input="form.name = $event.target.value.toLowerCase().replace(/[^a-z0-9_]/g,'_').replace(/__+/g,'_')"
                               class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-200 font-mono focus:outline-none focus:border-brand">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 font-semibold mb-1">
                            Tool da concedere <span class="text-gray-600 font-normal">(opzionale)</span>
                        </label>
                        <input type="text" x-model="form.tools"
                               placeholder="http_get, memory_read, …"
                               class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-200 font-mono focus:outline-none focus:border-brand">
                    </div>
                </div>

                {{-- Tool quick chips --}}
                <div class="flex flex-wrap gap-1.5" x-show="availableTools.length > 0">
                    <span class="text-[10px] text-gray-600 self-center mr-1">Aggiungi tool:</span>
                    <template x-for="t in availableTools.slice(0, 20)" :key="t.name">
                        <button type="button" @click="toggleTool(t.name)"
                                :class="isToolSelected(t.name)
                                    ? 'bg-brand/20 border-brand text-brand'
                                    : 'bg-gray-800 border-gray-700 text-gray-500 hover:border-gray-500'"
                                class="border rounded px-2 py-0.5 text-[10px] font-mono transition">
                            <span x-text="t.name"></span>
                        </button>
                    </template>
                </div>

                {{-- Description --}}
                <div>
                    <label class="block text-xs text-gray-400 font-semibold mb-1">
                        Descrizione dettagliata
                        <span class="text-gray-600 font-normal">— più contesto dai, meglio è il risultato</span>
                    </label>
                    <textarea x-model="form.description" rows="5"
                              placeholder="Descrivi cosa deve fare: input attesi, output, API da usare, come gestire gli errori, esempi concreti di utilizzo..."
                              class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-sm text-gray-200 focus:outline-none focus:border-brand resize-y leading-relaxed"></textarea>
                </div>

                {{-- Extra instructions (collapsible) --}}
                <div x-data="{ open: false }">
                    <button type="button" @click="open = !open"
                            class="text-xs text-gray-600 hover:text-gray-400 transition flex items-center gap-1">
                        <span x-text="open ? '▾' : '▸'"></span>
                        Istruzioni aggiuntive per Claude (opzionale)
                    </button>
                    <div x-show="open" x-cloak class="mt-2">
                        <textarea x-model="form.extra_prompt" rows="3"
                                  placeholder="Es: usa l'env var MYAPP_TOKEN, gestisci anche i webhook, aggiungi logging, supporta sia GET che POST..."
                                  class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-200 focus:outline-none focus:border-brand resize-y"></textarea>
                    </div>
                </div>

                {{-- Error --}}
                <div x-show="genError" x-cloak
                     class="bg-red-900/40 border border-red-700 text-red-300 text-xs rounded-lg px-4 py-3"
                     x-text="genError"></div>

                {{-- Results --}}
                <div x-show="genResult" x-cloak class="space-y-4">
                    <div class="border-t border-gray-800 pt-4">
                        <p class="text-xs font-semibold text-green-400 mb-3">✓ Generazione completata</p>

                        <template x-if="genResult && genResult.skill">
                            <div class="space-y-1 mb-4 text-xs">
                                <div class="flex items-center gap-3">
                                    <span class="text-gray-400">📄 Skill:</span>
                                    <code class="text-brand" x-text="genResult.skill.name"></code>
                                    <a :href="`/skills/${genResult.skill.id}`" class="text-brand hover:underline">Apri →</a>
                                </div>
                                <details class="mt-1">
                                    <summary class="text-gray-600 cursor-pointer hover:text-gray-400">Mostra SKILL.md</summary>
                                    <pre class="mt-2 bg-gray-950 border border-gray-800 rounded p-3 text-xs text-gray-300 overflow-auto whitespace-pre-wrap max-h-48" x-text="genResult.skill.content"></pre>
                                </details>
                            </div>
                        </template>

                        <template x-if="genResult && genResult.tool">
                            <div class="space-y-1 mb-4 text-xs">
                                <div class="flex items-center flex-wrap gap-3">
                                    <span class="text-gray-400">⚙️ Tool:</span>
                                    <code class="text-brand" x-text="genResult.tool.name"></code>
                                    <span class="text-yellow-600">● Disabilitato — abilita dopo verifica</span>
                                    <a :href="`/tools/${genResult.tool.id}`" class="text-brand hover:underline">Configura →</a>
                                </div>
                                <details class="mt-1">
                                    <summary class="text-gray-600 cursor-pointer hover:text-gray-400">Mostra codice PHP</summary>
                                    <pre class="mt-2 bg-gray-950 border border-gray-800 rounded p-3 text-xs text-green-300 font-mono overflow-auto whitespace-pre-wrap max-h-48" x-text="genResult.tool.code"></pre>
                                </details>
                            </div>
                        </template>

                        <template x-if="genResult && genResult.command">
                            <div class="flex items-center gap-3 text-xs">
                                <span class="text-gray-400">💬 Comando:</span>
                                <code class="text-brand" x-text="'/' + genResult.command.name"></code>
                                <a href="/commands" class="text-brand hover:underline">Vedi comandi →</a>
                            </div>
                        </template>

                        <template x-if="genResult && genResult.errors && genResult.errors.length > 0">
                            <div class="mt-2 space-y-1">
                                <template x-for="e in genResult.errors" :key="e">
                                    <div class="text-xs text-red-400" x-text="'⚠ ' + e"></div>
                                </template>
                            </div>
                        </template>
                    </div>

                    <button @click="resetGen()" class="text-xs text-gray-500 hover:text-gray-300 transition">
                        ← Crea un altro
                    </button>
                </div>

            </div>

            {{-- Footer --}}
            <div x-show="!genResult" class="px-6 py-4 border-t border-gray-800 flex items-center justify-between gap-4">
                <p class="text-xs text-gray-600">
                    Powered by <span class="text-brand font-semibold">Claude Opus</span> — 30–90 secondi
                </p>
                <div class="flex gap-3">
                    <button @click="showGenerator = false"
                            class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-400 text-xs rounded-lg transition">
                        Annulla
                    </button>
                    <button @click="runGenerator()"
                            :disabled="genLoading || form.name.length < 2 || form.description.length < 10"
                            class="px-5 py-2 bg-brand hover:bg-orange-600 disabled:opacity-40 text-white text-xs font-semibold rounded-lg transition flex items-center gap-2">
                        <template x-if="!genLoading"><span>✦ Genera</span></template>
                        <template x-if="genLoading">
                            <span class="flex items-center gap-2">
                                <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                Claude sta scrivendo…
                            </span>
                        </template>
                    </button>
                </div>
            </div>

        </div>
    </div>
    </template>

</div>
@endsection

@push('scripts')
<script>
function dashboardApp() {
    return {
        // generator state
        showGenerator: false,
        genLoading:    false,
        genError:      '',
        genResult:     null,
        availableTools: [],
        form: {
            type:         'skilltool',
            name:         '',
            description:  '',
            tools:        '',
            extra_prompt: '',
        },

        async init() {
            try {
                const r = await fetch('{{ route('generator.tools-list') }}');
                this.availableTools = await r.json();
            } catch(e) { /* non-critical */ }
        },

        resetGen() {
            this.genError  = '';
            this.genResult = null;
            this.form = { type: 'skilltool', name: '', description: '', tools: '', extra_prompt: '' };
        },

        isToolSelected(name) {
            return this.form.tools.split(',').map(t => t.trim()).filter(Boolean).includes(name);
        },

        toggleTool(name) {
            let arr = this.form.tools ? this.form.tools.split(',').map(t => t.trim()).filter(Boolean) : [];
            arr = arr.includes(name) ? arr.filter(t => t !== name) : [...arr, name];
            this.form.tools = arr.join(', ');
        },

        async runGenerator() {
            this.genLoading = true;
            this.genError   = '';
            this.genResult  = null;

            try {
                const resp = await fetch('{{ route('generator.run') }}', {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify(this.form),
                });

                const data = await resp.json();

                if (! resp.ok) {
                    this.genError = data.error ?? 'Errore sconosciuto.';
                } else {
                    this.genResult = data;
                }
            } catch(e) {
                this.genError = e.message;
            }

            this.genLoading = false;
        },
    };
}
</script>
@endpush
