@extends('layouts.app')
@section('title', 'Edit Skill: ' . $skill->name)

@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3">
        <a href="{{ route('skills.index') }}" class="text-gray-500 hover:text-gray-300 text-sm">← Skills</a>
        <span class="text-gray-700">/</span>
        <span class="text-brand text-sm font-mono">{{ $skill->name }}</span>
    </div>

    <form action="{{ route('skills.update', $skill->id) }}" method="POST">
        @csrf
        <div class="bg-gray-900 border border-gray-800 rounded-lg p-4 space-y-3">
            <label class="block text-xs text-gray-500 mb-1">SKILL.md Content</label>
            <textarea name="content" rows="30"
                      class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-xs text-gray-200 font-mono focus:outline-none focus:border-brand"
            >{{ $content }}</textarea>
            <button type="submit" class="px-4 py-2 bg-brand hover:bg-orange-600 text-white text-sm rounded transition">
                Save
            </button>
        </div>
    </form>
</div>
@endsection
