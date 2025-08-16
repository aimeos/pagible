@extends('cms::layouts.main')


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
