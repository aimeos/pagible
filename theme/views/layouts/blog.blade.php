@extends('cms::layouts.main')

@pushOnce('css')
<link href="{{ cmsasset('vendor/cms/theme/layout-blog.css') }}" rel="stylesheet">
@endPushOnce

@once('prism')
    @pushOnce('css')
    <link href="{{ cmsasset('vendor/cms/theme/prism.css') }}" rel="stylesheet">
    @endPushOnce

    @pushOnce('js')
    <script defer src="{{ cmsasset('vendor/cms/theme/prism.js') }}"></script>
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
    <footer class="copyright">
        &copy; {{ date('Y') }} {{ config('app.name') }}
    </footer>
@endsection
