@extends('layouts.app')
@section('title', 'Prompt Editor')

@section('content')
<div class="space-y-8" x-data="{ activeCmd: null }">

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="bg-green-900 border border-green-700 text-green-300 px-4 py-3 text-sm rounded-lg">✓ {{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-900 border border-red-700 text-red-300 px-4 py-3 text-sm rounded-lg">✗ {{ session('error') }}</div>
    @endif

    {{-- ─── Global System Prompt ─────────────────────────────────────────── --}}
    <section>
        <div class="flex items-center justify-between mb-3">
            <div>
                <h2 class="text-sm font-semibold text-gray-300">Global System Prompt</h2>
                <p class="text-xs text-gray-600 mt-0.5">
                    Injected at the start of every agent session.
                    File: <code class="text-gray-500">{{ $promptPath }}</code>
                </p>
            </div>
        </div>

        <form action="{{ route('prompts.save-global') }}" method="POST">
            @csrf
            <div class="bg-gray-900 border border-gray-800 rounded-lg overflow-hidden">
                <div class="flex items-center justify-between px-4 py-2 bg-gray-800 border-b border-gray-700">
                    <span class="text-xs text-gray-500 font-mono">agent_system.md</span>
                    <button type="submit"
                            class="px-3 py-1 bg-brand hover:bg-orange-600 text-white text-xs font-semibold rounded transition">
                        Save
                    </button>
                </div>
                <textarea name="prompt" rows="20" spellcheck="false"
                          class="w-full bg-gray-900 text-gray-200 font-mono text-xs px-4 py-4 focus:outline-none resize-y leading-relaxed"
                          style="tab-size:2">{{ $globalPrompt }}</textarea>
            </div>
        </form>

        <div class="mt-2 text-xs text-gray-600 space-y-0.5">
            <p>Markdown is supported. The following blocks are appended automatically by the engine:</p>
            <p class="font-mono text-gray-700">## Command-specific Instructions &nbsp;·&nbsp; ## Relevant Memories &nbsp;·&nbsp; ## Active Skill &nbsp;·&nbsp; ## Available Tools &nbsp;·&nbsp; ## Session Constraints</p>
        </div>
    </section>

    {{-- ─── Per-command Prompts ──────────────────────────────────────────── --}}
    <section>
        <h2 class="text-sm font-semibold text-gray-300 mb-1">Per-command Prompt Overrides</h2>
        <p class="text-xs text-gray-600 mb-4">
            Additional instructions injected after the global prompt for a specific command.
            Use this to give a command a different personality, extra constraints, or domain knowledge.
        </p>

        <div class="space-y-2">
            @foreach($commands->groupBy('category') as $category => $cmds)
                <div class="text-xs font-semibold text-gray-600 uppercase tracking-wider pt-2 pb-1">{{ $category }}</div>

                @foreach($cmds as $cmd)
                <div class="bg-gray-900 border border-gray-800 rounded-lg overflow-hidden">
                    {{-- Header --}}
                    <button type="button"
                            @click="activeCmd = (activeCmd === {{ $cmd->id }}) ? null : {{ $cmd->id }}"
                            class="w-full flex items-center justify-between px-4 py-3 hover:bg-gray-800 transition text-left">
                        <div class="flex items-center gap-3">
                            <code class="text-brand text-xs">{{ $cmd->name }}</code>
                            <span class="text-xs text-gray-500">{{ $cmd->description }}</span>
                            @if($cmd->system_prompt)
                                <span class="text-xs bg-purple-900 text-purple-300 px-2 py-0.5 rounded">custom prompt</span>
                            @endif
                        </div>
                        <span class="text-gray-600 text-xs" x-text="activeCmd === {{ $cmd->id }} ? '▲' : '▼'"></span>
                    </button>

                    {{-- Editor --}}
                    <div x-show="activeCmd === {{ $cmd->id }}" x-cloak>
                        <form action="{{ route('prompts.save-command', $cmd->id) }}" method="POST">
                            @csrf
                            <div class="border-t border-gray-800">
                                <textarea name="system_prompt"
                                          rows="6"
                                          spellcheck="false"
                                          placeholder="Leave empty to use only the global prompt.&#10;&#10;Example:&#10;You are handling the {{ $cmd->name }} command. Be extra careful and always double-check paths before writing files."
                                          class="w-full bg-gray-950 text-gray-200 font-mono text-xs px-4 py-3 focus:outline-none resize-y leading-relaxed"
                                          style="tab-size:2">{{ $cmd->system_prompt }}</textarea>
                            </div>
                            <div class="flex justify-between items-center px-4 py-2 bg-gray-800 border-t border-gray-700">
                                <span class="text-xs text-gray-600">
                                    Mode: <code class="text-gray-400">{{ $cmd->execution_mode->value }}</code>
                                    &nbsp;·&nbsp; Timeout: <code class="text-gray-400">{{ $cmd->timeout_seconds }}s</code>
                                    &nbsp;·&nbsp; Tools: <code class="text-gray-400">{{ implode(', ', $cmd->tools_allowed ?? []) ?: 'none' }}</code>
                                </span>
                                <div class="flex gap-2">
                                    @if($cmd->system_prompt)
                                        <button type="submit" name="system_prompt" value=""
                                                class="px-3 py-1 bg-gray-700 hover:bg-gray-600 text-gray-400 text-xs rounded transition"
                                                onclick="this.form.querySelector('textarea').value=''">
                                            Clear
                                        </button>
                                    @endif
                                    <button type="submit"
                                            class="px-3 py-1 bg-brand hover:bg-orange-600 text-white text-xs font-semibold rounded transition">
                                        Save
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                @endforeach
            @endforeach
        </div>
    </section>

</div>
@endsection
