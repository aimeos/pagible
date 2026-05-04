@pushOnce('js')
<link href="{{ cmstheme($page, 'video.css') }}" rel="stylesheet">
@endPushOnce

@pushOnce('js:caption')
	<script defer src="{{ cmstheme($page, 'caption.js') }}"></script>
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

	<script type="application/ld+json">{
		"@@context": "https://schema.org",
		"@@type": "VideoObject",
		"name": {{ Js::from(@cms($file, 'description')?->{cms($page, 'lang')} ?? cms($page, 'title')) }},
		"contentUrl": {{ Js::from(cmsurl(cms($file, 'path'))) }},
		"uploadDate": "{{ $page->created_at->toIso8601String() }}"
		@if($preview = current(array_reverse((array) cms($file, 'previews', []))))
			, "thumbnailUrl": {{ Js::from(cmsurl($preview)) }}
		@endif
		@if(@cms($file, 'transcription')?->{cms($page, 'lang')})
			, "transcript": {{ Js::from(cms($file, 'transcription')->{cms($page, 'lang')}) }}
		@endif
	}</script>
@else
	<!-- no video file -->
@endif
