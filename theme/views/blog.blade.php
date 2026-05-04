@pushOnce('foot')
<link rel="preload" href="{{ cmstheme($page, 'blog.css') }}" as="style">
<script defer src="{{ cmstheme($page, 'blog.js') }}"></script>
@endPushOnce

@if($first = @$action?->first())
    @if(@$data->title)
        <h2>{{ $data->title }}</h2>
    @endif
    <div class="blog-items" data-blog="{{ @$data->{'parent-page'}?->value }}">
        <div class="first">
            <a href="{{ route('cms.page', ['path' => @$first->path]) }}" class="blog-item">
                @if($article = collect(cms($first, 'content'))->first(fn($el) => @$el->type === 'article')?->data)
                    @if($file = cms(cms($first, 'files'), @$article->file?->id))
                        @include('cms::pic', ['file' => $file])
                    @endif
                    <div class="content">
                        <div class="date">
                            <span class="date-day">@localDate($first->created_at, 'D')</span>
                            <span class="date-month">@localDate($first->created_at, 'MMM')</span>
                        </div>
                        <h3>{{ cms($first, 'title') }}</h3>
                    </div>
                @else
                    <h3>{{ cms($first, 'title') }}</h3>
                @endif
            </a>
        </div>
        <div class="second">
            @foreach(@$action?->skip(1) ?? [] as $item)
                <a href="{{ route('cms.page', ['path' => @$item->path]) }}" class="blog-item">
                    @if($article = collect(cms($item, 'content'))->first(fn($el) => @$el->type === 'article')?->data)
                        @if($file = cms(cms($item, 'files'), @$article->file?->id))
                            @include('cms::pic', ['file' => $file])
                        @endif
                        <div class="content">
                            <div class="date">
                                @localDate($item->created_at, 'D. MMM. YYYY')
                            </div>
                            <h3>{{ cms($item, 'title') }}</h3>
                        </div>
                    @else
                        <h3>{{ cms($item, 'title') }}</h3>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
    {{ @$action?->appends(request()->query())?->links() }}

    <script type="application/ld+json">{
        "@@context": "https://schema.org",
        "@@type": "Blog",
        "name": {{ Js::from(@$data->title ?? cms($page, 'title')) }},
        "blogPost": [
        @foreach(@$action ?? [] as $item)
            {
                "@@type": "BlogPosting",
                "headline": {{ Js::from(cms($item, 'title')) }},
                "url": {{ Js::from(route('cms.page', ['path' => @$item->path])) }},
                "datePublished": "{{ $item->created_at->toIso8601String() }}"
            }
            @if(!$loop->last),@endif
        @endforeach
        ]
    }</script>
@endif
