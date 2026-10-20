@props(['page', 'file', 'autoplay' => false])
@if($autoplay)
<video autoplay muted loop playsinline preload="metadata"
@else
<video preload="none" controls playsinline
@endif
	title="{{ cms($file, 'description')?->{cms($page, 'lang')} ?? '' }}"
	src="{{ cmsasset($page, $file) }}"
	@if($preview = current(array_reverse((array) cms($file, 'previews', []))))
		poster="{{ cmsasset($page, $file, $preview) }}"
	@endif
	{{ $attributes }}
>
	{{ $slot }}
</video>
