<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $project?->title_ru ?? 'Тихий спутник' }} — фильм</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-ink-950 text-mist-50">
<header class="absolute inset-x-0 top-0 z-20">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-6">
        <a href="{{ route('public.home') }}" class="font-semibold">Тихий спутник</a>
        <a href="{{ route('login') }}" class="rounded-full border border-white/20 px-4 py-2 text-sm hover:bg-white/10">Вход для команды</a>
    </div>
</header>

<main>
    <section class="relative min-h-[88vh] overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_70%_30%,#34423e_0%,#17201e_42%,#111716_75%)]"></div>
        @if($settings?->poster_asset_id)
            <img src="{{ route('public.media', $settings->poster_asset_id) }}" alt="" aria-hidden="true" class="absolute inset-0 h-full w-full scale-110 object-cover opacity-20 blur-xl">
            <div class="absolute inset-0 bg-gradient-to-r from-ink-950 via-ink-950/85 to-ink-950/45"></div>
        @endif
        <div class="relative mx-auto grid min-h-[88vh] max-w-7xl items-center gap-12 px-5 pb-16 pt-28 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,25rem)] lg:py-28">
            <div class="max-w-3xl">
                <p class="text-xs uppercase tracking-[.34em] text-amber-400">Короткометражный фильм</p>
                <h1 class="mt-5 text-5xl font-semibold text-white md:text-7xl">{{ $project?->title_ru ?? 'Тихий спутник' }}</h1>
                <p class="mt-3 text-xl text-mist-200 md:text-2xl">{{ $project?->title_en ?? 'The Silent Companion' }}</p>
                <p class="mt-8 max-w-2xl text-xl leading-8 text-mist-100">{{ $project?->tagline }}</p>
                <p class="mt-5 max-w-2xl leading-7 text-mist-200/80">{{ $settings?->public_summary ?: $project?->logline }}</p>
            </div>
            @if($settings?->poster_asset_id)
                <figure class="mx-auto w-full max-w-sm overflow-hidden rounded-[1.75rem] border border-white/15 bg-black shadow-2xl shadow-black/50">
                    <img src="{{ route('public.media', $settings->poster_asset_id) }}" alt="Афиша фильма «{{ $project?->title_ru ?? 'Тихий спутник' }}»" class="aspect-[2/3] h-auto w-full object-cover">
                </figure>
            @endif
        </div>
    </section>

    @if($publications->isNotEmpty())
        <section class="bg-mist-50 py-20 text-ink-900">
            <div class="mx-auto max-w-7xl px-5">
                <p class="text-xs uppercase tracking-[.28em] text-amber-500">Из дневника фильма</p>
                <h2 class="mt-3 text-3xl md:text-4xl">Опубликованные материалы</h2>
                <div class="mt-10 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($publications as $publication)
                        <article class="overflow-hidden rounded-3xl bg-white shadow-sm">
                            @php($media = $publication->assets->first())
                            @if($media?->mime_type && str_starts_with($media->mime_type, 'image/'))
                                <img src="{{ route('public.media', $media) }}" alt="{{ $publication->title }}" class="h-64 w-full object-cover">
                            @elseif($media?->mime_type && str_starts_with($media->mime_type, 'video/'))
                                <video controls preload="metadata" class="h-64 w-full bg-black object-contain">
                                    <source src="{{ route('public.media', $media) }}" type="{{ $media->mime_type }}">
                                </video>
                            @endif
                            <div class="p-6">
                                <p class="text-xs uppercase tracking-wider text-amber-600">{{ $publication->type }}</p>
                                <h3 class="mt-2 text-xl">{{ $publication->title }}</h3>
                                <p class="mt-3 leading-6 text-slate-600">{{ $publication->description }}</p>
                                <p class="mt-4 text-xs text-slate-400">{{ $publication->published_at?->format('d.m.Y') }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if($donations)
        <section class="bg-amber-400 py-20 text-ink-950">
            <div class="mx-auto grid max-w-5xl gap-10 px-5 md:grid-cols-[1fr_auto]">
                <div>
                    <p class="text-xs uppercase tracking-[.28em]">Поддержка проекта</p>
                    <h2 class="mt-3 text-3xl md:text-4xl">{{ $donations->title }}</h2>
                    <p class="mt-5 max-w-2xl whitespace-pre-wrap leading-7">{{ $donations->goal_description }}</p>
                    @if($donations->bank_details)
                        <div class="mt-6 whitespace-pre-wrap rounded-2xl bg-white/45 p-5">{{ $donations->bank_details }}</div>
                    @endif
                    @if($donations->additional_methods)
                        <p class="mt-5 whitespace-pre-wrap">{{ $donations->additional_methods }}</p>
                    @endif
                    @if($donations->payment_url)
                        <a href="{{ $donations->payment_url }}" rel="noopener" target="_blank" class="mt-6 inline-flex rounded-xl bg-ink-950 px-5 py-3 font-semibold text-white">Поддержать проект</a>
                    @endif
                </div>
                @if($donations->qr_asset_id)
                    <img src="{{ route('public.media', $donations->qr_asset_id) }}" alt="QR-код для поддержки" class="h-52 w-52 self-center rounded-2xl bg-white p-3">
                @endif
            </div>
        </section>
    @endif
</main>

<footer class="border-t border-white/10 bg-ink-950 py-10">
    <div class="mx-auto flex max-w-7xl flex-col gap-4 px-5 text-sm text-mist-200/70 md:flex-row md:justify-between">
        <p>© {{ now()->year }} «Тихий спутник»</p>
        <div class="flex flex-wrap gap-4">
            @foreach($settings?->official_links ?? [] as $link)
                <a href="{{ $link['url'] }}" target="_blank" rel="noopener" class="hover:text-white">Официальная страница</a>
            @endforeach
            @if($settings?->contact)
                <span>{{ $settings->contact }}</span>
            @endif
        </div>
    </div>
</footer>
</body>
</html>
