<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Тихий спутник') — производство фильма</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div x-data="{ nav: false }" class="min-h-screen lg:grid lg:grid-cols-[260px_1fr]">
    <header class="sticky top-0 z-30 flex items-center justify-between bg-ink-950 px-4 py-3 text-white lg:hidden"><a href="{{ route('dashboard') }}" class="font-semibold">Тихий спутник</a><button type="button" @click="nav = !nav" class="rounded-lg border border-white/20 px-3 py-1.5" aria-label="Открыть меню">Меню</button></header>
    <aside :class="nav ? 'block' : 'hidden'" class="fixed inset-x-0 top-[52px] z-20 h-[calc(100vh-52px)] overflow-y-auto bg-ink-950 p-5 lg:sticky lg:top-0 lg:block lg:h-screen">
        <div class="mb-8 hidden lg:block"><p class="text-xs uppercase tracking-[0.26em] text-amber-400">Производство фильма</p><p class="mt-2 text-xl font-semibold text-white">Тихий спутник</p><p class="mt-1 text-xs text-mist-200/70">The Silent Companion</p></div>
        @php($nav = [
            ['dashboard', 'Обзор', 'dashboard'], ['project.show', 'О проекте', 'project.*'], ['structure.index', 'Акты, сцены, кадры', 'structure.*'], ['checklist.index', 'Чек-лист', 'checklist.*'], ['assets.index', 'Материалы', 'assets.*'],
            ['catalog.index', 'Персонажи', null, ['characters']], ['catalog.index', 'Места', null, ['locations']], ['catalog.index', 'Реквизит и одежда', null, ['props']], ['documents.index', 'Документы', 'documents.*'], ['ai.index', 'ИИ-помощник', 'ai.*'], ['publications.index', 'Публикации', 'publications.*'], ['team.index', 'Команда', 'team.*'],
        ])
        <nav class="space-y-1">@foreach($nav as $entry) @php($url = $entry[0] === 'catalog.index' ? route($entry[0], $entry[3][0]) : route($entry[0])) @php($active = $entry[2] ? request()->routeIs($entry[2]) : (request()->routeIs('catalog.*') && request()->route('type') === $entry[3][0]))<a href="{{ $url }}" class="nav-link {{ $active ? 'nav-link-active' : '' }}">{{ $entry[1] }}</a>@endforeach</nav>
        <div class="mt-8 border-t border-white/10 pt-5 text-sm text-mist-200"><p class="truncate font-medium text-white">{{ auth()->user()->name }}</p><p class="truncate text-xs opacity-60">{{ auth()->user()->email }}</p><form method="POST" action="{{ route('logout') }}" class="mt-3">@csrf<button class="text-sm text-amber-400 hover:text-white">Выйти</button></form></div>
    </aside>
    <main class="min-w-0 p-4 md:p-7 lg:p-10">
        @if(session('success'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><p class="font-semibold">Проверьте данные:</p><ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('content')
    </main>
</div>
</body></html>
