<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('ui.film')) — {{ __('ui.production') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div x-data="{ nav: false }" class="min-h-screen lg:grid lg:grid-cols-[260px_1fr]">
    <header class="sticky top-0 z-30 flex items-center justify-between bg-ink-950 px-4 py-3 text-white lg:hidden"><a href="{{ route('dashboard') }}" class="font-semibold">Тихий спутник</a><button type="button" @click="nav = !nav" class="rounded-lg border border-white/20 px-3 py-1.5">Меню</button></header>
    <aside :class="nav ? 'block' : 'hidden'" class="fixed inset-x-0 top-[52px] z-20 h-[calc(100vh-52px)] overflow-y-auto bg-ink-950 p-5 lg:sticky lg:top-0 lg:block lg:h-screen">
        <div class="mb-8 hidden lg:block"><p class="text-xs uppercase tracking-[0.26em] text-amber-400">Производство фильма</p><p class="mt-2 text-xl font-semibold text-white">Тихий спутник</p><p class="mt-1 text-xs text-mist-200/70">The Silent Companion</p></div>
        @php($navGroups = [
            'Фильм' => [
                ['dashboard', __('ui.nav.dashboard'), 'dashboard'],
                ['project.show', __('ui.nav.project'), 'project.*'],
                ['structure.index', __('ui.nav.structure'), 'structure.*'],
                ['checklist.index', __('ui.nav.checklist'), 'checklist.*'],
                ['documents.index', __('ui.nav.documents'), 'documents.*'],
            ],
            'Подготовка съёмок' => [
                ['catalog.index', __('ui.nav.characters'), null, ['characters']],
                ['catalog.index', __('ui.nav.locations'), null, ['locations']],
                ['catalog.index', __('ui.nav.props'), null, ['props']],
            ],
            'Сайт и публикации' => [
                ['publications.index', __('ui.nav.publications'), 'publications.*'],
                ['assets.index', __('ui.nav.assets'), 'assets.*'],
            ],
            'Команда' => [
                ['team.index', __('ui.nav.team'), 'team.*'],
            ],
            'Система' => [
                ['ai.index', __('ui.nav.ai'), 'ai.*'],
                ['activity.index', __('ui.nav.activity'), 'activity.*'],
            ],
        ])
        <nav class="space-y-5" aria-label="Основная навигация">
            @foreach($navGroups as $group => $entries)
                <section aria-labelledby="nav-group-{{ $loop->index }}">
                    <p id="nav-group-{{ $loop->index }}" class="px-3 text-[10px] font-semibold uppercase tracking-[0.2em] text-mist-200/40">{{ $group }}</p>
                    <div class="mt-1 space-y-1">
                        @foreach($entries as $entry)
                            @php($url = $entry[0] === 'catalog.index' ? route($entry[0], $entry[3][0]) : route($entry[0]))
                            @php($active = $entry[2] ? request()->routeIs($entry[2]) : (request()->routeIs('catalog.*') && request()->route('type') === $entry[3][0]))
                            <a href="{{ $url }}" class="nav-link {{ $active ? 'nav-link-active' : '' }}">{{ $entry[1] }}</a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </nav>
        <div class="mt-8 border-t border-white/10 pt-5 text-sm text-mist-200"><p class="truncate font-medium text-white">{{ auth()->user()->name }}</p><p class="truncate text-xs opacity-60">{{ auth()->user()->email }}</p><form method="POST" action="{{ route('logout') }}" class="mt-3">@csrf<button class="text-sm text-amber-400 hover:text-white">{{ __('ui.logout') }}</button></form></div>
    </aside>
    <main class="min-w-0 p-4 md:p-7 lg:p-10">
        @if(session('success'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><p class="font-semibold">{{ __('ui.check_data') }}</p><ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('content')
    </main>
</div>
</body></html>
