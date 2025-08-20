<meta property="og:title" content="{{ @$data->title }}" />
<meta property="og:description" content="{{ @$data->description }}" />
<meta property="og:site_name" content="{{ config('app.name') }}" />
<meta name="twitter:card" content="summary" />
<meta name="twitter:title" content="{{ @$data->title }}" />
<meta name="twitter:description" content="{{ @$data->description }}" />

@if(($file = cms($files, @$data->file?->id)) && ($preview = current(array_reverse((array) $file?->previews ?? [])))
    <meta name="twitter:image" content="{{ cmsurl($preview) }}" />
    <meta property="og:image" content="{{ cmsurl($preview) }}" />
    <meta property="og:image:url" content="{{ cmsurl($preview) }}" />
    <meta property="og:image:width" content="{{ current(array_reverse(array_keys($file->previews ?? []))) }}" />
@endif
