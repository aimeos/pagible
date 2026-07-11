<picture class="{{ join(' ', (array) ($class ?? '')) }}" itemscope itemprop="image" itemtype="http://schema.org/ImageObject">
	<meta itemprop="representativeOfPage" content="{{ ($main ?? false) ? 'true' : 'false' }}">
    @if($preview = current(array_reverse((array) cms($file, 'previews', []))) ?: cms($file, 'path') )
        <img itemprop="contentUrl"
            loading="{{ ($main ?? false) ? 'eager' : 'lazy' }}"
            fetchpriority="{{ ($main ?? false) ? 'high' : 'low' }}"
            srcset="{{ cmssrcset(cms($file, 'previews')) }}"
            src="{{ cmsurl($preview) }}"
            sizes="{{ $sizes ?? '100vw' }}"
            alt="{{ cms($file, 'description')?->{cms($page, 'lang')} ?? cms($file, 'name') }}">
    @endif
</picture>
