@pushOnce('foot:caption')
	<script defer src="{{ cmstheme($page, 'caption.js') }}"></script>
@endPushOnce

@if($file = cms($files, $data->file?->id ?? null))
	<audio preload="none" controls
		title="{{ cmstext($page, $file, 'description') ?? '' }}"
		src="{{ cmsasset($page, $file) }}">
		<div class="transcription" lang="{{ cms($page, 'lang') }}">{{ cmstext($page, $file, 'transcription') ?? '' }}</div>
	</audio>
	<div class="caption"></div>

	<script type="application/ld+json">{
		"@@context": "https://schema.org",
		"@@type": "AudioObject",
		"name": {!! cmsjson(cmstext($page, $file, 'description') ?? cms($page, 'title')) !!},
		"contentUrl": {!! cmsjson(cmsasset($page, $file)) !!},
		"uploadDate": "{{ $page->created_at->toIso8601String() }}"
		@if(cmstext($page, $file, 'transcription'))
			, "transcript": {!! cmsjson(cmstext($page, $file, 'transcription')) !!}
		@endif
	}</script>
@else
	<!-- no audio file -->
@endif
