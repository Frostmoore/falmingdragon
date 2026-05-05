@extends('layouts.app')
@section('title', 'Memory Store')

@section('content')
<div class="space-y-4"
     x-data="{
        showAdd: false,
        editingId: null,
        editValue: '',
        editType: '',
        saving: false,
        backfillStatus: null,

        startEdit(id, value, type) {
            this.editingId = id;
            this.editValue = value;
            this.editType  = type;
        },
        cancelEdit() {
            this.editingId = null;
        },
        async saveEdit(id) {
            this.saving = true;
            await fetch(`/api/fd/memory/${id}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ value: this.editValue, memory_type: this.editType }),
            });
            this.saving = false;
            this.editingId = null;
            location.reload();
        },
        async deleteOne(id) {
            if (!confirm('Delete this memory entry?')) return;
            await fetch(`/api/fd/memory/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
            });
            location.reload();
        },
        async backfill() {
            this.backfillStatus = 'running';
            const r = await fetch('/api/fd/memory/backfill', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }
            });
            const d = await r.json();
            this.backfillStatus = `Generated: ${d.stats.generated} · Skipped: ${d.stats.skipped} · Failed: ${d.stats.failed}`;
        },

        async clearNamespace(ns) {
            if (!confirm(`Delete ALL memories in namespace \"${ns}\"?`)) return;
            await fetch(`/api/fd/memory?namespace=${encodeURIComponent(ns)}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json',
                },
            });
            location.reload();
        },
     }">

    {{-- Toolbar --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <p class="text-sm text-gray-500">{{ $memories->total() }} memory entries</p>
        <div class="flex gap-2">
            {{-- Search --}}
            <form method="GET" class="flex gap-2">
                @if(request('namespace'))
                    <input type="hidden" name="namespace" value="{{ request('namespace') }}">
                @endif
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Search values…"
                       class="bg-gray-800 border border-gray-700 rounded px-3 py-1.5 text-xs text-gray-200 focus:outline-none focus:border-brand w-48">
                <button type="submit" class="px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-gray-300 text-xs rounded transition">Search</button>
                @if(request('search'))
                    <a href="{{ route('memory.index', array_filter(['namespace' => request('namespace')])) }}"
                       class="px-3 py-1.5 bg-gray-800 hover:bg-gray-700 text-gray-500 text-xs rounded transition">✕</a>
                @endif
            </form>
            <button @click="backfill()"
                    title="Generate embeddings for memories that are missing them"
                    class="px-3 py-1.5 bg-purple-800 hover:bg-purple-700 text-white text-xs rounded transition">
                ⚡ Backfill embeddings
            </button>
            <button @click="showAdd = !showAdd"
                    class="px-3 py-1.5 bg-brand hover:bg-orange-600 text-white text-xs rounded transition">
                + Add
            </button>
        </div>
    </div>

    {{-- Add form --}}
    <div x-show="showAdd" x-cloak class="bg-gray-900 border border-gray-800 rounded-lg p-4">
        <form action="{{ route('api.fd.memory.store') }}" method="POST" class="grid grid-cols-2 gap-3">
            @csrf
            <div>
                <label class="block text-xs text-gray-500 mb-1">Namespace</label>
                <input type="text" name="namespace" value="{{ request('namespace', 'general') }}"
                       class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-sm text-gray-200 focus:outline-none focus:border-brand">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Key *</label>
                <input type="text" name="key" required
                       class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-sm text-gray-200 focus:outline-none focus:border-brand">
            </div>
            <div class="col-span-2">
                <label class="block text-xs text-gray-500 mb-1">Value *</label>
                <textarea name="value" required rows="3"
                          class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-sm text-gray-200 focus:outline-none focus:border-brand"></textarea>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Type</label>
                <select name="memory_type"
                        class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-sm text-gray-200 focus:outline-none focus:border-brand">
                    <option value="fact">Fact</option>
                    <option value="preference">Preference</option>
                    <option value="context">Context</option>
                    <option value="instruction">Instruction</option>
                </select>
            </div>
            <div class="col-span-2 flex items-center justify-between">
                <label class="flex items-center gap-2 text-xs text-gray-400 cursor-pointer">
                    <input type="checkbox" name="is_important" value="1" class="accent-yellow-500">
                    <span>★ Important <span class="text-gray-600">(generates embedding for semantic search)</span></span>
                </label>
                <button type="submit"
                        class="px-4 py-2 bg-green-700 hover:bg-green-600 text-white text-xs rounded transition">
                    Save Memory
                </button>
            </div>
        </form>
    </div>

    {{-- Backfill status --}}
    <div x-show="backfillStatus" x-cloak
         class="text-xs px-3 py-2 bg-purple-900/40 border border-purple-800 rounded text-purple-300"
         x-text="backfillStatus === 'running' ? '⚡ Generating embeddings…' : '✓ Backfill done — ' + backfillStatus"></div>

    {{-- Namespace filter --}}
    <div class="flex gap-2 flex-wrap text-xs items-center">
        <a href="{{ route('memory.index', array_filter(['search' => request('search')])) }}"
           class="px-2 py-1 rounded {{ !request('namespace') ? 'bg-brand text-white' : 'bg-gray-800 text-gray-400 hover:text-white' }}">
            All
        </a>
        @foreach($namespaces as $ns)
            <div class="flex items-center gap-1">
                <a href="{{ route('memory.index', array_filter(['namespace' => $ns, 'search' => request('search')])) }}"
                   class="px-2 py-1 rounded {{ request('namespace') === $ns ? 'bg-brand text-white' : 'bg-gray-800 text-gray-400 hover:text-white' }}">
                    {{ $ns }}
                </a>
                <button @click="clearNamespace('{{ $ns }}')"
                        title="Clear entire namespace"
                        class="text-gray-700 hover:text-red-400 px-1 transition text-base leading-none">×</button>
            </div>
        @endforeach
    </div>

    {{-- Memory table --}}
    <div class="bg-gray-900 border border-gray-800 rounded-lg overflow-hidden">
        <table class="w-full text-xs">
            <thead class="bg-gray-800 text-gray-500 uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left w-28">Namespace</th>
                    <th class="px-4 py-3 text-left w-36">Key</th>
                    <th class="px-4 py-3 text-left">Value</th>
                    <th class="px-4 py-3 text-left w-24">Type</th>
                    <th class="px-4 py-3 text-left w-16">Vector</th>
                    <th class="px-4 py-3 text-right w-24">Updated</th>
                    <th class="px-4 py-3 w-20"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
                @forelse($memories as $memory)
                    <tr class="hover:bg-gray-800/40 group {{ $memory->isExpired() ? 'opacity-40' : '' }}">

                        {{-- Namespace --}}
                        <td class="px-4 py-3 text-purple-400 font-mono">
                            <a href="{{ route('memory.index', ['namespace' => $memory->namespace]) }}"
                               class="hover:underline">{{ $memory->namespace }}</a>
                        </td>

                        {{-- Key --}}
                        <td class="px-4 py-3 text-brand font-mono">{{ $memory->key }}</td>

                        {{-- Value — normal / editing --}}
                        <td class="px-4 py-3 text-gray-300">
                            <template x-if="editingId !== {{ $memory->id }}">
                                <span class="block max-w-md truncate cursor-pointer hover:text-white"
                                      @click="startEdit({{ $memory->id }}, {{ json_encode($memory->value) }}, '{{ $memory->memory_type->value }}')"
                                      title="Click to edit">{{ $memory->value }}</span>
                            </template>
                            <template x-if="editingId === {{ $memory->id }}">
                                <div class="space-y-2">
                                    <textarea x-model="editValue" rows="3"
                                              class="w-full bg-gray-800 border border-brand rounded px-2 py-1.5 text-xs text-gray-200 font-mono focus:outline-none resize-y"></textarea>
                                    <div class="flex items-center gap-2">
                                        <select x-model="editType"
                                                class="bg-gray-800 border border-gray-700 rounded px-2 py-1 text-xs text-gray-200 focus:outline-none">
                                            <option value="fact">Fact</option>
                                            <option value="preference">Preference</option>
                                            <option value="context">Context</option>
                                            <option value="instruction">Instruction</option>
                                        </select>
                                        <button @click="saveEdit({{ $memory->id }})" :disabled="saving"
                                                class="px-2 py-1 bg-green-700 hover:bg-green-600 text-white rounded transition disabled:opacity-50">Save</button>
                                        <button @click="cancelEdit()"
                                                class="px-2 py-1 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded transition">Cancel</button>
                                    </div>
                                </div>
                            </template>
                        </td>

                        {{-- Type --}}
                        <td class="px-4 py-3 text-gray-500">
                            {{ $memory->memory_type->label() }}
                            @if($memory->is_important)
                                <span class="ml-1 text-yellow-500" title="Important — always embedded">★</span>
                            @endif
                        </td>

                        {{-- Vector --}}
                        <td class="px-4 py-3">
                            @if($memory->embedding)
                                <span class="text-purple-400 text-xs" title="Embedding present">⬡</span>
                            @else
                                <span class="text-gray-700 text-xs">—</span>
                            @endif
                        </td>

                        {{-- Updated --}}
                        <td class="px-4 py-3 text-right text-gray-600">
                            {{ $memory->updated_at->diffForHumans() }}
                            @if($memory->expires_at)
                                <div class="text-gray-700">exp {{ $memory->expires_at->diffForHumans() }}</div>
                            @endif
                        </td>

                        {{-- Actions --}}
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition">
                                <button @click="startEdit({{ $memory->id }}, {{ json_encode($memory->value) }}, '{{ $memory->memory_type->value }}')"
                                        class="text-gray-500 hover:text-brand text-xs">Edit</button>
                                <button @click="deleteOne({{ $memory->id }})"
                                        class="text-gray-500 hover:text-red-400 text-xs">Del</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-gray-600">
                            No memories stored.
                            @if(request('search') || request('namespace'))
                                <a href="{{ route('memory.index') }}" class="text-brand ml-2">Clear filters</a>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="text-xs">{{ $memories->withQueryString()->links() }}</div>

    {{-- Legend --}}
    <div class="text-xs text-gray-700 pt-2 border-t border-gray-800">
        <p class="mb-1 font-semibold text-gray-600">Namespaces used by the engine:</p>
        <p><code class="text-gray-600">session_history</code> — automatic summary saved after each agent run (loaded as context for future sessions)</p>
        <p><code class="text-gray-600">general</code> — manual facts and preferences you add</p>
        <p>Click any value to edit it. Click × next to a namespace tab to wipe the entire namespace.</p>
    </div>

</div>
@endsection
