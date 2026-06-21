{{-- Article preview: expects $item (blog page), $layout (layout type) --}}
<a href="{{ route('cms.page', ['path' => $item->path]) }}" class="blog-item">
    @if($article = collect(cms($item, 'content'))->first(fn($el) => ($el->type ?? null) === 'article')?->data)
        @if($file = cms(cms($item, 'files'), $article->file?->id ?? null))
            @include('cms::pic', ['file' => $file])
        @endif
        <div class="content">
            <div class="date">
                @if(($layout ?? '') === 'cards')
                    <span class="date-day">@localDate($item->created_at, 'D')</span>
                    <span class="date-month">@localDate($item->created_at, 'MMM')</span>
                @else
                    @localDate($item->created_at, 'D. MMM. YYYY')
                @endif
            </div>
            <h3>{{ cms($item, 'title') }}</h3>
            @if(($layout ?? '') === 'list' && ($text = $article->text ?? null))
                <p class="intro">{{ str($text)->limit(500) }}</p>
            @endif
        </div>
    @else
        <h3>{{ cms($item, 'title') }}</h3>
    @endif
</a>
