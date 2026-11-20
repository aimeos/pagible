@pushOnce('foot')
<link href="{{ cmstheme($page, 'video.css') }}" rel="preload" as="style">
@endPushOnce

@pushOnce('foot:caption')
	<script defer src="{{ cmstheme($page, 'caption.js') }}"></script>
@endPushOnce

@if($file = cms($files, $data->file?->id ?? null))
	<x-cms::video :page="$page" :file="$file">
		{{ __('Download file') }}: <a href="{{ cmsasset($page, $file) }}">{{ cmsasset($page, $file) }}</a>
		<div class="transcription" lang="{{ cms($page, 'lang') }}">{{ cmstext($page, $file, 'transcription') ?? '' }}</div>
	</x-cms::video>
	<div class="caption"></div>

	<script type="application/ld+json">{
		"@@context": "https://schema.org",
		"@@type": "VideoObject",
		"name": {!! cmsjson((string) cms($file, 'name')) !!},
		"contentUrl": {!! cmsjson(cmsasset($page, $file)) !!},
		"thumbnailUrl": {!! cmsjson(cmsasset($page, $file, current(array_reverse((array) cms($file, 'previews', []))) ?: null)) !!},
		"uploadDate": {!! cmsjson($file->created_at?->toIso8601String()) !!},
		"description": {!! cmsjson(trim((string) (cmstext($page, $file, 'description') ?? ''))) !!},
		"transcript": {!! cmsjson(trim((string) (cmstext($page, $file, 'transcription') ?? ''))) !!}
	}</script>
@else
	<!-- no video file -->
@endif
