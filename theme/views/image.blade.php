@pushOnce('foot', 'css:image')
<link href="{{ cmstheme($page, 'image.css') }}" rel="preload" as="style">
@endPushOnce

@if($file = cms($files, $data->file?->id ?? null))
	@include('cms::pic', ['file' => $file, 'main' => $data->main ?? false, 'sizes' => '(max-width: 1200px) 100vw, 1200px'])
@else
	<!-- no image file -->
@endif
