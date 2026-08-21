<!DOCTYPE html>
<html lang="de">
<head>
    @php($title = $page === 'impressum' ? 'Impressum' : 'Datenschutzerklärung')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $title }} — The Silent Companion</title>
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ route('public.legal', $page) }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-mist-50 text-ink-950">
    <header class="border-b border-white/10 bg-ink-950 text-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-5 py-5">
            <a href="{{ \App\Support\PublicUrl::home('de') }}" class="font-semibold">{{ $project?->title_de ?: 'Der stille Begleiter' }}</a>
            <a href="{{ \App\Support\PublicUrl::home('de') }}" class="text-sm text-mist-200/70 hover:text-white">Zur Website</a>
        </div>
    </header>
    <main class="mx-auto max-w-4xl px-5 py-12 md:py-20">
        <article class="prose-film">
            {!! \Illuminate\Support\Str::markdown($content, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
        </article>
    </main>
    <x-public-footer :title="$project?->title_de ?: 'Der stille Begleiter'" :settings="$settings" />
</body>
</html>
