@extends($theme . '::layouts.main')

@pushOnce('head')
<link href="{{ cmstheme($page, 'layout-blog.css') }}" rel="stylesheet">
@endPushOnce

@once('prism')
    @pushOnce('head')
    <link href="{{ cmstheme($page, 'prism.css') }}" rel="stylesheet">
    @endPushOnce

    @pushOnce('foot')
    <script defer src="{{ cmstheme($page, 'prism.js') }}"></script>
    @endPushOnce
@endOnce


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
