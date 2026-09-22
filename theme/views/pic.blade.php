<picture class="{{ join(' ', (array) ($class ?? '')) }}" itemscope itemprop="image" itemtype="http://schema.org/ImageObject">
	<meta itemprop="representativeOfPage" content="{{ ($main ?? false) ? 'true' : 'false' }}">
    @if($preview = current(array_reverse((array) cms($file, 'previews', []))) ?: cms($file, 'path') )
        @php($src = cmsasset($page, $file, $preview))
        @php($srcset = cmssrcset($page, $file))
        @if($preload ?? false)
            @pushOnce('head', 'cms-lcp-preload')
            <link rel="preload" as="image" fetchpriority="high" href="{{ $src }}" @if($srcset) imagesrcset="{{ $srcset }}" imagesizes="{{ $sizes ?? '100vw' }}" @endif>
            @endPushOnce
        @endif
        <img itemprop="contentUrl"
            loading="{{ ($main ?? false) ? 'eager' : 'lazy' }}"
            fetchpriority="{{ ($main ?? false) ? 'high' : 'low' }}"
            srcset="{{ $srcset }}"
            src="{{ $src }}"
            sizes="{{ $sizes ?? '100vw' }}"
            alt="{{ cms($file, 'description')?->{cms($page, 'lang')} ?? cms($file, 'name') }}">
    @endif
</picture>
