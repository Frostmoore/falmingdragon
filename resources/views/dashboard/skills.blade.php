@extends('layouts.app')
@section('title', 'Skills')

@section('content')
<div class="space-y-4" x-data="{ showInstall: false }">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500">{{ $skills->count() }} skills installed</p>
        <button @click="showInstall = !showInstall"
                class="px-3 py-1.5 bg-brand hover:bg-orange-600 text-white text-xs rounded transition">
            + Install Skill
        </button>
    </div>

    {{-- Install form --}}
    <div x-show="showInstall" x-cloak class="bg-gray-900 border border-gray-800 rounded-lg p-4">
        <form action="{{ route('skills.install') }}" method="POST" class="flex gap-3 items-end">
            @csrf
            <div class="flex-1">
                <label class="block text-xs text-gray-500 mb-1">Skill directory path (absolute)</label>
                <input type="text" name="path" required placeholder="/home/ubuntu/my-skill"
                       class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-sm text-gray-200 focus:outline-none focus:border-brand">
            </div>
            <button type="submit" class="px-4 py-2 bg-green-700 hover:bg-green-600 text-white text-xs rounded transition">
                Install
            </button>
        </form>
    </div>

    {{-- Skills list --}}
    <div class="bg-gray-900 border border-gray-800 rounded-lg divide-y divide-gray-800">
        @forelse($skills as $skill)
            <div class="px-4 py-4 flex items-start gap-4">
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <span class="font-semibold text-gray-200">{{ $skill->display_name }}</span>
                        <code class="text-xs text-brand">{{ $skill->name }}</code>
                        <span class="text-xs text-gray-600">v{{ $skill->version }}</span>
                        @if(!$skill->is_active)
                            <span class="text-xs bg-gray-700 text-gray-500 px-2 py-0.5 rounded">Inactive</span>
                        @endif
                    </div>
                    <p class="text-xs text-gray-500">{{ $skill->description }}</p>
                    <div class="mt-1 text-xs text-gray-600 font-mono">{{ $skill->skill_path }}</div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <a href="{{ route('skills.edit', $skill->id) }}"
                       class="px-3 py-1 bg-gray-700 hover:bg-gray-600 text-gray-300 text-xs rounded transition">
                        Edit
                    </a>
                    <button onclick="toggleSkill({{ $skill->id }}, this)"
                            class="px-3 py-1 text-xs rounded transition {{ $skill->is_active ? 'bg-yellow-900 text-yellow-300 hover:bg-yellow-800' : 'bg-green-900 text-green-300 hover:bg-green-800' }}">
                        {{ $skill->is_active ? 'Disable' : 'Enable' }}
                    </button>
                </div>
            </div>
        @empty
            <div class="px-4 py-8 text-center text-gray-600 text-sm">
                No skills installed yet. Install one using the button above.
            </div>
        @endforelse
    </div>

</div>
@endsection

@push('scripts')
<script>
async function toggleSkill(id, btn) {
    const resp = await fetch(`/api/fd/skills/${id}/toggle`, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } });
    if (resp.ok) location.reload();
}
</script>
@endpush
