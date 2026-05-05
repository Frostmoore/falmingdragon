@extends('layouts.app')
@section('title', 'Commands (Allow-list)')

@section('content')
<div class="space-y-4" x-data="{ showAdd: false }">

    <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500">{{ $commands->count() }} commands registered</p>
        <button @click="showAdd = !showAdd"
                class="px-3 py-1.5 bg-brand hover:bg-orange-600 text-white text-xs rounded transition">
            + Add Command
        </button>
    </div>

    {{-- Add form --}}
    <div x-show="showAdd" x-cloak class="bg-gray-900 border border-gray-800 rounded-lg p-4">
        <form action="{{ route('api.fd.commands.store') }}" method="POST" class="grid grid-cols-2 gap-3">
            @csrf
            <div>
                <label class="block text-xs text-gray-500 mb-1">Name *</label>
                <input type="text" name="name" required class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-sm text-gray-200 focus:outline-none focus:border-brand">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Category *</label>
                <input type="text" name="category" required class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-sm text-gray-200 focus:outline-none focus:border-brand">
            </div>
            <div class="col-span-2">
                <label class="block text-xs text-gray-500 mb-1">Description *</label>
                <input type="text" name="description" required class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-sm text-gray-200 focus:outline-none focus:border-brand">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Execution Mode</label>
                <select name="execution_mode" class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-sm text-gray-200">
                    <option value="auto">Auto</option>
                    <option value="sync">Sync</option>
                    <option value="async">Async</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Timeout (seconds)</label>
                <input type="number" name="timeout_seconds" value="300" class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-sm text-gray-200 focus:outline-none focus:border-brand">
            </div>
            <div class="col-span-2 flex items-center gap-4">
                <label class="flex items-center gap-2 text-xs text-gray-400">
                    <input type="checkbox" name="is_dangerous" value="1"> Dangerous (requires confirmation)
                </label>
            </div>
            <div class="col-span-2">
                <button type="submit" class="px-4 py-2 bg-green-700 hover:bg-green-600 text-white text-xs rounded transition">
                    Add Command
                </button>
            </div>
        </form>
    </div>

    {{-- Commands table --}}
    <div class="bg-gray-900 border border-gray-800 rounded-lg overflow-hidden">
        <table class="w-full text-xs">
            <thead class="bg-gray-800 text-gray-500 uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left">Name</th>
                    <th class="px-4 py-3 text-left">Category</th>
                    <th class="px-4 py-3 text-left">Mode</th>
                    <th class="px-4 py-3 text-left">Tools</th>
                    <th class="px-4 py-3 text-center">Dangerous</th>
                    <th class="px-4 py-3 text-center" title="Skip confirmation even if dangerous">Skip Confirm</th>
                    <th class="px-4 py-3 text-center">Active</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
                @foreach($commands as $command)
                    <tr class="hover:bg-gray-800/50">
                        <td class="px-4 py-3 text-brand font-mono font-semibold">{{ $command->name }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $command->category }}</td>
                        <td class="px-4 py-3 text-gray-400">{{ $command->execution_mode->value }}</td>
                        <td class="px-4 py-3 text-gray-600 max-w-xs truncate">
                            {{ implode(', ', $command->tools_allowed ?? []) ?: '—' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($command->is_dangerous)
                                <span class="text-yellow-500" title="Dangerous — requires confirmation">⚠</span>
                            @else
                                <span class="text-gray-700">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($command->is_dangerous)
                                <button onclick="toggleSkipConfirm({{ $command->id }}, {{ $command->skip_confirmation ? 'true' : 'false' }})"
                                        title="{{ $command->skip_confirmation ? 'Confirmation bypassed — click to re-enable' : 'Click to bypass confirmation' }}"
                                        class="text-xs px-2 py-0.5 rounded transition {{ $command->skip_confirmation ? 'bg-green-900 text-green-300 hover:bg-red-900 hover:text-red-300' : 'bg-gray-800 text-gray-600 hover:bg-yellow-900 hover:text-yellow-300' }}">
                                    {{ $command->skip_confirmation ? '✓ bypassed' : 'require' }}
                                </button>
                            @else
                                <span class="text-gray-700 text-xs">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="{{ $command->is_active ? 'text-green-400' : 'text-gray-600' }}">
                                {{ $command->is_active ? '●' : '○' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button onclick="toggleCommand({{ $command->id }})"
                                    class="text-xs text-gray-500 hover:text-brand">Toggle</button>
                            |
                            <button onclick="deleteCommand({{ $command->id }})"
                                    class="text-xs text-gray-500 hover:text-red-400">Delete</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</div>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;
async function toggleCommand(id) {
    const cmd = await (await fetch(`/api/fd/commands`)).json();
    const current = cmd.find(c => c.id === id);
    if (!current) return;
    await fetch(`/api/fd/commands/${id}`, { method: 'PUT', headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' }, body: JSON.stringify({ is_active: !current.is_active }) });
    location.reload();
}
async function deleteCommand(id) {
    if (!confirm('Delete this command?')) return;
    await fetch(`/api/fd/commands/${id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf } });
    location.reload();
}
async function toggleSkipConfirm(id, currentlySkipped) {
    const msg = currentlySkipped
        ? 'Re-enable confirmation for this dangerous command?'
        : 'Bypass confirmation for this dangerous command? It will execute immediately without asking.';
    if (!confirm(msg)) return;
    await fetch(`/api/fd/commands/${id}`, {
        method: 'PUT',
        headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' },
        body: JSON.stringify({ skip_confirmation: !currentlySkipped }),
    });
    location.reload();
}
</script>
@endpush
