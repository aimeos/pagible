@pushOnce('head')
<link href="{{ cmstheme($page, 'hero.css') }}" rel="stylesheet">
@endPushOnce

@if($bg = cms($files, $data->background?->id ?? null))
    @pushOnce('head', 'cms-hero-bg-preload')
    <link rel="preload" as="image" fetchpriority="high" href="{{ cmsasset($page, $bg, current(array_reverse((array) cms($bg, 'previews', []))) ?: cms($bg, 'path')) }}" imagesrcset="{{ collect((array) cms($bg, 'previews', []))->map(fn($path, $width) => cmsasset($page, $bg, (int) $width) . ' ' . $width . 'w')->implode(',') }}" imagesizes="100vw">
    @endPushOnce
    <x-cms::image :page="$page" :file="$bg" main :class="array_filter(['background', $data->{'background-animation'} ?? null])" sizes="100vw" />
@endif

<div class="first">
    @if($data->subtitle ?? null)
        <div class="subtitle">
            {{ $data->subtitle }}
        </div>
    @endif

    <h1 class="title">@text($data->title ?? '')</h1>

    @if($data->text ?? null)
        <div class="cms-text">@markdown($data->text)</div>
    @endif

    <x-cms::buttons :buttons="$data->buttons ?? []" />
</div>

@if($heroFileIds = array_values(array_filter(array_map(fn($item) => data_get($item, 'id'), (array) ($data->files ?? [])))))
    <div class="second{{ count($heroFileIds) > 1 ? ' multiple has-second-image' : '' }}{{ count($heroFileIds) > 2 ? ' has-third-image' : '' }}">
        @foreach($heroFileIds as $idx => $id)
            @if($file = cms($files, $id))
                @if(str_starts_with(cms($file, 'mime') ?? '', 'video/'))
                    <x-cms::video :page="$page" :file="$file" autoplay />
                @else
                    <x-cms::image
                        :page="$page"
                        :file="$file"
                        :main="$idx === 0"
                        :sizes="count($heroFileIds) > 1 ? '(min-width: 768px) 25vw, 50vw' : '50vw'"
                    />
                @endif
            @endif
        @endforeach
    </div>
@endif
