@props(['page', 'file', 'main' => false, 'sizes' => '100vw', 'class' => ''])
<picture class="{{ join(' ', (array) $class) }}" itemscope itemprop="image" itemtype="http://schema.org/ImageObject"@if($attributes->isNotEmpty()) {{ $attributes }}@endif>
	<meta itemprop="representativeOfPage" content="{{ $main ? 'true' : 'false' }}">
	@if($preview = current(array_reverse((array) cms($file, 'previews', []))) ?: cms($file, 'path') )
		<img itemprop="contentUrl"
			loading="{{ $main ? 'eager' : 'lazy' }}"
			fetchpriority="{{ $main ? 'high' : 'low' }}"
			srcset="{{ collect((array) cms($file, 'previews', []))->map(fn($path, $width) => cmsasset($page, $file, (int) $width) . ' ' . $width . 'w')->implode(',') }}"
			src="{{ cmsasset($page, $file, $preview) }}"
			sizes="{{ $sizes }}"
			alt="{{ cms($file, 'description')?->{cms($page, 'lang')} ?? cms($file, 'name') }}">
	@endif
</picture>
