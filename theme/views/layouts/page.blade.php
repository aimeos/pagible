@extends($theme . '::layouts.main')

@pushOnce('head')
<link href="{{ cmstheme($page, 'layout-page.css') }}" rel="stylesheet">
@endPushOnce


@section('main')
    <div class="cms-content" data-section="main">
        @include('cms::elements', ['items' => $content['main'] ?? []])
    </div>
@endsection


@section('footer')
    <footer class="cms-content" data-section="footer">
        @include('cms::elements', ['items' => $content['footer'] ?? []])
    </footer>
@endsection
