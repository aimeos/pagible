@props(['page', 'file', 'autoplay' => false])
@if($autoplay)
<video autoplay muted loop playsinline preload="metadata"
@else
<video preload="none" controls playsinline
@endif
	title="{{ cmstext($page, $file, 'description') ?? '' }}"
	src="{{ cmsasset($page, $file) }}"
	@if($preview = current(array_reverse((array) cms($file, 'previews', []))))
		poster="{{ cmsasset($page, $file, $preview) }}"
	@endif
	{{ $attributes }}
>
	{{ $slot }}
</video>
