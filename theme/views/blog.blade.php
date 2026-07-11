@pushOnce('foot')
<link href="{{ cmstheme($page, 'list.css') }}" rel="preload" as="style">
<script defer src="{{ cmstheme($page, 'list.js') }}"></script>
@endPushOnce

@if($first = $action?->first())
    <div class="list">
        @if($data->title ?? null)
            <h2>{{ $data->title }}</h2>
        @endif
        @if(($data->layout ?? 'default') === 'default')
            <div class="list-items list-default" data-list="{{ $data->{'parent-page'}?->value ?? '' }}">
                <div class="first">
                    @include('cms::list-item', ['item' => $first, 'layout' => 'cards'])
                </div>
                <div class="second">
                    @foreach($action?->skip(1) ?? [] as $item)
                        @include('cms::list-item', ['item' => $item, 'layout' => $data->layout ?? 'default'])
                    @endforeach
                </div>
            </div>
        @else
            <div class="list-items list-{{ $data->layout ?? 'default' }}" data-list="{{ $data->{'parent-page'}?->value ?? '' }}">
                @foreach($action ?? [] as $item)
                    @include('cms::list-item', ['item' => $item, 'layout' => $data->layout ?? 'default'])
                @endforeach
            </div>
        @endif
        {{ $action?->appends(request()->query())?->links() }}

        <script type="application/ld+json">{
            "@@context": "https://schema.org",
            "@@type": "Blog",
            "name": {!! cmsjson($data->title ?? cms($page, 'title')) !!},
            "blogPost": [
            @foreach($action ?? [] as $item)
                {
                    "@@type": "BlogPosting",
                    "headline": {!! cmsjson(cms($item, 'title')) !!},
                    "url": {!! cmsjson(route('cms.page', ['path' => $item->path])) !!},
                    "datePublished": "{{ $item->created_at->toIso8601String() }}"
                }
                @if(!$loop->last),@endif
            @endforeach
            ]
        }</script>
    </div>
@endif
