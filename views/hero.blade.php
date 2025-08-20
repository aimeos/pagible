@pushOnce('css')
<link href="{{ cmsasset('vendor/cms/theme/hero.css') }}" rel="stylesheet">
@endPushOnce

<h1 class="title">{{ @$data->title }}</h1>

@if(@$data->text)
    <div class="subtitle">
        @markdown($data->text)
    </div>
@endif

@if(@$data->url)
    <a class="btn url" href="{{ $data->url }}">{{ @$data->button }}</a>
@endif
