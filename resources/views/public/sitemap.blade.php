{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach(['ru', 'en', 'de'] as $locale)
    <url>
        <loc>{{ \App\Support\PublicUrl::home($locale) }}</loc>
        <lastmod>{{ now()->toAtomString() }}</lastmod>
        <changefreq>weekly</changefreq>
        <priority>1.0</priority>
    </url>
    @endforeach
    @foreach(['impressum', 'datenschutz'] as $page)
    <url>
        <loc>{{ route('public.legal', $page) }}</loc>
        <lastmod>{{ now()->toAtomString() }}</lastmod>
        <changefreq>yearly</changefreq>
        <priority>0.3</priority>
    </url>
    @endforeach
    @foreach($publications as $publication)
        @foreach(['ru', 'en', 'de'] as $locale)
        <url>
            <loc>{{ \App\Support\PublicUrl::publication($publication, $locale) }}</loc>
            <lastmod>{{ $publication->updated_at->toAtomString() }}</lastmod>
            <changefreq>monthly</changefreq>
            <priority>0.7</priority>
        </url>
        @endforeach
    @endforeach
</urlset>
