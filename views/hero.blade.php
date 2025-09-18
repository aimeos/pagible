@pushOnce('css')
<link href="{{ cmsasset('vendor/cms/theme/hero.css') }}" rel="stylesheet">
@endPushOnce

<div class="content">
    @if(@$data->subtitle)
        <div class="subtitle">
            {{ $data->subtitle }}
        </div>
    @endif

    <h1 class="title">{{ @$data->title }}</h1>

    @if(@$data->text)
        <p class="text">
            @markdown($data->text)
        </p>
    @endif

    @if(@$data->url)
        <a class="btn url" href="{{ $data->url }}">{{ @$data->button }}</a>
    @endif
</div>

@if($file = cms($files, @$data->file?->id))
    <div class="image">
        @include('cms::pic', ['file' => $file, 'main' => true, 'sizes' => '50vw'])
    </div>
@endif
