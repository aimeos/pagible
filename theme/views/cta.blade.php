@pushOnce('foot')
<link href="{{ cmstheme($page, 'cta.css') }}" rel="preload" as="style">
@endPushOnce

<h2 class="title">{{ $data->title ?? '' }}</h2>

@if($data->text ?? null)
    <div class="cms-text">@markdown($data->text)</div>
@endif

@if($data->buttons ?? null)
    <div class="actions">
        @foreach($data->buttons as $button)
            @if($url = cmslink($button->url ?? null))
                <a class="btn" href="{{ $url }}">{{ $button->label ?? '' }}</a>
            @endif
        @endforeach
    </div>
@endif
