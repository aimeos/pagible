@pushOnce('css')
<link href="{{ cmstheme($page, 'hero.css') }}" rel="stylesheet">
@endPushOnce

<div class="first">
    @if(@$data->subtitle)
        <div class="subtitle">
            {{ $data->subtitle }}
        </div>
    @endif

    <h1 class="title">{{ @$data->title }}</h1>

    @if(@$data->text)
        @markdown($data->text)
    @endif

    @if(@$data->url || @$data->url2)
        <div class="actions">
            @if(@$data->url)
                <a class="btn url" href="{{ $data->url }}">{{ @$data->button }}</a>
            @endif
            @if(@$data->url2)
                <a class="btn url2" href="{{ $data->url2 }}">{{ @$data->button2 }}</a>
            @endif
        </div>
    @endif
</div>

@if($file = cms($files, @$data->file?->id))
    <div class="second">
        @include('cms::pic', ['file' => $file, 'main' => true, 'sizes' => '50vw'])
    </div>
@endif
