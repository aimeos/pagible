@if($file = cms($files, $data->file?->id ?? null))
	<a href="{{ cmsurl($file->path) }}">
		{{ __('Download file') }}
	</a>
@else
	<!-- no file -->
@endif
