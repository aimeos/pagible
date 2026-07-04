{{-- Article preview: expects $item (page), $layout (layout type), $date (include date) --}}
<a href="{{ route('cms.page', ['path' => $item->path]) }}" class="list-item">
    @if($article = collect(cms($item, 'content'))->first()?->data)
        @if($file = cms(cms($item, 'files'), $article->file?->id ?? null))
            @include('cms::pic', ['file' => $file])
        @endif
        <div class="content">
            @if($date ?? true)
                <div class="date">
                    @if(($layout ?? '') === 'cards')
                        <span class="date-day">@localDate($item->created_at, 'D')</span>
                        <span class="date-month">@localDate($item->created_at, 'MMM')</span>
                    @else
                        @localDate($item->created_at, 'D. MMM. YYYY')
                    @endif
                </div>
            @endif
            <h3>{{ cms($item, 'title') }}</h3>
            @if(($layout ?? '') === 'list' && ($text = $article->text ?? null))
                <p class="intro">{{ str($text)->limit(500) }}</p>
            @endif
        </div>
    @else
        <h3>{{ cms($item, 'title') }}</h3>
    @endif
</a>
