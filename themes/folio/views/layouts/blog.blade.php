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
    <div class="post">
        <article class="cms-content" data-section="main">
            @foreach($content['main'] ?? [] as $item)
                @if($el = cmsref($page, $item))
                    <div id="{{ cmsattr($item->id ?? '') }}" class="{{ cmsattr($el->type ?? '') }}">
                        <div class="container">
                            @includeFirst(cmsviews($page, $el), cmsdata($page, $el))
                        </div>
                    </div>
                @endif
            @endforeach
        </article>

        @if(($outline = collect($content['main'] ?? [])->map(fn($item) => ['id' => $item->id ?? '', 'el' => cmsref($page, $item)])->filter(fn($entry) => ($entry['el']->type ?? '') === 'heading' && in_array((int) ($entry['el']->data->level ?? 0), [2, 3])))->count() > 1)
            <aside class="outline" aria-label="{{ __('On this page') }}">
                <p>{{ __('On this page') }}</p>
                <ul>
                    @foreach($outline as $entry)
                        <li class="level-{{ (int) $entry['el']->data->level }}">
                            <a href="#{{ cmsattr($entry['id']) }}">{{ $entry['el']->data->title ?? '' }}</a>
                        </li>
                    @endforeach
                </ul>
            </aside>
        @endif

        <div class="post-foot">
            @if(($authorItem = $page->ancestorsAndSelf->reverse()->first(fn($item) => cms($item, 'config.folio::profile.data'))) && ($author = cms($authorItem, 'config.folio::profile.data')))
                <aside class="author" aria-label="{{ __('About the author') }}">
                    @if(($avatarId = $author->avatar->id ?? null) && ($avatar = cmsfile($authorItem, $avatarId)))
                        <img src="{{ cmsasset($authorItem, $avatar) }}" alt="" width="64" height="64" loading="lazy">
                    @endif
                    <div>
                        <p class="name">{{ __('Written by :name', ['name' => $author->name ?? '']) }}</p>
                        @if($author->bio ?? null)
                            <p>{{ $author->bio }}</p>
                        @endif
                    </div>
                </aside>
            @endif

            @if(($siblings = $nav->items(max(0, $nav->ancestors()->count() - 1))->values()) && ($pos = $siblings->search(fn($item) => $item->id === $page->id)) !== false)
                <nav class="pager" aria-label="{{ __('More posts') }}">
                    @if($pos > 0 && ($prev = $siblings->get($pos - 1)))
                        <a href="{{ cmsroute($prev) }}" class="prev" rel="prev">
                            <small>{{ __('Previous') }}</small>
                            <span>{{ cms($prev, 'title') }}</span>
                        </a>
                    @endif
                    @if($next = $siblings->get($pos + 1))
                        <a href="{{ cmsroute($next) }}" class="next" rel="next">
                            <small>{{ __('Next') }}</small>
                            <span>{{ cms($next, 'title') }}</span>
                        </a>
                    @endif
                </nav>
            @endif
        </div>
    </div>
@endsection


@section('footer')
    <footer class="cms-content" data-section="footer">
        @foreach($content['footer'] ?? [] as $item)
            @if($el = cmsref($page, $item))
                <div id="{{ cmsattr($item->id ?? '') }}" class="{{ cmsattr($el->type ?? '') }}">
                    <div class="container">
                        @includeFirst(cmsviews($page, $el), cmsdata($page, $el))
                    </div>
                </div>
            @endif
        @endforeach
    </footer>
@endsection
