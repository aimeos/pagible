@pushOnce('head')
<link href="{{ cmstheme($page, 'hero.css') }}" rel="stylesheet">
@endPushOnce

@if(count($heroFiles = (array) ($data->files ?? [])) > 1)
    @pushOnce('foot')
    <link href="{{ cmstheme($page, 'slideshow.css') }}" rel="preload" as="style">
    @endPushOnce

    @pushOnce('foot')
    <script defer src="{{ cmstheme($page, 'slideshow.js') }}"></script>
    @endPushOnce
@endif

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

@if($heroFiles)
    @if(count($heroFiles) > 1)
        <div class="second multiple swiffy-slider slider-item-nogap slider-item-ratio slider-nav-animation slider-nav-autoplay slider-nav-autopause slider-nav-round slider-nav-dark"
            data-slider-nav-autoplay-interval="4000">
            <div class="slider-container">
                @foreach($heroFiles as $idx => $item)
                    @if($file = cms($files, data_get($item, 'id')))
                        <div class="hero-slide">
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
                                @include('cms::pic', [
                                    'file' => $file,
                                    'main' => $idx === 0,
                                    'sizes' => '(min-width: 768px) 50vw, 100vw',
                                ])
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>

            <button type="button" class="slider-nav slider-nav-prev" aria-label="Go to previous"></button>
            <button type="button" class="slider-nav slider-nav-next" aria-label="Go to next"></button>
        </div>
    @else
        <div class="second">
            @foreach($heroFiles as $idx => $item)
                @if($file = cms($files, data_get($item, 'id')))
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
                        @include('cms::pic', [
                            'file' => $file,
                            'main' => $idx === 0,
                            'sizes' => '50vw',
                        ])
                    @endif
                @endif
            @endforeach
        </div>
    @endif
@endif
