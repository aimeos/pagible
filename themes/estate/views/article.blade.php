@pushOnce('head')
<link href="{{ cmstheme($page, 'article.css') }}" rel="stylesheet">
@endPushOnce

@if($file = cms($files, $data->file?->id ?? null))
    <div class="article-cover">
        <h1 class="title">{{ cms($page, 'title') }}</h1>
        @include('cms::pic', ['file' => $file, 'main' => true, 'class' => 'cover', 'sizes' => '(max-width: 960px) 100vw, 960px'])
    </div>
@else
    <h1 class="title">{{ cms($page, 'title') }}</h1>
@endif

<div class="cms-text">@markdown($data->text ?? '')</div>

<script type="application/ld+json">{
    "@@context": "https://schema.org",
    "@@type": "Article",
    "headline": {!! cmsjson(cms($page, 'title')) !!},
    "datePublished": "{{ $page->created_at->toIso8601String() }}",
    "dateModified": "{{ $page->updated_at->toIso8601String() }}"
    @if($file)
        , "image": {!! cmsjson(cmsasset($page, $file)) !!}
    @endif
}</script>
