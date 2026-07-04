@pushOnce('head')
<link href="{{ cmstheme($page, 'hero.css') }}" rel="stylesheet">
@endPushOnce

@if($bg = cms($files, $data->background?->id ?? null))
    @include('cms::pic', ['file' => $bg, 'main' => true, 'class' => array_filter(['background', $data->{'background-animation'} ?? null]), 'sizes' => '100vw'])
@endif

<div class="first">
    @if($data->subtitle ?? null)
        <div class="subtitle">
            {{ $data->subtitle }}
        </div>
    @endif

    <h1 class="title">{{ $data->title ?? '' }}</h1>

    @if($data->text ?? null)
        <div class="cms-text">@markdown($data->text)</div>
    @endif

    @if(($data->url ?? null) || ($data->{'url-alternative'} ?? null))
        <div class="actions">
            @if($data->url ?? null)
                <a class="btn url" href="{{ cmslink($data->url) }}">{{ $data->button ?? '' }}</a>
            @endif
            @if($data->{'url-alternative'} ?? null)
                <a class="btn url-alternative" href="{{ cmslink($data->{'url-alternative'}) }}">{{ $data->{'button-alternative'} ?? '' }}</a>
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
