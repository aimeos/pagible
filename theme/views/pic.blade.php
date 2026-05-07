<picture class="{{ $class ?? '' }}" itemscope itemprop="image" itemtype="http://schema.org/ImageObject">
	<meta itemprop="representativeOfPage" content="{{ ($main ?? false) ? 'true' : 'false' }}">
    @if($preview = current(array_reverse((array) $file?->previews ?? [])) ?: $file?->path )
        <img itemprop="contentUrl"
            loading="{{ ($main ?? false) ? 'eager' : 'lazy' }}"
            fetchpriority="{{ ($main ?? false) ? 'high' : 'low' }}"
            srcset="{{ cmssrcset($file?->previews) }}"
            src="{{ cmsurl($preview) }}"
            sizes="{{ $sizes ?? '100vw' }}"
            alt="{{ cms($file, 'description')?->{cms($page, 'lang')} ?? cms($file, 'name') }}">
    @endif
</picture>
