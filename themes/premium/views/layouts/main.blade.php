<!DOCTYPE html>
<html class="no-js" data-theme="light" lang="{{ cms($page, 'lang') }}" dir="{{ \Aimeos\Cms\Utils::textDir(cms($page, 'lang')) }}">
    <head>
        <x-cms::head :page="$page" :nav="$nav" />

        <link href="{{ cmstheme($page, 'pico.min.css') }}" rel="stylesheet">
        <link href="{{ cmstheme($page, 'pico.nav.min.css') }}" rel="stylesheet">
        <link href="{{ cmstheme($page, 'pico.dropdown.min.css') }}" rel="stylesheet">
        <link href="{{ cmstheme($page, 'cms.css') }}" rel="stylesheet">
        @stack('head')

        <script type="application/ld+json">
            [{
                "@@context": "https://schema.org",
                "@@type": "WebSite",
                "name": {!! cmsjson(cmsconfig($page, 'website.data.title', cms($page->ancestorsAndSelf->first() ?? $page, 'name'))) !!},
                "url": {!! cmsjson(url('/')) !!}
            },
            {
                "@@context": "https://schema.org",
                "@@type": "WebPage",
                "name": {!! cmsjson(cms($page, 'title')) !!},
                "inLanguage": {!! cmsjson(cms($page, 'lang')) !!},
                "url": {!! cmsjson(cmsroute($page)) !!}
            }
            @if($nav->ancestors()->count() > 1)
            ,{
                "@@context": "https://schema.org",
                "@@type": "BreadcrumbList",
                "itemListElement": [
                    @foreach($nav->ancestors()->skip(1)->values() as $item)
                    {
                        "@@type": "ListItem",
                        "position": {{ $loop->iteration }},
                        "name": {!! cmsjson(cms($item, 'name')) !!},
                        "item": {!! cmsjson(cmsroute($item)) !!}
                    },
                    @endforeach
                    {
                        "@@type": "ListItem",
                        "position": {{ $nav->ancestors()->skip(1)->count() + 1 }},
                        "name": {!! cmsjson(cms($page, 'name')) !!}
                    }
                ]
            }
            @endif
            ]
        </script>
    </head>
    <body class="theme-premium type-{{ cms($page, 'type') ?: 'page' }}">
        <a href="#main" class="skip-link">{{ __('Skip to main content') }}</a>
        <x-cms::search :page="$page" />
        <header>
            <nav role="navigation" aria-label="{{ __('Main navigation') }}">
                <ul>
                    <li class="sidebar-open show">
                        <button aria-label="{{ __('Open sidebar') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708"/>
                            </svg>
                        </button>
                    </li>
                    <li class="sidebar-close">
                        <button aria-label="{{ __('Close sidebar') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0"/>
                            </svg>
                        </button>
                    </li>
                    <li class="brand">
                        <x-cms::brand :page="$page" :nav="$nav" />
                    </li>
                    <li class="menu-close">
                        <button aria-label="{{ __('Close navigation') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/>
                            </svg>
                        </button>
                    </li>
                </ul>
                <ul class="menu">
                    <li>
                        <a href="#" class="search" data-modal="modal-search" title="{{ __('Search') }}" aria-label="{{ __('Search') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0"/>
                            </svg>
                        </a>
                    </li>
                    <x-cms::menu :page="$page" :nav="$nav" />
                    <x-cms::languages :page="$page" :nav="$nav" />
                    @if(Route::has('login'))
                        <li class="login">
                            <a href="{{ route('login') }}" title="{{ __('Login') }}" aria-label="{{ __('Login') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12,4A4,4 0 0,1 16,8A4,4 0 0,1 12,12A4,4 0 0,1 8,8A4,4 0 0,1 12,4M12,14C16.42,14 20,15.79 20,18V20H4V18C4,15.79 7.58,14 12,14Z" />
                                </svg>
                            </a>
                        </li>
                    @endif
                </ul>
                <ul class="menu-open show">
                    <li>
                        <button aria-label="{{ __('Open navigation') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5"/>
                            </svg>
                        </button>
                    </li>
                </ul>
            </nav>
        </header>

        <x-cms::breadcrumb :page="$page" :nav="$nav" />

        <main id="main">
            @yield('main')
        </main>

        @yield('footer')

        <footer class="bottom">
            <div class="container">
                <span class="copyright">
                    &copy; {{ date('Y') }} {{ cmsconfig($page, 'website.data.title', cms($page->ancestorsAndSelf->first() ?? $page, 'name')) }}
                </span>
            </div>
        </footer>


        @include('cms::layouts.foot')
    </body>
</html>
