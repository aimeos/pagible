@extends($theme . '::layouts.main')

@pushOnce('head')
<link href="{{ cmstheme($page, 'layout-docs.css') }}" rel="stylesheet">
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
    @if($section = $nav->ancestors()->concat([$page])->skip(1)->first())
        <nav class="sidebar" aria-label="{{ __('Documentation') }}">
            <a href="{{ cmsroute($section) }}" @class(['section', 'active' => $page->id === $section->id])>{{ cms($section, 'name') }}</a>
            <ul class="menu">
                @foreach($nav->items(1) as $item)
                    <li class="{{ $item->children->count() ? 'group' : '' }}">
                        <a href="{{ cmsroute($item) }}" class="{{ $page->id === $item->id ? 'active' : '' }}" @if($page->id === $item->id) aria-current="page" @endif>{{ cms($item, 'name') }}</a>
                        @if($item->children->count())
                            <ul>
                                @foreach($item->children as $subItem)
                                    <li>
                                        <a href="{{ cmsroute($subItem) }}" class="{{ $page->isSelfOrDescendantOf($subItem) ? 'active' : '' }}" @if($page->id === $subItem->id) aria-current="page" @endif>{{ cms($subItem, 'name') }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif

    <article class="content">
        <header class="intro">
            @if($nav->ancestors()->count() > 1)
                <p class="eyebrow">{{ cms($nav->ancestors()->last(), 'name') }}</p>
            @endif
            <h1>{{ cms($page, 'title') }}</h1>
        </header>

        <div class="cms-content" data-section="main">
            @include('cms::elements', ['items' => $content['main'] ?? []])
        </div>

        @if($section && ($pages = collect([$section])->concat($nav->items(1)->flatMap(fn($item) => collect([$item])->concat($item->children)))->values()) && ($pos = $pages->search(fn($item) => $item->id === $page->id)) !== false)
            <nav class="pager" aria-label="{{ __('Pagination') }}">
                @if($pos > 0 && ($prev = $pages->get($pos - 1)))
                    <a href="{{ cmsroute($prev) }}" class="prev" rel="prev">
                        <small>{{ __('Previous') }}</small>
                        <span>{{ cms($prev, 'name') }}</span>
                    </a>
                @endif
                @if($next = $pages->get($pos + 1))
                    <a href="{{ cmsroute($next) }}" class="next" rel="next">
                        <small>{{ __('Next') }}</small>
                        <span>{{ cms($next, 'name') }}</span>
                    </a>
                @endif
            </nav>
        @endif

        @if($page->updated_at)
            <p class="updated">{{ __('Last updated') }}: <time datetime="{{ $page->updated_at->toIso8601String() }}">@localDate($page->updated_at, 'medium')</time></p>
        @endif
    </article>

    @if(count($outline = \Aimeos\Cms\Theme::headings($page, $content['main'] ?? [], [2, 3])))
        <aside class="outline" aria-label="{{ __('On this page') }}">
            <p>{{ __('On this page') }}</p>
            <ul>
                @foreach($outline as $entry)
                    <li class="level-{{ $entry['level'] }}">
                        <a href="#{{ cmsattr($entry['id']) }}">{{ $entry['title'] }}</a>
                    </li>
                @endforeach
            </ul>
        </aside>
    @endif
@endsection


@section('footer')
    <footer class="cms-content" data-section="footer">
        @include('cms::elements', ['items' => $content['footer'] ?? []])
    </footer>
@endsection
