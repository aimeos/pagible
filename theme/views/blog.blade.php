@pushOnce('foot')
<link href="{{ cmstheme($page, 'blog.css') }}" rel="preload" as="style">
<script defer src="{{ cmstheme($page, 'blog.js') }}"></script>
@endPushOnce

@if($first = $action?->first())
    @php($layout = $data->layout ?? 'default')
    @if($data->title ?? null)
        <h2>{{ $data->title }}</h2>
    @endif
    @if($layout === 'default')
        <div class="blog-items blog-default" data-blog="{{ $data->{'parent-page'}?->value ?? '' }}">
            <div class="first">
                @include('cms::blog-item', ['item' => $first, 'layout' => 'cards'])
            </div>
            <div class="second">
                @foreach($action?->skip(1) ?? [] as $item)
                    @include('cms::blog-item', ['item' => $item, 'layout' => $layout])
                @endforeach
            </div>
        </div>
    @else
        <div class="blog-items blog-{{ $layout }}" data-blog="{{ $data->{'parent-page'}?->value ?? '' }}">
            @foreach($action ?? [] as $item)
                @include('cms::blog-item', ['item' => $item, 'layout' => $layout])
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
@endif
