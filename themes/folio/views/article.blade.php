@pushOnce('head')
<link href="{{ cmstheme($page, 'article.css') }}" rel="stylesheet">
@endPushOnce

<header class="post-head">
    <p class="meta">
        <time datetime="{{ $page->created_at?->toDateString() }}">@localDate($page->created_at, 'long')</time>
        <span>{{ __(':count min read', ['count' => max(1, (int) ceil(collect(cms($page, 'content'))->sum(fn($el) => str_word_count(strip_tags(($el->data->title ?? '') . ' ' . ($el->data->text ?? '')))) / 220))]) }}</span>
    </p>
    <h1 class="title">{{ cms($page, 'title') }}</h1>
    <div class="lead cms-text">@markdown($data->text ?? '')</div>
</header>

@if($file = cms($files, data_get($data, 'files.0.id') ?? data_get($data, 'file.id')))
    <x-cms::image :page="$page" :file="$file" main class="cover" sizes="(max-width: 860px) 100vw, 860px" />
@endif

<script type="application/ld+json">{
    "@@context": "https://schema.org",
    "@@type": "BlogPosting",
    "headline": {!! cmsjson(cms($page, 'title')) !!},
    "datePublished": "{{ $page->created_at?->toIso8601String() }}",
    "dateModified": "{{ $page->updated_at?->toIso8601String() }}"
    @if($author = array_filter([
        'name' => trim((string) ($data->{'author-name'} ?? '')) ?: (string) cmsconfig($page, 'folio::profile.data.name'),
        'url' => trim((string) ($data->{'author-url'} ?? '')),
    ]))
        , "author": {!! cmsjson(['@@type' => 'Person'] + $author) !!}
    @endif
    @if($images = collect(data_get($data, 'files') ?: [data_get($data, 'file')])
        ->map(fn($item) => cmsasset($page, cms($files, data_get($item, 'id'))))
        ->filter()
        ->unique()
        ->values()
        ->all())
        , "image": {!! cmsjson($images) !!}
    @endif
}</script>
