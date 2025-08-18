@extends('cms::layouts.main')

@pushOnce('css')
<link href="{{ cmsasset('vendor/cms/theme/layout-docs.css') }}" rel="stylesheet">
@endPushOnce


@section('main')
    <main>
        <nav class="sidebar">
            <ul class="menu">
                @foreach($page->nav(2) as $item)
                    @if(cms($item, 'status') == 1)
                        <li>
                            @if($item->children->count() && $page->isSelfOrDescendantOf($item))
                                <details class="is-menu" open>
                                    <summary role>
                                        <a href="{{ cmsroute($item) }}" class="{{ $page->isSelfOrDescendantOf($item) ? 'active' : '' }}">
                                            {{ cms($item, 'name') }}
                                        </a>
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
                                <a href="{{ cmsroute($item) }}" class="{{ $page->isSelfOrDescendantOf($item) ? 'active' : '' }}">
                                    {{ cms($item, 'name') }}
                                </a>
                            @endif
                        </li>
                    @else
                        @break
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
        </div>
    </main>
@endsection
