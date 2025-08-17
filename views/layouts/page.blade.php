@extends('cms::layouts.main')

@pushOnce('css')
<link href="{{ cmsasset('vendor/cms/theme/layout-page.css') }}" rel="stylesheet">
@endPushOnce


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
