@if($file = cms($files, $data->file?->id ?? null))
	<a href="{{ cmsasset($page, $file) }}">
		{{ __('Download file') }}
	</a>
@else
	<!-- no file -->
@endif
