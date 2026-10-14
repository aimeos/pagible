@pushOnce('head')
<link href="{{ cmstheme($page, 'list.css') }}" rel="stylesheet">
@endPushOnce

@pushOnce('foot')
<script defer src="{{ cmstheme($page, 'list.js') }}"></script>
@endPushOnce

@if($action?->count())
    <div class="list">
        @if($data->title ?? null)
            <h2>{{ $data->title }}</h2>
        @endif
        @if(($data->layout ?? 'cards') === 'list')
            <div class="list-items list-rows" data-list="{{ $data->{'parent-page'}?->value ?? '' }}">
                @foreach($action as $item)
                    <a href="{{ cmsroute('cms.page', ['path' => $item->path], $item->domain ?? null) }}" class="list-item">
                        <time datetime="{{ $item->created_at?->toDateString() }}">@localDate($item->created_at, 'medium')</time>
                        <span class="name">{{ cms($item, 'title') }}</span>
                        <span class="arrow" aria-hidden="true">→</span>
                    </a>
                @endforeach
            </div>
        @else
            <div class="list-items list-cards" data-list="{{ $data->{'parent-page'}?->value ?? '' }}">
                @foreach($action as $item)
                    <a href="{{ cmsroute('cms.page', ['path' => $item->path], $item->domain ?? null) }}" class="list-item">
                        @if(($article = collect(cms($item, 'content'))->firstWhere('type', 'article')) && ($file = cms(cms($item, 'files'), data_get($article, 'data.files.0.id') ?? data_get($article, 'data.file.id'))))
                            <span class="cover">
                                @include('cms::pic', ['file' => $file, 'sizes' => '(max-width: 768px) 100vw, 33vw'])
                            </span>
                        @endif
                        <span class="meta">
                            <time datetime="{{ $item->created_at?->toDateString() }}">@localDate($item->created_at, 'medium')</time>
                            <span>{{ __(':count min read', ['count' => max(1, (int) ceil(collect(cms($item, 'content'))->sum(fn($el) => str_word_count(strip_tags(($el->data->title ?? '') . ' ' . ($el->data->text ?? '')))) / 220))]) }}</span>
                        </span>
                        <h3>{{ cms($item, 'title') }}</h3>
                        @if($intro = data_get($article, 'data.text'))
                            <p>{{ str($intro)->markdown()->stripTags()->squish()->limit(140) }}</p>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
        @if($data->{'parent-page'}?->value ?? null)
            {{ $action->appends(request()->query())->links() }}
        @endif
    </div>
@endif
