@extends('layouts.app')
@section('title', $skill->display_name . ' — Skill')

@section('content')
<div class="space-y-6"
     x-data="{
        rawContent: @js($content),
        aiInstruction: '',
        aiSuggestion: '',
        aiLoading: false,
        aiError: '',
        applyMsg: '',
        saveMsg: '',
        tab: {{ empty($envKeys) ? '\'docs\'' : '\'config\'' }},

        async runAI() {
            this.aiLoading = true; this.aiError = ''; this.aiSuggestion = ''; this.applyMsg = '';
            try {
                const r = await fetch('{{ route('skills.ai-suggest', $skill->id) }}', {
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
                const r = await fetch('{{ route('skills.ai-apply', $skill->id) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ content: this.aiSuggestion })
                });
                const j = await r.json();
                if (j.error) { this.aiError = j.error; }
                else { this.applyMsg = j.message; this.rawContent = this.aiSuggestion; this.aiSuggestion = ''; this.aiInstruction = ''; }
            } catch(e) { this.aiError = e.message; }
        },

        async saveDirect() {
            this.saveMsg = ''; this.aiError = '';
            try {
                const r = await fetch('{{ route('skills.ai-apply', $skill->id) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ content: this.rawContent })
                });
                const j = await r.json();
                if (j.error) { this.aiError = j.error; } else { this.saveMsg = 'SKILL.md saved.'; }
            } catch(e) { this.aiError = e.message; }
        }
     }">

    {{-- Header --}}
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
            <div class="flex items-center gap-3 flex-wrap">
                <a href="{{ route('skills.index') }}" class="text-gray-600 hover:text-gray-400 text-xs">← Skills</a>
                <h1 class="text-lg font-semibold text-gray-100">{{ $skill->display_name }}</h1>
                <code class="text-brand text-sm">{{ $skill->name }}</code>
                <span class="text-xs text-gray-600">v{{ $skill->version }}</span>
                <span class="text-xs font-semibold {{ $skill->is_active ? 'text-green-400' : 'text-gray-600' }}">
                    {{ $skill->is_active ? '● Active' : '○ Inactive' }}
                </span>
            </div>
            <p class="mt-1 text-sm text-gray-400">{{ $skill->description }}</p>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-900/40 border border-green-700 text-green-300 text-xs rounded px-4 py-2">
            {{ session('success') }}
        </div>
    @endif

    {{-- Tabs --}}
    <div class="flex gap-1 border-b border-gray-800 text-xs">
        @foreach(['config' => 'Configuration', 'docs' => 'SKILL.md', 'ai' => 'Modify with AI'] as $key => $label)
            <button @click="tab = '{{ $key }}'"
                    :class="tab === '{{ $key }}' ? 'border-b-2 border-brand text-brand' : 'text-gray-500 hover:text-gray-300'"
                    class="px-4 py-2 transition">{{ $label }}</button>
        @endforeach
    </div>

    {{-- ── Tab: Configuration (env vars + tools required) ── --}}
    <div x-show="tab === 'config'" x-cloak class="space-y-6">

        {{-- Required tools --}}
        @if(!empty($toolsReq))
        <div>
            <p class="text-xs text-gray-500 font-semibold mb-2">Required Tools</p>
            <div class="flex flex-wrap gap-2">
                @foreach($toolsReq as $toolName)
                    @php $t = \App\Models\Tool::where('name', $toolName)->first(); @endphp
                    <a href="{{ $t ? route('tools.show', $t->id) : '#' }}"
                       class="bg-gray-800 border {{ $t?->is_active ? 'border-green-800 text-green-400' : 'border-red-900 text-red-400' }} text-xs font-mono px-2 py-1 rounded hover:border-brand transition">
                        {{ $toolName }}
                        <span class="text-gray-600 ml-1">{{ $t?->is_active ? '✓' : '✗ inactive' }}</span>
                    </a>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Env vars --}}
        @if(empty($envKeys))
            <div class="text-sm text-gray-600 py-2">
                No environment variables declared for this skill.
                Add <code class="text-brand">env_required</code> to the SKILL.md frontmatter to declare them.
            </div>
        @else
            <form action="{{ route('skills.save-config', $skill->id) }}" method="POST" class="space-y-4">
                @csrf
                <p class="text-xs text-gray-500">Values are written to your <code class="text-brand">.env</code> file.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($envKeys as $key)
                        @php
                            $val       = $envValues[$key] ?? '';
                            $sensitive = preg_match('/TOKEN|SECRET|PASSWORD|KEY|PASS/i', $key);
                        @endphp
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">
                                <code class="text-brand">{{ $key }}</code>
                                @if($sensitive) <span class="text-yellow-600 ml-1">sensitive</span> @endif
                                @if($val !== '') <span class="text-green-600 ml-1">✓ set</span> @else <span class="text-red-600 ml-1">not set</span> @endif
                            </label>
                            <input type="{{ $sensitive ? 'password' : 'text' }}"
                                   name="{{ $key }}"
                                   value="{{ $val }}"
                                   placeholder="Enter value…"
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

        {{-- Skill path --}}
        <div class="border-t border-gray-800 pt-4 text-xs text-gray-600 space-y-1">
            <div><span class="text-gray-500">Skill path:</span> <code>{{ $skill->skill_path }}</code></div>
            <div><span class="text-gray-500">SKILL.md:</span> <code>{{ $skill->skill_md_path }}</code></div>
            <div><span class="text-gray-500">Installed:</span> {{ $skill->installed_at?->format('Y-m-d H:i') }}</div>
        </div>
    </div>

    {{-- ── Tab: SKILL.md editor ── --}}
    <div x-show="tab === 'docs'" x-cloak class="space-y-3">
        <p class="text-xs text-gray-500">
            Edit the SKILL.md directly. This file defines how the agent uses this skill.
            The YAML frontmatter controls metadata (env_required, tools_required, etc.).
        </p>

        <div x-show="saveMsg" x-cloak class="bg-green-900/40 border border-green-700 text-green-300 text-xs rounded px-4 py-2" x-text="saveMsg"></div>
        <div x-show="aiError" x-cloak class="bg-red-900/40 border border-red-700 text-red-300 text-xs rounded px-4 py-2" x-text="aiError"></div>

        <textarea x-model="rawContent" rows="30"
                  class="w-full bg-gray-950 border border-gray-700 rounded px-4 py-3 text-xs text-gray-300 font-mono focus:outline-none focus:border-brand resize-y leading-relaxed"></textarea>

        <div class="flex gap-3">
            <button @click="saveDirect()"
                    class="px-4 py-2 bg-brand hover:bg-orange-600 text-white text-xs rounded transition">
                Save SKILL.md
            </button>
            <button @click="tab = 'ai'"
                    class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 text-xs rounded transition">
                Modify with AI →
            </button>
        </div>
    </div>

    {{-- ── Tab: Modify with AI ── --}}
    <div x-show="tab === 'ai'" x-cloak class="space-y-4">
        <p class="text-xs text-gray-500">
            Describe what you want to change in this skill. Claude Opus will rewrite the SKILL.md and show you the result before saving.
        </p>

        <div>
            <label class="block text-xs text-gray-500 mb-1">Your instruction</label>
            <textarea x-model="aiInstruction" rows="3"
                      placeholder="e.g. Add instructions for handling recurring events, or translate to Italian"
                      class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-sm text-gray-200 focus:outline-none focus:border-brand resize-y"></textarea>
        </div>

        <button @click="runAI()" :disabled="aiLoading || aiInstruction.trim() === ''"
                class="px-4 py-2 bg-brand hover:bg-orange-600 disabled:opacity-40 text-white text-xs rounded transition">
            <span x-show="!aiLoading">Ask Claude Opus</span>
            <span x-show="aiLoading" x-cloak>Thinking…</span>
        </button>

        <div x-show="aiError" x-cloak class="bg-red-900/40 border border-red-700 text-red-300 text-xs rounded px-4 py-2" x-text="aiError"></div>
        <div x-show="applyMsg" x-cloak class="bg-green-900/40 border border-green-700 text-green-300 text-xs rounded px-4 py-2" x-text="applyMsg"></div>

        <div x-show="aiSuggestion" x-cloak class="space-y-3">
            <p class="text-xs text-gray-400 font-semibold">Claude's suggestion — review and edit before saving:</p>
            <textarea x-model="aiSuggestion" rows="25"
                      class="w-full bg-gray-950 border border-gray-700 rounded px-3 py-2 text-xs text-green-300 font-mono focus:outline-none focus:border-brand resize-y leading-relaxed"></textarea>
            <div class="flex gap-3">
                <button @click="applyAI()"
                        class="px-4 py-2 bg-green-700 hover:bg-green-600 text-white text-xs rounded transition">
                    Apply to SKILL.md
                </button>
                <button @click="aiSuggestion = ''; aiInstruction = ''"
                        class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 text-xs rounded transition">
                    Discard
                </button>
            </div>
        </div>
    </div>

</div>
@endsection
