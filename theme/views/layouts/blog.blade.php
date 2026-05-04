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
        @foreach($content['main'] ?? [] as $item)
            @if($el = cmsref($page, $item))
                <div id="{{ cmsattr(@$item->id) }}" class="{{ cmsattr(@$el->type) }}">
                    <div class="container">
                        @includeFirst(cmsviews($page, $el), cmsdata($page, $el))
                    </div>
                </div>
            @endif
        @endforeach
    </div>
@endsection


@section('footer')
    <footer class="cms-content" data-section="footer">
        @foreach($content['footer'] ?? [] as $item)
            @if($el = cmsref($page, $item))
                <div id="{{ cmsattr(@$item->id) }}" class="{{ cmsattr(@$el->type) }}">
                    <div class="container">
                        @includeFirst(cmsviews($page, $el), cmsdata($page, $el))
                    </div>
                </div>
            @endif
        @endforeach
    </footer>
@endsection
