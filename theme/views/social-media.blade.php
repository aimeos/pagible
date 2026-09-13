<meta property="og:title" content="{{ $data->title ?? '' }}" />
<meta property="og:description" content="{{ $data->description ?? '' }}" />
<meta property="og:site_name" content="{{ config('app.name') }}" />
<meta property="og:url" content="{{ cmsroute($page) }}" />
<meta property="og:type" content="website" />
<meta name="twitter:card" content="summary" />
<meta name="twitter:title" content="{{ $data->title ?? '' }}" />
<meta name="twitter:description" content="{{ $data->description ?? '' }}" />

@if(($file = cms($files, $data->file?->id ?? null)) && ($preview = current(array_reverse((array) cms($file, 'previews', []))) ?: cms($file, 'path')))
    <meta name="twitter:image" content="{{ cmsasset($page, $file, $preview) }}" />
    <meta property="og:image" content="{{ cmsasset($page, $file, $preview) }}" />
    <meta property="og:image:url" content="{{ cmsasset($page, $file, $preview) }}" />
    @if($width = current(array_reverse(array_keys((array) cms($file, 'previews', [])))))
        <meta property="og:image:width" content="{{ $width }}" />
    @endif
    @if($imageAlt = cms($file, 'description')?->{cms($page, 'lang')} ?: cms($file, 'name'))
        <meta name="twitter:image:alt" content="{{ $imageAlt }}" />
        <meta property="og:image:alt" content="{{ $imageAlt }}" />
    @endif
@endif
