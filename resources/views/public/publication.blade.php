<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    @php($title = $publication->localized('title'))
    @php($description = $publication->localized('description'))
    @php($canonicalUrl = \App\Support\PublicUrl::publication($publication))
    @php($image = $publication->assets->first(fn ($asset) => $asset->mime_type && str_starts_with($asset->mime_type, 'image/')))
    @php($seoImage = $image ? route('public.media', $image) : ($settings?->poster ? route('public.media', $settings->poster) : null))
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $title }} — {{ __('ui.film') }}</title><meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags((string) $description), 200, '') }}"><link rel="canonical" href="{{ $canonicalUrl }}">
    @foreach(['ru', 'en', 'de'] as $locale)<link rel="alternate" hreflang="{{ $locale }}" href="{{ \App\Support\PublicUrl::publication($publication, $locale) }}">@endforeach
    <meta property="og:type" content="article"><meta property="og:url" content="{{ $canonicalUrl }}"><meta property="og:title" content="{{ $title }}"><meta property="og:description" content="{{ \Illuminate\Support\Str::limit(strip_tags((string) $description), 200, '') }}">@if($seoImage)<meta property="og:image" content="{{ $seoImage }}">@endif<meta name="twitter:card" content="summary_large_image">@if($seoImage)<meta name="twitter:image" content="{{ $seoImage }}">@endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-mist-50 text-ink-950"><header class="border-b border-slate-200 bg-ink-950 text-white"><div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-5 py-5"><a href="{{ \App\Support\PublicUrl::home() }}" class="font-semibold">{{ $project?->localized('title_ru') ?: __('ui.film') }}</a><x-language-switcher :publication="$publication" public /></div></header>
<main class="mx-auto max-w-4xl px-5 py-12 md:py-20"><a href="{{ \App\Support\PublicUrl::home() }}#materials" class="text-sm font-semibold text-amber-700">← {{ __('ui.back_to_home') }}</a><article class="mt-8"><p class="text-xs uppercase tracking-[.22em] text-amber-600">{{ __('ui.types.'.$publication->type) }} · {{ $publication->published_at?->format('d.m.Y') }}</p><h1 class="mt-4 text-4xl font-semibold md:text-6xl">{{ $title }}</h1><div class="mt-8 whitespace-pre-line text-lg leading-8 text-slate-700">{{ $description }}</div>
    @if($publication->assets->isNotEmpty())<div class="mt-12 space-y-8">@foreach($publication->assets as $asset)
        @if($asset->youtubeId())<div class="aspect-video overflow-hidden rounded-2xl bg-black"><iframe class="h-full w-full" src="https://www.youtube-nocookie.com/embed/{{ $asset->youtubeId() }}?rel=0" title="{{ $title }}" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe></div>
        @elseif($asset->mime_type && str_starts_with($asset->mime_type, 'image/'))<img src="{{ route('public.media', $asset) }}" alt="{{ $asset->title }}" class="w-full rounded-2xl object-contain">
        @elseif($asset->mime_type && str_starts_with($asset->mime_type, 'video/'))<video controls preload="metadata" class="w-full rounded-2xl bg-black"><source src="{{ route('public.media', $asset) }}" type="{{ $asset->mime_type }}"></video>@endif
    @endforeach</div>@endif
    </article></main><x-public-footer :title="$project?->localized('title_ru') ?: __('ui.film')" :settings="$settings" /></body></html>
