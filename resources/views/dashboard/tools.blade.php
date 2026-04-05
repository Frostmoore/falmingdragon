@extends('layouts.app')
@section('title', 'Tools')

@section('content')
<div class="space-y-4">

    <p class="text-sm text-gray-500">{{ $tools->count() }} tools registered</p>

    <div class="bg-gray-900 border border-gray-800 rounded-lg overflow-hidden">
        <table class="w-full text-xs">
            <thead class="bg-gray-800 text-gray-500 uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left">Name</th>
                    <th class="px-4 py-3 text-left">Type</th>
                    <th class="px-4 py-3 text-left">Risk</th>
                    <th class="px-4 py-3 text-left">Description</th>
                    <th class="px-4 py-3 text-center">Confirm</th>
                    <th class="px-4 py-3 text-center">Active</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
                @foreach($tools as $tool)
                    <tr class="hover:bg-gray-800/50">
                        <td class="px-4 py-3 text-brand font-mono font-semibold">{{ $tool->name }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $tool->type->label() }}</td>
                        <td class="px-4 py-3">
                            @php
                                $riskClass = [
                                    'safe'      => 'badge-safe',
                                    'moderate'  => 'badge-moderate',
                                    'dangerous' => 'badge-dangerous',
                                ][$tool->risk_level->value] ?? 'badge-safe';
                            @endphp
                            <span class="{{ $riskClass }}">{{ $tool->risk_level->label() }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-400 max-w-xs truncate">{{ $tool->description }}</td>
                        <td class="px-4 py-3 text-center">
                            {{ $tool->requires_confirmation ? '✓' : '–' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="{{ $tool->is_active ? 'text-green-400' : 'text-gray-600' }}">
                                {{ $tool->is_active ? '● On' : '○ Off' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button onclick="toggleTool({{ $tool->id }})"
                                    class="text-xs text-gray-500 hover:text-brand transition">
                                Toggle
                            </button>
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
async function toggleTool(id) {
    const resp = await fetch(`/api/fd/tools/${id}/toggle`, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } });
    if (resp.ok) location.reload();
}
</script>
@endpush
