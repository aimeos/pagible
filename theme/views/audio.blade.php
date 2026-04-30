@pushOnce('js:caption')
	<script defer src="{{ cmstheme($page, 'caption.js') }}"></script>
@endPushOnce

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
