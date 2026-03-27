@if($file = cms($files, @$data->file?->id))
	@include('cms::pic', ['file' => $file, 'main' => @$data->main, 'sizes' => '(max-width: 1200px) 100vw, 1200px'])
@else
	<!-- no image file -->
@endif
