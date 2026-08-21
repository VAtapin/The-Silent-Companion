<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    @php
        $title = $project?->localized('title_ru') ?: __('ui.film');
        $summary = $settings?->localized('public_summary') ?: $project?->localized('logline');
        $seoTitle = $title.' — '.__('ui.short_film');
        $seoDescription = \Illuminate\Support\Str::limit(trim($summary ?: __('ui.fallback_description')), 200, '');
        $canonicalUrl = \App\Support\PublicUrl::home();
        $seoImage = $settings?->poster ? route('public.media', ['asset' => $settings->poster, 'v' => $settings->poster->updated_at?->timestamp]) : null;
        $ogLocales = ['ru' => 'ru_RU', 'en' => 'en_GB', 'de' => 'de_DE'];
        $movieSchema = array_filter(['@context' => 'https://schema.org', '@type' => 'Movie', 'name' => $title, 'alternateName' => $project?->title_en, 'description' => $seoDescription, 'image' => $seoImage, 'url' => $canonicalUrl, 'inLanguage' => app()->getLocale(), 'genre' => $project?->genre], fn ($value) => filled($value));
    @endphp
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDescription }}"><meta name="robots" content="index, follow, max-image-preview:large"><meta name="theme-color" content="#111716">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    @foreach(['ru', 'en', 'de'] as $locale)<link rel="alternate" hreflang="{{ $locale }}" href="{{ \App\Support\PublicUrl::home($locale) }}">@endforeach
    <link rel="alternate" hreflang="x-default" href="{{ \App\Support\PublicUrl::home('ru') }}">
    <meta property="og:type" content="website"><meta property="og:locale" content="{{ $ogLocales[app()->getLocale()] }}"><meta property="og:site_name" content="{{ $title }}"><meta property="og:url" content="{{ $canonicalUrl }}"><meta property="og:title" content="{{ $seoTitle }}"><meta property="og:description" content="{{ $seoDescription }}">
    @if($seoImage)<meta property="og:image" content="{{ $seoImage }}"><meta property="og:image:secure_url" content="{{ $seoImage }}"><meta property="og:image:type" content="{{ $settings->poster->mime_type ?: 'image/jpeg' }}"><meta property="og:image:alt" content="{{ $title }}">@endif
    <meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="{{ $seoTitle }}"><meta name="twitter:description" content="{{ $seoDescription }}">@if($seoImage)<meta name="twitter:image" content="{{ $seoImage }}">@endif
    <script type="application/ld+json">{!! json_encode($movieSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-ink-950 text-mist-50">
<header class="absolute inset-x-0 top-0 z-20"><div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-6"><a href="{{ \App\Support\PublicUrl::home() }}" class="font-semibold">{{ $title }}</a><x-language-switcher public /></div></header>
<main>
    <section class="relative min-h-screen overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_70%_30%,#34423e_0%,#17201e_42%,#111716_75%)]"></div>
        @if($settings?->poster_asset_id)<img src="{{ route('public.media', $settings->poster_asset_id) }}" alt="{{ $title }}" class="absolute inset-0 h-full w-full object-cover object-[68%_center]"><div class="absolute inset-0 bg-gradient-to-r from-ink-950/95 via-ink-950/65 to-ink-950/10"></div><div class="absolute inset-0 bg-gradient-to-t from-ink-950/70 via-transparent to-ink-950/25"></div>@endif
        <div class="relative mx-auto flex min-h-screen max-w-7xl items-center px-5 pb-16 pt-28 lg:py-28"><div class="max-w-3xl"><p class="text-xs uppercase tracking-[.34em] text-amber-400">{{ __('ui.short_film') }}</p><h1 class="mt-5 text-5xl font-semibold text-white md:text-7xl">{{ $title }}</h1>@if(app()->getLocale() === 'ru' && $project?->title_en)<p class="mt-3 text-xl text-mist-200 md:text-2xl">{{ $project->title_en }}</p>@endif<p class="mt-8 max-w-2xl text-xl leading-8 text-mist-100">{{ $project?->localized('tagline') }}</p><p class="mt-5 max-w-2xl leading-7 text-mist-200/80">{{ $summary }}</p></div></div>
    </section>
    @if($publications->isNotEmpty())
        <section id="materials" class="bg-mist-50 py-20 text-ink-900"><div class="mx-auto max-w-7xl px-5"><p class="text-xs uppercase tracking-[.28em] text-amber-500">{{ __('ui.journal') }}</p><h2 class="mt-3 text-3xl md:text-4xl">{{ __('ui.published_materials') }}</h2><div class="mt-10 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            @foreach($publications as $publication)
                @php($media = $publication->assets->first())
                <article class="flex overflow-hidden rounded-3xl bg-white shadow-sm"><a href="{{ \App\Support\PublicUrl::publication($publication) }}" class="flex w-full flex-col">
                    @if($media?->youtubeId())<div class="relative aspect-video overflow-hidden bg-black"><img src="https://i.ytimg.com/vi/{{ $media->youtubeId() }}/hqdefault.jpg" alt="{{ $publication->localized('title') }}" class="h-full w-full object-cover"><span class="absolute inset-0 grid place-items-center"><span class="grid h-14 w-14 place-items-center rounded-full bg-white/90 text-2xl text-ink-950">▶</span></span></div>
                    @elseif($media?->mime_type && str_starts_with($media->mime_type, 'image/'))<img src="{{ route('public.media', $media) }}" alt="{{ $publication->localized('title') }}" class="aspect-video w-full object-cover">
                    @elseif($media?->mime_type && str_starts_with($media->mime_type, 'video/'))<div class="grid aspect-video place-items-center bg-ink-950 text-white"><span class="grid h-14 w-14 place-items-center rounded-full border border-white/40 text-2xl">▶</span></div>@endif
                    <div class="flex flex-1 flex-col p-6"><p class="text-xs uppercase tracking-wider text-amber-600">{{ __('ui.types.'.$publication->type) }}</p><h3 class="mt-2 text-xl">{{ $publication->localized('title') }}</h3><p class="mt-3 line-clamp-3 leading-6 text-slate-600">{{ \Illuminate\Support\Str::limit(strip_tags((string) $publication->localized('description')), 180) }}</p><div class="mt-auto flex items-end justify-between gap-4 pt-5"><span class="font-semibold text-amber-700">{{ __('ui.read_more') }} →</span><time class="text-xs text-slate-400">{{ $publication->published_at?->format('d.m.Y') }}</time></div></div>
                </a></article>
            @endforeach
        </div></div></section>
    @endif
    @if($donations)<section class="bg-amber-400 py-20 text-ink-950"><div class="mx-auto grid max-w-5xl gap-10 px-5 md:grid-cols-[1fr_auto]"><div><p class="text-xs uppercase tracking-[.28em]">{{ __('ui.support_label') }}</p><h2 class="mt-3 text-3xl md:text-4xl">{{ $donations->localized('title') }}</h2><p class="mt-5 max-w-2xl whitespace-pre-wrap leading-7">{{ $donations->localized('goal_description') }}</p>@if($donations->bank_details)<div class="mt-6 whitespace-pre-wrap rounded-2xl bg-white/45 p-5">{{ $donations->bank_details }}</div>@endif @if($donations->localized('additional_methods'))<p class="mt-5 whitespace-pre-wrap">{{ $donations->localized('additional_methods') }}</p>@endif @if($donations->payment_url)<a href="{{ $donations->payment_url }}" rel="noopener" target="_blank" class="mt-6 inline-flex rounded-xl bg-ink-950 px-5 py-3 font-semibold text-white">{{ __('ui.support') }}</a>@endif</div>@if($donations->qr_asset_id)<img src="{{ route('public.media', $donations->qr_asset_id) }}" alt="QR" class="h-52 w-52 self-center rounded-2xl bg-white p-3">@endif</div></section>@endif
</main>
<footer class="border-t border-white/10 bg-ink-950 py-10"><div class="mx-auto flex max-w-7xl flex-col gap-4 px-5 text-sm text-mist-200/70 md:flex-row md:justify-between"><p>© {{ now()->year }} «{{ $title }}»</p><div class="flex flex-wrap gap-4">@foreach($settings?->official_links ?? [] as $link)<a href="{{ $link['url'] }}" target="_blank" rel="noopener" class="hover:text-white">{{ __('ui.official_page') }}</a>@endforeach @if($settings?->contact)<span>{{ $settings->contact }}</span>@endif <a href="{{ route('login') }}" class="hover:text-white">{{ __('ui.team_login') }}</a></div></div></footer>
</body></html>
