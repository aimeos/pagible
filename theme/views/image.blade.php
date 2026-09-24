@pushOnce('foot', 'css:image')
<link href="{{ cmstheme($page, 'image.css') }}" rel="preload" as="style">
@endPushOnce

@if($file = cms($files, $data->file?->id ?? null))
	@include('cms::pic', ['file' => $file, 'main' => $data->main ?? false, 'sizes' => $sizes ?? '(max-width: 640px) 90vw, (max-width: 1200px) calc(100vw - 4rem), 1136px'])
@else
	<!-- no image file -->
@endif
