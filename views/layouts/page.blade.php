@extends('cms::layouts.main')

@pushOnce('css')
<link href="{{ cmsasset('vendor/cms/pico.nav.min.css') }}" rel="stylesheet">
@endPushOnce

@pushOnce('css')
<link href="{{ cmsasset('vendor/cms/pico.dropdown.min.css') }}" rel="stylesheet">
@endPushOnce

@section('header')
    <header>
        <nav>
            <ul class="brand">
                <li class="menu-close">
                    <button aria-label="Toggle menu">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-x-lg" viewBox="0 0 16 16">
                            <path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/>
                        </svg>
                    </button>
                </li>
                <li>
                    <a href="{{ cmsroute($page->ancestors?->first() ?? $page) }}" class="contrast">
                        <strong>{{ config('app.name') }}</strong>
                    </a>
                </li>
            </ul>
            <ul class="menu">
                @foreach($page->nav() as $item)
                    @if(cms($item, 'status') <= 1)
                        <li>
                            @if($item->children->count())
                                <details class="dropdown is-menu">
                                    <summary role>{{ cms($item, 'name') }}</summary>
                                    <ul class="right">
                                        @foreach($item->children as $subItem)
                                            @if(cms($subItem, 'status') <= 1)
                                                <li>
                                                    <a href="{{ cmsroute($subItem) }}" class="{{ !$page->isSelfOrDescendantOf($subItem) ?: 'active' }} contrast">
                                                        {{ cms($subItem, 'name') }}
                                                    </a>
                                                </li>
                                            @endif
                                        @endforeach
                                    </ul>
                                </details>
                            @else
                                <a href="{{ cmsroute($item) }}" class="{{ !$page->isSelfOrDescendantOf($item) ?: 'active' }} contrast">
                                    {{ cms($item, 'name') }}
                                </a>
                            @endif
                        </li>
                    @endif
                @endforeach
            </ul>
            <ul class="menu-open show">
                <li>
                    <button aria-label="Toggle menu">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-list" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5"/>
                        </svg>
                    </button>
                </li>
            </ul>
        </nav>
    </header>

    @if($page->ancestors->count() > 1)
        <nav aria-label="breadcrumb">
            <ul>
                @foreach($page->ancestors ?? [] as $item)
                    @if(cms($item, 'status') === 1)
                        <li>
                            <a href="{{ cmsroute($item) }}">{{ cms($item, 'name') }}</a>
                        </li>
                    @endif
                @endforeach
                <li>{{ cms($page, 'name') }}</li>
            </ul>
        </nav>
    @endif
@endsection


@section('main')
    <div class="cms-content" data-section="main">
        @foreach($content['main'] ?? [] as $item)
            @if($el = cmsref($page, $item))
                <div id="{{ cmsid(@$item->id) }}" class="{{ cmsid(@$el->type) }}">
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
                <div id="{{ cmsid(@$item->id) }}" class="{{ cmsid(@$el->type) }}">
                    <div class="container">
                        @includeFirst(cmsviews($page, $el), cmsdata($page, $el))
                    </div>
                </div>
            @endif
        @endforeach
    </footer>
@endsection
