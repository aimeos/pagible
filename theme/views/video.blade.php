@pushOnce('foot')
<link href="{{ cmstheme($page, 'video.css') }}" rel="preload" as="style">
@endPushOnce

@pushOnce('foot:caption')
	<script defer src="{{ cmstheme($page, 'caption.js') }}"></script>
@endPushOnce

@if($file = cms($files, $data->file?->id ?? null))
	<video preload="metadata" controls playsinline
		title="{{ cms($file, 'description')?->{cms($page, 'lang')} ?? '' }}"
		src="{{ cmsasset($page, $file) }}"
		@if($preview = current(array_reverse((array) cms($file, 'previews', []))))
			poster="{{ cmsasset($page, $file, $preview) }}"
		@endif
	>
		{{ __('Download file') }}: <a href="{{ cmsasset($page, $file) }}">{{ cmsasset($page, $file) }}</a>
		<div class="transcription" lang="{{ cms($page, 'lang') }}">{{ cms($file, 'transcription')?->{cms($page, 'lang')} ?? '' }}</div>
	</video>
	<div class="caption"></div>

	<script type="application/ld+json">{
		"@@context": "https://schema.org",
		"@@type": "VideoObject",
		"name": {!! cmsjson(cms($file, 'description')?->{cms($page, 'lang')} ?? cms($page, 'title')) !!},
		"contentUrl": {!! cmsjson(cmsasset($page, $file)) !!},
		"uploadDate": "{{ $page->created_at->toIso8601String() }}"
		@if($preview = current(array_reverse((array) cms($file, 'previews', []))))
			, "thumbnailUrl": {!! cmsjson(cmsasset($page, $file, $preview)) !!}
		@endif
		@if(cms($file, 'transcription')?->{cms($page, 'lang')} ?? null)
			, "transcript": {!! cmsjson(cms($file, 'transcription')->{cms($page, 'lang')}) !!}
		@endif
	}</script>
@else
	<!-- no video file -->
@endif
