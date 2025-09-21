@extends('cms::layouts.main')

@pushOnce('css')
<link href="{{ cmsasset('vendor/cms/theme/layout-docs.css') }}" rel="stylesheet">
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
    <main>
        <nav class="sidebar">
            <ul class="menu">
                @foreach($page->nav(1) as $item)
                    @if(cms($item, 'status') == 1)
                        <li>
                            @if($item->children->count() && $page->isSelfOrDescendantOf($item))
                                <details class="is-menu" open>
                                    <summary class="menu-item" role>
                                        <a href="{{ cmsroute($item) }}" class="{{ $page->isSelfOrDescendantOf($item) ? 'active' : '' }}">
                                            {{ cms($item, 'name') }}
                                        </a>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-chevron-right" viewBox="0 0 16 16">
                                            <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708"/>
                                        </svg>
                                    </summary>
                                    <ul class="menu">
                                        @foreach($item->children as $subItem)
                                            @if(cms($subItem, 'status') == 1)
                                                <li>
                                                    <a href="{{ cmsroute($subItem) }}" class="{{ $page->isSelfOrDescendantOf($subItem) ? 'active' : '' }}">
                                                        {{ cms($subItem, 'name') }}
                                                    </a>
                                                </li>
                                            @else
                                                @break
                                            @endif
                                        @endforeach
                                    </ul>
                                </details>
                            @else
                                <div class="menu-item">
                                    <a href="{{ cmsroute($item) }}" class="{{ $page->isSelfOrDescendantOf($item) ? 'active' : '' }}">
                                        {{ cms($item, 'name') }}
                                    </a>
                                    @if($item->has)
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-chevron-right" viewBox="0 0 16 16">
                                            <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708"/>
                                        </svg>
                                    @endif
                                </div>
                            @endif
                        </li>
                    @endif
                @endforeach
            </ul>
        </nav>

        <div class="content">
            <h1>{{ cms($page, 'title') }}</h1>

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
        </div>
    </main>
@endsection
