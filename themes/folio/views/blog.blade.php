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
            <div class="list-items list-years" data-list="{{ $data->{'parent-page'}?->value ?? '' }}">
                @foreach(collect($action->items())->groupBy(fn($item) => $item->created_at?->format('Y')) as $year => $items)
                    <section class="year">
                        <h3>{{ $year }}</h3>
                        <ul>
                            @foreach($items as $item)
                                <li>
                                    <a href="{{ cmsroute('cms.page', ['path' => $item->path], $item->domain ?? null) }}" class="list-item">
                                        <span class="name">{{ cms($item, 'title') }}</span>
                                        <time datetime="{{ $item->created_at?->toDateString() }}">@localDate($item->created_at)</time>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>
        @else
            <div class="list-items list-cards" data-list="{{ $data->{'parent-page'}?->value ?? '' }}">
                @foreach($action as $item)
                    <a href="{{ cmsroute('cms.page', ['path' => $item->path], $item->domain ?? null) }}" class="list-item">
                        <time datetime="{{ $item->created_at?->toDateString() }}">@localDate($item->created_at, 'medium')</time>
                        <h3>{{ cms($item, 'title') }}</h3>
                        @if($intro = collect(cms($item, 'content'))->firstWhere('type', 'article')?->data?->text)
                            <p>{{ str($intro)->markdown()->stripTags()->squish()->limit(160) }}</p>
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
