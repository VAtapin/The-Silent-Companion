@props(['publication' => null, 'public' => false])
@php
    $locales = ['ru' => '🇷🇺', 'en' => '🇬🇧', 'de' => '🇩🇪'];
@endphp
<nav class="flex items-center gap-1" aria-label="Language / Sprache / Язык">
    @foreach($locales as $locale => $flag)
        @php
            $url = $public
                ? ($publication ? \App\Support\PublicUrl::publication($publication, $locale) : \App\Support\PublicUrl::home($locale))
                : route('locale.switch', $locale);
        @endphp
        <a href="{{ $url }}" hreflang="{{ $locale }}" lang="{{ $locale }}" title="{{ __('ui.languages.'.$locale) }}" aria-label="{{ __('ui.languages.'.$locale) }}" class="inline-flex h-7 w-8 items-center justify-center rounded-md text-base transition hover:bg-white/10 {{ app()->getLocale() === $locale ? 'bg-white/15 ring-1 ring-white/30' : 'opacity-70 hover:opacity-100' }}">{{ $flag }}</a>
    @endforeach
</nav>
