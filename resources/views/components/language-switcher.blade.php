@props(['publication' => null, 'public' => false])
@php($locales = ['ru' => 'RU', 'en' => 'EN', 'de' => 'DE'])
<nav class="flex items-center gap-1.5 rounded-lg bg-black/25 p-1" aria-label="Language / Sprache / Язык">
    @foreach($locales as $locale => $label)
        @php($url = $publication ? \App\Support\PublicUrl::publication($publication, $locale) : \App\Support\PublicUrl::home($locale))
        <a href="{{ $url }}" hreflang="{{ $locale }}" lang="{{ $locale }}" title="{{ __('ui.languages.'.$locale) }}" aria-label="{{ __('ui.languages.'.$locale) }}" class="inline-flex h-8 items-center gap-1.5 rounded-md px-2 text-[11px] font-semibold text-white transition {{ app()->getLocale() === $locale ? 'bg-white/20 ring-1 ring-white/40' : 'opacity-75 hover:bg-white/10 hover:opacity-100' }}">
            @if($locale === 'ru')
                <svg viewBox="0 0 24 16" class="h-4 w-6 overflow-hidden rounded-[2px] shadow" aria-hidden="true"><path fill="#fff" d="M0 0h24v16H0z"/><path fill="#1c57a7" d="M0 5.33h24v5.34H0z"/><path fill="#d52b1e" d="M0 10.67h24V16H0z"/></svg>
            @elseif($locale === 'en')
                <svg viewBox="0 0 24 16" class="h-4 w-6 overflow-hidden rounded-[2px] shadow" aria-hidden="true"><path fill="#012169" d="M0 0h24v16H0z"/><path stroke="#fff" stroke-width="3.2" d="m0 0 24 16M24 0 0 16"/><path stroke="#c8102e" stroke-width="1.6" d="m0 0 24 16M24 0 0 16"/><path fill="#fff" d="M10 0h4v16h-4zM0 6h24v4H0z"/><path fill="#c8102e" d="M11 0h2v16h-2zM0 7h24v2H0z"/></svg>
            @else
                <svg viewBox="0 0 24 16" class="h-4 w-6 overflow-hidden rounded-[2px] shadow" aria-hidden="true"><path fill="#000" d="M0 0h24v5.33H0z"/><path fill="#dd0000" d="M0 5.33h24v5.34H0z"/><path fill="#ffce00" d="M0 10.67h24V16H0z"/></svg>
            @endif
            <span>{{ $label }}</span>
        </a>
    @endforeach
</nav>
