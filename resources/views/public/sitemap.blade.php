{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>{{ route('home') }}</loc><changefreq>weekly</changefreq></url>
    @foreach ($gyms as $gym)
        <url>
            <loc>{{ $gym->websiteUrl() }}</loc>
            <lastmod>{{ $gym->updated_at->toAtomString() }}</lastmod>
            <changefreq>weekly</changefreq>
        </url>
    @endforeach
</urlset>
