@extends('layouts.app')
@section('title', 'Memory Store')

@section('content')
<div class="space-y-4" x-data="{ showAdd: false }">

    <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500">{{ $memories->total() }} memory entries</p>
        <button @click="showAdd = !showAdd"
                class="px-3 py-1.5 bg-brand hover:bg-orange-600 text-white text-xs rounded transition">
            + Add Memory
        </button>
    </div>

    {{-- Add form --}}
    <div x-show="showAdd" x-cloak class="bg-gray-900 border border-gray-800 rounded-lg p-4">
        <form action="{{ route('api.fd.memory.store') }}" method="POST" class="grid grid-cols-2 gap-3">
            @csrf
            <div>
                <label class="block text-xs text-gray-500 mb-1">Namespace</label>
                <input type="text" name="namespace" value="general" class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-sm text-gray-200 focus:outline-none focus:border-brand">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Key *</label>
                <input type="text" name="key" required class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-sm text-gray-200 focus:outline-none focus:border-brand">
            </div>
            <div class="col-span-2">
                <label class="block text-xs text-gray-500 mb-1">Value *</label>
                <textarea name="value" required rows="3" class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-sm text-gray-200 focus:outline-none focus:border-brand"></textarea>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Type</label>
                <select name="memory_type" class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-sm text-gray-200">
                    <option value="fact">Fact</option>
                    <option value="preference">Preference</option>
                    <option value="context">Context</option>
                    <option value="instruction">Instruction</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="px-4 py-2 bg-green-700 hover:bg-green-600 text-white text-xs rounded transition">
                    Save Memory
                </button>
            </div>
        </form>
    </div>

    {{-- Namespace filter --}}
    <div class="flex gap-2 flex-wrap text-xs">
        <a href="{{ route('memory.index') }}" class="px-2 py-1 rounded {{ !request('namespace') ? 'bg-brand text-white' : 'bg-gray-800 text-gray-400 hover:text-white' }}">All</a>
        @foreach($namespaces as $ns)
            <a href="{{ route('memory.index', ['namespace' => $ns]) }}"
               class="px-2 py-1 rounded {{ request('namespace') === $ns ? 'bg-brand text-white' : 'bg-gray-800 text-gray-400 hover:text-white' }}">
                {{ $ns }}
            </a>
        @endforeach
    </div>

    {{-- Memory table --}}
    <div class="bg-gray-900 border border-gray-800 rounded-lg overflow-hidden">
        <table class="w-full text-xs">
            <thead class="bg-gray-800 text-gray-500 uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left">Namespace</th>
                    <th class="px-4 py-3 text-left">Key</th>
                    <th class="px-4 py-3 text-left">Value</th>
                    <th class="px-4 py-3 text-left">Type</th>
                    <th class="px-4 py-3 text-left">Source</th>
                    <th class="px-4 py-3 text-right">Updated</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
                @forelse($memories as $memory)
                    <tr class="hover:bg-gray-800/50 {{ $memory->isExpired() ? 'opacity-40' : '' }}">
                        <td class="px-4 py-3 text-purple-400 font-mono">{{ $memory->namespace }}</td>
                        <td class="px-4 py-3 text-brand font-mono">{{ $memory->key }}</td>
                        <td class="px-4 py-3 text-gray-300 max-w-xs truncate">{{ $memory->value }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $memory->memory_type->label() }}</td>
                        <td class="px-4 py-3 text-gray-600 max-w-[120px] truncate">{{ $memory->source ?? '—' }}</td>
                        <td class="px-4 py-3 text-right text-gray-600">{{ $memory->updated_at->diffForHumans() }}</td>
                        <td class="px-4 py-3 text-right">
                            <button onclick="deleteMemory({{ $memory->id }})"
                                    class="text-xs text-gray-500 hover:text-red-400">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-600">No memories stored.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="text-xs">{{ $memories->withQueryString()->links() }}</div>

</div>
@endsection

@push('scripts')
<script>
async function deleteMemory(id) {
    if (!confirm('Delete this memory entry?')) return;
    await fetch(`/api/fd/memory/${id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } });
    location.reload();
}
</script>
@endpush
