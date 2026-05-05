@extends('layouts.app')
@section('title', 'Settings')

@section('content')
<div class="space-y-8">

    {{-- Setup Wizard Banner --}}
    <div class="bg-gradient-to-r from-orange-950 to-gray-900 border border-orange-800/50 rounded-xl p-5 flex items-center gap-5">
        <div class="text-4xl shrink-0">🔥</div>
        <div class="flex-1">
            <h2 class="text-base font-bold text-white mb-1">Configurazione guidata</h2>
            <p class="text-sm text-gray-400">
                Prima volta? Usa il wizard per configurare Telegram, il provider AI, il webhook e tutto il resto passo per passo,
                con istruzioni dettagliate per ogni impostazione.
            </p>
        </div>
        <a href="{{ route('wizard.index') }}"
           class="shrink-0 px-5 py-2.5 bg-brand hover:bg-orange-600 text-white text-sm font-semibold rounded-lg transition shadow-lg shadow-orange-900/30 flex items-center gap-2">
            Avvia Wizard →
        </a>
    </div>

    {{-- LLM Providers --}}
    <section>
        <h2 class="text-sm font-semibold text-gray-300 mb-3">Large Language Model Providers</h2>
        <div class="bg-gray-900 border border-gray-800 rounded-lg divide-y divide-gray-800">
            @foreach($providers as $provider)
                <div class="px-4 py-4 flex items-center gap-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-1">
                            <span class="font-semibold text-gray-200">{{ $provider->display_name }}</span>
                            <code class="text-xs text-brand">{{ $provider->name }}</code>
                            @if($provider->is_default)
                                <span class="text-xs bg-brand/20 text-brand px-2 py-0.5 rounded">Default</span>
                            @endif
                            <span class="{{ $provider->is_active ? 'text-green-400' : 'text-gray-600' }} text-xs">
                                {{ $provider->is_active ? '● Active' : '○ Inactive' }}
                            </span>
                        </div>
                        <div class="text-xs text-gray-500">
                            Model: <code class="text-gray-400">{{ $provider->default_model }}</code>
                            &nbsp;|&nbsp;
                            URL: <code class="text-gray-600">{{ $provider->api_base_url }}</code>
                            @if($provider->api_key_env)
                                &nbsp;|&nbsp;
                                Key env: <code class="text-gray-600">{{ $provider->api_key_env }}</code>
                                @php $keySet = !empty(env($provider->api_key_env)); @endphp
                                <span class="{{ $keySet ? 'text-green-500' : 'text-red-500' }}">
                                    {{ $keySet ? '(set)' : '(missing!)' }}
                                </span>
                            @endif
                        </div>
                    </div>
                    @if(!$provider->is_default)
                        <button onclick="setDefault({{ $provider->id }})"
                                class="px-3 py-1 text-xs bg-gray-700 hover:bg-gray-600 text-gray-300 rounded transition">
                            Set Default
                        </button>
                    @endif
                </div>
            @endforeach
        </div>
        <p class="text-xs text-gray-600 mt-2">API keys are read from environment variables. Edit your .env file to set them.</p>
    </section>

    {{-- LLM Default Model --}}
    <section x-data="{
        provider: '{{ addslashes($defaultProvider) }}',
        model:    '{{ addslashes($defaultModel) }}',
        saving: false,
        status: null,
        models: {
            anthropic: [
                { value: 'claude-opus-4-6',            label: 'claude-opus-4-6 — massima intelligenza' },
                { value: 'claude-sonnet-4-6',           label: 'claude-sonnet-4-6 — bilanciato' },
                { value: 'claude-haiku-4-5-20251001',   label: 'claude-haiku-4-5 — veloce & economico' },
                { value: 'claude-3-5-sonnet-20241022',  label: 'claude-3-5-sonnet-20241022' },
                { value: 'claude-3-5-haiku-20241022',   label: 'claude-3-5-haiku-20241022' },
                { value: 'claude-3-opus-20240229',      label: 'claude-3-opus-20240229' },
            ],
            openai: [
                { value: 'gpt-4o',          label: 'gpt-4o — più capace' },
                { value: 'gpt-4o-mini',     label: 'gpt-4o-mini — veloce & economico' },
                { value: 'gpt-4-turbo',     label: 'gpt-4-turbo' },
                { value: 'gpt-4',           label: 'gpt-4' },
                { value: 'gpt-3.5-turbo',   label: 'gpt-3.5-turbo' },
            ],
            ollama: [],
        },
        get currentModels() {
            return this.models[this.provider] ?? [];
        },
        async save() {
            this.saving = true;
            this.status = null;
            try {
                const res = await fetch('{{ route('wizard.save-env') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ values: {
                        FD_LLM_DEFAULT_PROVIDER: this.provider,
                        FD_LLM_DEFAULT_MODEL:    this.model,
                    }}),
                });
                const data = await res.json();
                this.status = data.success ? 'ok' : ('error:' + (data.message ?? 'Errore sconosciuto'));
            } catch(e) {
                this.status = 'error:' + e.message;
            } finally {
                this.saving = false;
            }
        },
    }">
        <h2 class="text-sm font-semibold text-gray-300 mb-3">LLM — Modello predefinito</h2>
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-4 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-2">Provider</label>
                    <select x-model="provider" @change="model = currentModels[0]?.value ?? model"
                            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm font-mono text-gray-200 focus:outline-none focus:border-brand">
                        @foreach($providers as $p)
                            <option value="{{ $p->name }}">{{ $p->display_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-2">Modello</label>

                    {{-- Dropdown per anthropic/openai --}}
                    <template x-if="currentModels.length > 0">
                        <select x-model="model"
                                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm font-mono text-gray-200 focus:outline-none focus:border-brand">
                            <template x-for="m in currentModels" :key="m.value">
                                <option :value="m.value" x-text="m.label"></option>
                            </template>
                        </select>
                    </template>

                    {{-- Campo libero per ollama --}}
                    <template x-if="currentModels.length === 0">
                        <input type="text" x-model="model"
                               placeholder="es. llama3, mistral, phi3"
                               class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm font-mono text-gray-200 placeholder-gray-600 focus:outline-none focus:border-brand">
                    </template>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <button @click="save()" :disabled="saving"
                        class="px-4 py-2 bg-brand hover:bg-orange-600 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition">
                    <span x-show="!saving">Salva</span>
                    <span x-show="saving" class="animate-pulse">Salvataggio…</span>
                </button>
                <span x-show="status === 'ok'" class="text-green-400 text-sm">✓ Salvato nel .env</span>
                <span x-show="status?.startsWith('error')" class="text-red-400 text-sm"
                      x-text="status?.replace('error:', '✗ ')"></span>
            </div>

            <p class="text-xs text-gray-600">
                Attuale: <code class="text-gray-400">{{ $defaultProvider }} / {{ $defaultModel }}</code>
                — modifiche effettive al prossimo riavvio del worker (o config:clear).
            </p>
        </div>
    </section>

    {{-- System Info --}}
    <section>
        <h2 class="text-sm font-semibold text-gray-300 mb-3">System Configuration</h2>
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-4 grid grid-cols-2 lg:grid-cols-3 gap-4 text-xs">
            @php
                $cfg = config('flamingdragon');
            @endphp
            <div>
                <div class="text-gray-500 mb-1">Default Timeout</div>
                <div class="text-gray-200">{{ $cfg['execution']['default_timeout'] }}s</div>
            </div>
            <div>
                <div class="text-gray-500 mb-1">Max Concurrent Agents</div>
                <div class="text-gray-200">{{ $cfg['execution']['max_concurrent_agents'] }}</div>
            </div>
            <div>
                <div class="text-gray-500 mb-1">Max Tool Calls / Session</div>
                <div class="text-gray-200">{{ $cfg['security']['max_tool_calls_per_session'] }}</div>
            </div>
            <div>
                <div class="text-gray-500 mb-1">Allow-list Strict</div>
                <div class="{{ $cfg['security']['allow_list_strict'] ? 'text-green-400' : 'text-yellow-400' }}">
                    {{ $cfg['security']['allow_list_strict'] ? 'Enabled' : 'Disabled' }}
                </div>
            </div>
            <div>
                <div class="text-gray-500 mb-1">Allowed Chat IDs</div>
                <div class="text-gray-200 font-mono">{{ implode(', ', $cfg['telegram']['allowed_chat_ids']) ?: 'None configured' }}</div>
            </div>
            <div>
                <div class="text-gray-500 mb-1">Queue Driver</div>
                <div class="text-gray-200">{{ config('queue.default') }}</div>
            </div>
        </div>
    </section>

    {{-- Telegram Webhook --}}
    <section x-data="{
        webhookUrl: '{{ addslashes(config('flamingdragon.telegram.bot_token') ? url('/api/telegram/webhook') : '') }}',
        saving: false,
        result: null,

        async update() {
            this.saving = true;
            this.result = null;
            try {
                const r = await fetch('{{ route('settings.update-webhook') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ webhook_url: this.webhookUrl }),
                });
                this.result = await r.json();
            } catch(e) {
                this.result = { success: false, message: e.message };
            } finally {
                this.saving = false;
            }
        },
    }">
        <h2 class="text-sm font-semibold text-gray-300 mb-3">Telegram Webhook</h2>
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-4 space-y-4">

            <div class="grid grid-cols-1 gap-2 text-xs">
                <div class="flex gap-3">
                    <span class="text-gray-500 w-32">Bot Token</span>
                    @php $hasToken = !empty(config('flamingdragon.telegram.bot_token')); @endphp
                    <span class="{{ $hasToken ? 'text-green-400' : 'text-red-400' }}">
                        {{ $hasToken ? '● Configured' : '✗ Not set' }}
                    </span>
                </div>
                <div class="flex gap-3">
                    <span class="text-gray-500 w-32">Current URL</span>
                    <code class="text-gray-400">{{ url('/api/telegram/webhook') }}</code>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-400 mb-2">
                    Webhook URL
                    <span class="text-gray-600 font-normal ml-1">— update this when your ngrok/domain changes</span>
                </label>
                <div class="flex gap-2">
                    <input type="text" x-model="webhookUrl"
                           placeholder="https://xxxx.ngrok-free.app/api/telegram/webhook"
                           class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm font-mono text-gray-200 placeholder-gray-600 focus:outline-none focus:border-brand">
                    <button @click="update()" :disabled="saving"
                            class="px-4 py-2 bg-brand hover:bg-orange-600 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition whitespace-nowrap">
                        <span x-show="!saving">Update Webhook</span>
                        <span x-show="saving" class="animate-pulse">Updating…</span>
                    </button>
                </div>
            </div>

            <div x-show="result" x-cloak class="text-sm">
                <div x-show="result?.success" class="text-green-400">
                    ✓ <span x-text="result?.description ?? 'Webhook updated successfully'"></span>
                </div>
                <div x-show="!result?.success" class="text-red-400">
                    ✗ <span x-text="result?.message"></span>
                </div>
            </div>

            <p class="text-xs text-gray-600">
                Calls Telegram's <code class="text-gray-500">setWebhook</code> API directly.
                Use this every time your ngrok URL changes without having to redo the wizard.
            </p>
        </div>
    </section>

</div>
@endsection

@push('scripts')
<script>
async function setDefault(id) {
    await fetch(`/api/fd/providers/${id}/set-default`, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } });
    location.reload();
}
</script>
@endpush
