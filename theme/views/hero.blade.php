@pushOnce('head')
<link href="{{ cmstheme($page, 'hero.css') }}" rel="stylesheet">
@endPushOnce

<div class="first">
    @if($data->subtitle ?? null)
        <div class="subtitle">
            {{ $data->subtitle }}
        </div>
    @endif

    <h1 class="title">{{ $data->title ?? '' }}</h1>

    @if($data->text ?? null)
        @markdown($data->text)
    @endif

    @if(($data->url ?? null) || ($data->{'url-alternative'} ?? null))
        <div class="actions">
            @if($data->url ?? null)
                <a class="btn url" href="{{ $data->url }}">{{ $data->button ?? '' }}</a>
            @endif
            @if($data->{'url-alternative'} ?? null)
                <a class="btn url-alternative" href="{{ $data->{'url-alternative'} }}">{{ $data->{'button-alternative'} ?? '' }}</a>
            @endif
        </div>
    @endif
</div>

@if($file = cms($files, $data->file?->id ?? null))
    <div class="second">
        @if(str_starts_with(cms($file, 'mime') ?? '', 'video/'))
            <video autoplay muted loop playsinline preload="metadata"
                title="{{ cms($file, 'description')?->{cms($page, 'lang')} ?? '' }}"
                src="{{ cmsurl(cms($file, 'path')) }}"
                @if($preview = current(array_reverse((array) cms($file, 'previews', []))))
                    poster="{{ cmsurl($preview) }}"
                @endif
            >
            </video>
        @else
            @include('cms::pic', ['file' => $file, 'main' => true, 'sizes' => '50vw'])
        @endif
    </div>
@endif
