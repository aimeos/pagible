@pushOnce('foot:caption')
	<script defer src="{{ cmstheme($page, 'caption.js') }}"></script>
@endPushOnce

@if($file = cms($files, $data->file?->id ?? null))
	<audio preload="metadata" controls
		title="{{ cms($file, 'description')?->{cms($page, 'lang')} ?? '' }}"
		src="{{ cmsurl(cms($file, 'path')) }}">
		<div class="transcription" lang="{{ cms($page, 'lang') }}">{{ cms($file, 'transcription')?->{cms($page, 'lang')} ?? '' }}</div>
	</audio>
	<div class="caption"></div>

	<script type="application/ld+json">{
		"@@context": "https://schema.org",
		"@@type": "AudioObject",
		"name": {{ Js::from(cms($file, 'description')?->{cms($page, 'lang')} ?? cms($page, 'title')) }},
		"contentUrl": {{ Js::from(cmsurl(cms($file, 'path'))) }},
		"uploadDate": "{{ $page->created_at->toIso8601String() }}"
		@if(cms($file, 'transcription')?->{cms($page, 'lang')} ?? null)
			, "transcript": {{ Js::from(cms($file, 'transcription')->{cms($page, 'lang')}) }}
		@endif
	}</script>
@else
	<!-- no audio file -->
@endif
