@extends('layouts.app')
@section('title', 'Tools')

@section('content')
<div class="space-y-4" x-data="{ search: '' }">

    <div class="flex items-center justify-between flex-wrap gap-3">
        <p class="text-sm text-gray-500">{{ $tools->count() }} tools registered</p>
        <input type="text" x-model="search" placeholder="Filter tools…"
               class="bg-gray-800 border border-gray-700 rounded px-3 py-1.5 text-xs text-gray-200 focus:outline-none focus:border-brand w-48">
    </div>

    {{-- Risk legend --}}
    <div class="flex gap-3 text-xs">
        <span class="badge-safe">Safe</span>
        <span class="badge-moderate">Moderate</span>
        <span class="badge-dangerous">Dangerous</span>
        <span class="text-gray-600 ml-2">Toggle to enable/disable. Disabled tools cannot be used by any command.</span>
    </div>

    <div class="bg-gray-900 border border-gray-800 rounded-lg overflow-hidden">
        <table class="w-full text-xs">
            <thead class="bg-gray-800 text-gray-500 uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left">Name</th>
                    <th class="px-4 py-3 text-left">Type</th>
                    <th class="px-4 py-3 text-left">Risk</th>
                    <th class="px-4 py-3 text-left">Description</th>
                    <th class="px-4 py-3 text-center w-20">Confirm</th>
                    <th class="px-4 py-3 text-center w-20">Status</th>
                    <th class="px-4 py-3 w-20"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
                @foreach($tools as $tool)
                    <tr class="hover:bg-gray-800/40 transition"
                        x-show="search === '' || '{{ strtolower($tool->name . ' ' . $tool->description) }}'.includes(search.toLowerCase())">
                        <td class="px-4 py-3">
                            <code class="text-brand font-semibold">{{ $tool->name }}</code>
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $tool->type->label() }}</td>
                        <td class="px-4 py-3">
                            @php
                                $rc = match($tool->risk_level->value) {
                                    'safe'      => 'badge-safe',
                                    'moderate'  => 'badge-moderate',
                                    'dangerous' => 'badge-dangerous',
                                    default     => 'badge-safe',
                                };
                            @endphp
                            <span class="{{ $rc }}">{{ $tool->risk_level->label() }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-400">{{ $tool->description }}</td>
                        <td class="px-4 py-3 text-center text-gray-500">
                            {{ $tool->requires_confirmation ? '✓' : '–' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="font-semibold {{ $tool->is_active ? 'text-green-400' : 'text-gray-600' }}">
                                {{ $tool->is_active ? '● On' : '○ Off' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('tools.show', $tool->id) }}"
                                   class="px-2 py-1 text-xs rounded bg-gray-800 hover:bg-gray-700 text-gray-400 hover:text-brand transition">
                                    Configure
                                </a>
                                <button onclick="toggleTool({{ $tool->id }})"
                                        class="px-2 py-1 text-xs rounded transition {{ $tool->is_active ? 'bg-gray-800 hover:bg-red-900 text-gray-400 hover:text-red-300' : 'bg-gray-800 hover:bg-green-900 text-gray-400 hover:text-green-300' }}">
                                    {{ $tool->is_active ? 'Disable' : 'Enable' }}
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="text-xs text-gray-700 pt-1">
        Tools are assigned to commands in the <a href="{{ route('commands.index') }}" class="text-gray-500 hover:text-brand">Commands</a> page.
        A tool must be both active here and listed in the command's tool list to be usable.
    </p>

</div>
@endsection

@push('scripts')
<script>
async function toggleTool(id) {
    const resp = await fetch(`/api/fd/tools/${id}/toggle`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
    });
    if (resp.ok) location.reload();
}
</script>
@endpush
