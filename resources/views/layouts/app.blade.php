<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — FlamingDragon</title>
    <link rel="icon" type="image/png" href="{{ asset('storage/logo.png') }}">

    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: { DEFAULT: '#f97316', dark: '#ea580c' }
                    }
                }
            }
        }
    </script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        .badge-safe      { @apply bg-green-900 text-green-300 text-xs font-medium px-2 py-0.5 rounded; }
        .badge-moderate  { @apply bg-yellow-900 text-yellow-300 text-xs font-medium px-2 py-0.5 rounded; }
        .badge-dangerous { @apply bg-red-900 text-red-300 text-xs font-medium px-2 py-0.5 rounded; }
    </style>
</head>
<body class="h-full text-gray-100 font-mono" x-data="{ sidebarOpen: true }">

<div class="flex h-full">
    <!-- Sidebar -->
    <aside class="w-56 shrink-0 bg-gray-900 border-r border-gray-800 flex flex-col" x-show="sidebarOpen">
        <!-- Logo -->
        <div class="px-4 py-4 border-b border-gray-800">
            <img src="{{ asset('storage/logo.png') }}"
                 alt="FlamingDragon"
                 class="w-full h-auto block"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='block'">
            <span class="text-brand text-xl font-bold tracking-tight hidden">🔥 FlamingDragon</span>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 px-2 py-4 space-y-1 overflow-y-auto">
            @php
                $navItems = [
                    ['route' => 'dashboard',       'label' => 'Dashboard',  'icon' => '⬛'],
                    ['route' => 'logs.index',       'label' => 'Logs',       'icon' => '📋'],
                    ['route' => 'skills.index',     'label' => 'Skills',     'icon' => '🧩'],
                    ['route' => 'tools.index',      'label' => 'Tools',      'icon' => '🔧'],
                    ['route' => 'commands.index',   'label' => 'Commands',   'icon' => '⚡'],
                    ['route' => 'memory.index',     'label' => 'Memory',     'icon' => '🧠'],
                    ['route' => 'prompts.index',    'label' => 'Prompts',    'icon' => '📝'],
                    ['route' => 'settings.index',   'label' => 'Settings',   'icon' => '⚙️'],
                    ['route' => 'wizard.index',     'label' => 'Setup Wizard', 'icon' => '🧙'],
                ];
            @endphp

            @foreach($navItems as $item)
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-2 px-3 py-2 rounded text-sm transition
                          {{ request()->routeIs($item['route']) ? 'bg-gray-800 text-brand' : 'text-gray-400 hover:text-gray-100 hover:bg-gray-800' }}">
                    <span>{{ $item['icon'] }}</span>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <!-- Footer -->
        <div class="px-4 py-3 border-t border-gray-800 text-xs text-gray-600">
            Laravel {{ app()->version() }}
        </div>
    </aside>

    <!-- Main content -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Top bar -->
        <header class="bg-gray-900 border-b border-gray-800 px-6 py-3 flex items-center gap-4">
            <button @click="sidebarOpen = !sidebarOpen" class="text-gray-400 hover:text-white">
                ☰
            </button>
            <h1 class="text-sm font-semibold text-gray-200">@yield('title', 'Dashboard')</h1>
            <div class="ml-auto flex items-center gap-4 text-xs text-gray-500">
                <span>{{ now()->format('Y-m-d H:i') }}</span>
                <a href="{{ route('api.fd.health') }}" target="_blank" class="text-green-500 hover:text-green-400">● Live</a>
            </div>
        </header>

        <!-- Flash messages -->
        @if(session('success'))
            <div class="bg-green-900 border border-green-700 text-green-300 px-6 py-3 text-sm">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="bg-red-900 border border-red-700 text-red-300 px-6 py-3 text-sm">
                {{ session('error') }}
            </div>
        @endif

        <!-- Page content -->
        <main class="flex-1 overflow-y-auto p-6">
            @yield('content')
        </main>
    </div>
</div>

@stack('scripts')
</body>
</html>
