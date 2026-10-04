@pushOnce('head')
<link href="{{ cmstheme($page, 'hero.css') }}" rel="stylesheet">
@endPushOnce

@if($bg = cms($files, $data->background?->id ?? null))
    @pushOnce('head', 'cms-hero-bg-preload')
    <link rel="preload" as="image" fetchpriority="high" href="{{ cmsasset($page, $bg, current(array_reverse((array) cms($bg, 'previews', []))) ?: cms($bg, 'path')) }}" imagesrcset="{{ cmssrcset($page, $bg) }}" imagesizes="100vw">
    @endPushOnce
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

    @if($data->buttons ?? null)
        <div class="actions">
            @foreach($data->buttons as $button)
                @if($url = cmslink($button->url ?? null))
                    <a class="btn" href="{{ $url }}" rel="{{ $button->{'url-rel'} ?? '' }}">{{ $button->label ?? '' }}</a>
                @endif
            @endforeach
        </div>
    @endif
</div>

@if($heroFileIds = array_values(array_filter(array_map(fn($item) => data_get($item, 'id'), (array) ($data->files ?? [])))))
    <div class="second{{ count($heroFileIds) > 1 ? ' multiple has-second-image' : '' }}{{ count($heroFileIds) > 2 ? ' has-third-image' : '' }}">
        @foreach($heroFileIds as $idx => $id)
            @if($file = cms($files, $id))
                @if(str_starts_with(cms($file, 'mime') ?? '', 'video/'))
                    <video autoplay muted loop playsinline preload="metadata"
                        title="{{ cms($file, 'description')?->{cms($page, 'lang')} ?? '' }}"
                        src="{{ cmsasset($page, $file) }}"
                        @if($preview = current(array_reverse((array) cms($file, 'previews', []))))
                            poster="{{ cmsasset($page, $file, $preview) }}"
                        @endif
                    >
                    </video>
                @else
                    @include('cms::pic', [
                        'file' => $file,
                        'main' => $idx === 0,
                        'sizes' => count($heroFileIds) > 1 ? '(min-width: 768px) 25vw, 50vw' : '50vw',
                    ])
                @endif
            @endif
        @endforeach
    </div>
@endif
