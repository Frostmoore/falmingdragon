@extends('layouts.app')
@section('title', $tool->display_name . ' — Tool')

@section('content')
<div class="space-y-6"
     x-data="{
        aiInstruction: '',
        aiSuggestion: '',
        aiLoading: false,
        aiError: '',
        applyMsg: '',
        tab: 'config',
        async runAI() {
            this.aiLoading = true; this.aiError = ''; this.aiSuggestion = ''; this.applyMsg = '';
            try {
                const r = await fetch('{{ route('tools.ai-suggest', $tool->id) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ instruction: this.aiInstruction })
                });
                const j = await r.json();
                if (j.error) { this.aiError = j.error; } else { this.aiSuggestion = j.suggestion; }
            } catch(e) { this.aiError = e.message; }
            this.aiLoading = false;
        },
        async applyAI() {
            this.applyMsg = ''; this.aiError = '';
            try {
                const r = await fetch('{{ route('tools.ai-apply', $tool->id) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ code: this.aiSuggestion })
                });
                const j = await r.json();
                if (j.error) { this.aiError = j.error; } else { this.applyMsg = j.message; this.aiSuggestion = ''; this.aiInstruction = ''; }
            } catch(e) { this.aiError = e.message; }
        }
     }">

    {{-- Header --}}
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
            <div class="flex items-center gap-3 flex-wrap">
                <a href="{{ route('tools.index') }}" class="text-gray-600 hover:text-gray-400 text-xs">← Tools</a>
                <h1 class="text-lg font-semibold text-gray-100">{{ $tool->display_name }}</h1>
                <code class="text-brand text-sm">{{ $tool->name }}</code>
                @php
                    $rc = match($tool->risk_level->value) {
                        'safe'      => 'badge-safe',
                        'moderate'  => 'badge-moderate',
                        'dangerous' => 'badge-dangerous',
                        default     => 'badge-safe',
                    };
                @endphp
                <span class="{{ $rc }}">{{ $tool->risk_level->label() }}</span>
                <span class="text-xs text-gray-600">{{ $tool->type->label() }}</span>
                <span class="text-xs font-semibold {{ $tool->is_active ? 'text-green-400' : 'text-gray-600' }}">
                    {{ $tool->is_active ? '● Active' : '○ Inactive' }}
                </span>
            </div>
            <p class="mt-1 text-sm text-gray-400">{{ $tool->description }}</p>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-900/40 border border-green-700 text-green-300 text-xs rounded px-4 py-2">
            {{ session('success') }}
        </div>
    @endif

    {{-- Tab bar --}}
    <div class="flex gap-1 border-b border-gray-800 text-xs">
        @foreach(['config' => 'Configuration', 'code' => 'Implementation', 'ai' => 'Modify with AI', 'meta' => 'Metadata'] as $key => $label)
            <button @click="tab = '{{ $key }}'"
                    :class="tab === '{{ $key }}' ? 'border-b-2 border-brand text-brand' : 'text-gray-500 hover:text-gray-300'"
                    class="px-4 py-2 transition">{{ $label }}</button>
        @endforeach
    </div>

    {{-- ── Tab: Configuration (env vars) ── --}}
    <div x-show="tab === 'config'" x-cloak>
        @if(empty($configKeys))
            <div class="text-sm text-gray-600 py-4">
                This tool has no declared configuration variables.
                <br><br>
                <button @click="tab = 'meta'" class="text-brand hover:underline text-xs">
                    Add config keys →
                </button>
            </div>
        @else
            <form action="{{ route('tools.save-config', $tool->id) }}" method="POST" class="space-y-4">
                @csrf
                <p class="text-xs text-gray-500">
                    Values are written directly to your <code class="text-brand">.env</code> file.
                    Sensitive values are masked — clear a field to remove its value.
                </p>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    @foreach($configKeys as $key)
                        @php
                            $val       = $envValues[$key] ?? '';
                            $sensitive = preg_match('/TOKEN|SECRET|PASSWORD|KEY|PASS/i', $key);
                        @endphp
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">
                                <code class="text-brand">{{ $key }}</code>
                                @if($sensitive) <span class="text-yellow-600 ml-1">sensitive</span> @endif
                            </label>
                            <input type="{{ $sensitive ? 'password' : 'text' }}"
                                   name="{{ $key }}"
                                   value="{{ $val }}"
                                   placeholder="Not set"
                                   autocomplete="off"
                                   class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-sm text-gray-200 font-mono focus:outline-none focus:border-brand">
                        </div>
                    @endforeach
                </div>
                <button type="submit"
                        class="px-4 py-2 bg-brand hover:bg-orange-600 text-white text-xs rounded transition">
                    Save Configuration
                </button>
            </form>
        @endif
    </div>

    {{-- ── Tab: Implementation (PHP code) ── --}}
    <div x-show="tab === 'code'" x-cloak>
        @if($methodCode)
            <div class="space-y-2">
                <p class="text-xs text-gray-500">
                    Method <code class="text-brand">{{ $tool->executorMethod() }}</code> in
                    <code class="text-gray-400">app/Services/Agent/ToolExecutor.php</code>
                </p>
                <pre class="bg-gray-950 border border-gray-800 rounded-lg p-4 overflow-x-auto text-xs text-gray-300 leading-relaxed whitespace-pre-wrap">{{ $methodCode }}</pre>
                <p class="text-xs text-gray-700">
                    Use the <button @click="tab = 'ai'" class="text-brand hover:underline">Modify with AI</button> tab to change this implementation.
                </p>
            </div>
        @elseif($tool->handler_class)
            <p class="text-sm text-gray-400">
                This tool delegates to <code class="text-brand">{{ $tool->handler_class }}</code>.
            </p>
        @else
            <p class="text-sm text-gray-600">No implementation code found for this tool.</p>
        @endif
    </div>

    {{-- ── Tab: Modify with AI ── --}}
    <div x-show="tab === 'ai'" x-cloak class="space-y-4">
        <p class="text-xs text-gray-500">
            Describe what you want to change. Claude Opus will modify the implementation and show you the result before applying.
        </p>

        <div>
            <label class="block text-xs text-gray-500 mb-1">Your instruction</label>
            <textarea x-model="aiInstruction" rows="4" placeholder="e.g. Add support for recurring events, or change the date format to DD/MM/YYYY"
                      class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-sm text-gray-200 focus:outline-none focus:border-brand resize-y"></textarea>
        </div>

        <button @click="runAI()" :disabled="aiLoading || aiInstruction.trim() === ''"
                class="px-4 py-2 bg-brand hover:bg-orange-600 disabled:opacity-40 text-white text-xs rounded transition flex items-center gap-2">
            <span x-show="!aiLoading">Ask Claude Opus</span>
            <span x-show="aiLoading" x-cloak>Thinking…</span>
        </button>

        <div x-show="aiError" x-cloak class="bg-red-900/40 border border-red-700 text-red-300 text-xs rounded px-4 py-2" x-text="aiError"></div>

        <div x-show="applyMsg" x-cloak class="bg-green-900/40 border border-green-700 text-green-300 text-xs rounded px-4 py-2" x-text="applyMsg"></div>

        <div x-show="aiSuggestion" x-cloak class="space-y-3">
            <p class="text-xs text-gray-400 font-semibold">Claude's suggestion — review before applying:</p>
            <textarea x-model="aiSuggestion" rows="20"
                      class="w-full bg-gray-950 border border-gray-700 rounded px-3 py-2 text-xs text-green-300 font-mono focus:outline-none focus:border-brand resize-y leading-relaxed"></textarea>
            <div class="flex gap-3">
                <button @click="applyAI()"
                        class="px-4 py-2 bg-green-700 hover:bg-green-600 text-white text-xs rounded transition">
                    Apply to ToolExecutor.php
                </button>
                <button @click="aiSuggestion = ''; aiInstruction = ''"
                        class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 text-xs rounded transition">
                    Discard
                </button>
            </div>
        </div>
    </div>

    {{-- ── Tab: Metadata ── --}}
    <div x-show="tab === 'meta'" x-cloak class="space-y-6">

        {{-- Basic fields --}}
        <form action="{{ route('tools.save-config', $tool->id) }}" method="POST" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Display name</label>
                    <input type="text" name="display_name" value="{{ $tool->display_name }}"
                           class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-sm text-gray-200 focus:outline-none focus:border-brand">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Description</label>
                    <input type="text" name="description" value="{{ $tool->description }}"
                           class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-sm text-gray-200 focus:outline-none focus:border-brand">
                </div>
            </div>
            <button type="submit" class="px-4 py-2 bg-brand hover:bg-orange-600 text-white text-xs rounded transition">
                Save Metadata
            </button>
        </form>

        {{-- Config keys management --}}
        <div class="border-t border-gray-800 pt-4 space-y-3">
            <p class="text-xs text-gray-400 font-semibold">Required .env Keys</p>
            <p class="text-xs text-gray-600">Declare which .env variables this tool needs. Comma-separated list.</p>
            <form action="{{ route('tools.save-config-keys', $tool->id) }}" method="POST" class="flex gap-3 items-end">
                @csrf
                <div class="flex-1">
                    <input type="text" name="config_keys"
                           value="{{ implode(', ', $configKeys) }}"
                           placeholder="e.g. GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET"
                           class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-sm text-gray-200 font-mono focus:outline-none focus:border-brand">
                </div>
                <button type="submit" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 text-xs rounded transition whitespace-nowrap">
                    Update Keys
                </button>
            </form>
            @if(!empty($configKeys))
                <div class="flex flex-wrap gap-2">
                    @foreach($configKeys as $k)
                        <span class="bg-gray-800 text-gray-400 text-xs font-mono px-2 py-1 rounded">{{ $k }}</span>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Read-only fields --}}
        <div class="border-t border-gray-800 pt-4 space-y-2 text-xs text-gray-600">
            <div><span class="text-gray-500">Internal name:</span> <code class="text-brand">{{ $tool->name }}</code></div>
            <div><span class="text-gray-500">Type:</span> {{ $tool->type->label() }}</div>
            <div><span class="text-gray-500">Risk:</span> {{ $tool->risk_level->label() }}</div>
            <div><span class="text-gray-500">Requires confirmation:</span> {{ $tool->requires_confirmation ? 'Yes' : 'No' }}</div>
            @if($tool->handler_class)
                <div><span class="text-gray-500">Handler class:</span> <code>{{ $tool->handler_class }}</code></div>
            @endif
            <div><span class="text-gray-500">Created:</span> {{ $tool->created_at?->format('Y-m-d H:i') }}</div>
        </div>
    </div>

</div>
@endsection
