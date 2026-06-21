{{-- Article preview: expects $item (blog page) and $stacked (true = day/month date, false = inline date) --}}
<a href="{{ route('cms.page', ['path' => $item->path]) }}" class="blog-item">
    @if($article = collect(cms($item, 'content'))->first(fn($el) => ($el->type ?? null) === 'article')?->data)
        @if($file = cms(cms($item, 'files'), $article->file?->id ?? null))
            @include('cms::pic', ['file' => $file])
        @endif
        <div class="content">
            <div class="date">
                @if($stacked ?? false)
                    <span class="date-day">@localDate($item->created_at, 'D')</span>
                    <span class="date-month">@localDate($item->created_at, 'MMM')</span>
                @else
                    @localDate($item->created_at, 'D. MMM. YYYY')
                @endif
            </div>
            <h3>{{ cms($item, 'title') }}</h3>
        </div>
    @else
        <h3>{{ cms($item, 'title') }}</h3>
    @endif
</a>
