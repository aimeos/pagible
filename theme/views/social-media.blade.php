<meta property="og:title" content="{{ $data->title ?? '' }}" />
<meta property="og:description" content="{{ $data->description ?? '' }}" />
<meta property="og:site_name" content="{{ cmsconfig($page, 'website.data.title', cms($page->ancestorsAndSelf->first() ?? $page, 'name')) }}" />
<meta property="og:url" content="{{ cmsroute($page) }}" />
<meta property="og:type" content="website" />
@if($lang = str_replace('-', '_', (string) cms($page, 'lang')))
    @php
        // Open Graph expects language_TERRITORY, e.g. "de_DE" for "de" ("pt" is European Portuguese)
        $primary = \Locale::getPrimaryLanguage($lang);
        $region = \Locale::getRegion($lang) ?: \Locale::getRegion($primary === 'pt' ? 'pt_PT' : (method_exists(\Locale::class, 'addLikelySubtags')
            ? \Locale::addLikelySubtags($lang)
            : (string) \ResourceBundle::create('likelySubtags', 'ICUDATA', false)?->get($primary)));
    @endphp
    <meta property="og:locale" content="{{ $region ? $primary . '_' . $region : $primary }}" />
@endif
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
    @if($imageAlt = cms($file, 'description.'.cms($page, 'lang')) ?: cms($file, 'name'))
        <meta name="twitter:image:alt" content="{{ $imageAlt }}" />
        <meta property="og:image:alt" content="{{ $imageAlt }}" />
    @endif
@endif
