<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach($sitemaps as $sitemap)
    <sitemap>
        <loc>{{ $sitemap['loc'] }}</loc>
        <lastmod>{{ \Carbon\Carbon::parse($sitemap['lastmod'])->toAtomString() }}</lastmod>
    </sitemap>
    @endforeach
</sitemapindex>
