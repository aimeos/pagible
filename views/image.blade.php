@if($file = cms($files, @$data->file?->id))
	@include('cms::pic', ['file' => $file, 'main' => @$data->main, 'sizes' => '(max-width: 960px) 100vw, 960px'])
@else
	<!-- no image file -->
@endif
