@once('caption')
	@push('js')
		<script defer src="{{ cmsasset('vendor/cms/theme/caption.js') }}"></script>
	@endpush
@endonce

@if($file = cms($files, @$data->file?->id))
	<audio preload="metadata" controls
		title="{{ @cms($file, 'description')?->{cms($page, 'lang')} }}"
		src="{{ cmsurl(cms($file, 'path')) }}">
		<div class="transcription" lang="{{ cms($page, 'lang') }}">{{ @cms($file, 'transcription')?->{cms($page, 'lang')} }}</div>
	</audio>
	<div class="caption"></div>
@else
	<!-- no audio file -->
@endif
