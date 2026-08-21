@props(['title', 'settings' => null])

<footer class="border-t border-white/10 bg-ink-950 py-10 text-mist-50">
    <div class="mx-auto flex max-w-7xl flex-col gap-4 px-5 text-sm text-mist-200/70 md:flex-row md:justify-between">
        <p>© {{ now()->year }} «{{ $title }}»</p>
        <nav class="flex flex-wrap gap-x-4 gap-y-2" aria-label="Ссылки в подвале">
            @foreach($settings?->official_links ?? [] as $link)
                <a href="{{ $link['url'] }}" target="_blank" rel="noopener" class="hover:text-white">{{ __('ui.official_page') }}</a>
            @endforeach
            @if($settings?->contact)<span>{{ $settings->contact }}</span>@endif
            <a href="{{ route('public.legal', 'impressum') }}" class="hover:text-white">Impressum</a>
            <a href="{{ route('public.legal', 'datenschutz') }}" class="hover:text-white">Datenschutz</a>
            <a href="{{ route('login') }}" class="hover:text-white">{{ __('ui.team_login') }}</a>
        </nav>
    </div>
</footer>
