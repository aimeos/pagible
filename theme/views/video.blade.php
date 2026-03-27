@pushOnce('js:caption')
	<script defer src="{{ cmsasset('vendor/cms/theme/caption.js') }}"></script>
@endPushOnce

@if($file = cms($files, @$data->file?->id))
	<video preload="metadata" controls playsinline
		title="{{ @cms($file, 'description')?->{cms($page, 'lang')} }}"
		src="{{ cmsurl(cms($file, 'path')) }}"
		@if($preview = current(array_reverse((array) cms($file, 'previews', []))))
			poster="{{ cmsurl($preview) }}"
		@endif
	>
		{{ __('Download file') }}: <a href="{{ cmsurl(cms($file, 'path')) }}">{{ cmsurl(cms($file, 'path')) }}</a>
		<div class="transcription" lang="{{ cms($page, 'lang') }}">{{ @cms($file, 'transcription')?->{cms($page, 'lang')} }}</div>
	</video>
	<div class="caption"></div>
@else
	<!-- no video file -->
@endif
