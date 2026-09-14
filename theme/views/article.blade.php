@pushOnce('head')
<link href="{{ cmstheme($page, 'article.css') }}" rel="stylesheet">
@endPushOnce

<h1 class="title">{{ cms($page, 'title') }}</h1>

@if($file = cms($files, data_get($data, 'files.0.id') ?? data_get($data, 'file.id')))
	@include('cms::pic', ['file' => $file, 'main' => true, 'class' => 'cover', 'sizes' => '(max-width: 960px) 100vw, 960px'])
@endif

<div class="cms-text">@markdown($data->text ?? '')</div>

<script type="application/ld+json">{
	"@@context": "https://schema.org",
	"@@type": {!! cmsjson(($data->{'article-type'} ?? null) ?: 'Article') !!},
	"headline": {!! cmsjson(cms($page, 'title')) !!},
	"datePublished": "{{ $page->created_at->toIso8601String() }}",
	"dateModified": "{{ $page->updated_at->toIso8601String() }}"
	@if($author = array_filter([
		'name' => trim((string) ($data->{'author-name'} ?? '')),
		'url' => trim((string) ($data->{'author-url'} ?? '')),
	]))
		, "author": {!! cmsjson(['@@type' => 'Person'] + $author) !!}
	@endif
	@if($file)
		, "image": {!! cmsjson(cmsasset($page, $file)) !!}
	@endif
}</script>
