<!DOCTYPE html>
<html class="no-js" lang="{{ cms($page, 'lang') }}" dir="{{ in_array(strtok((string) cms($page, 'lang'), '-_'), ['ar', 'dv', 'fa', 'he', 'ku', 'ur']) ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @auth
            <meta name="csrf-token" content="{{ csrf_token() }}">
        @endauth
        @if(!config('app.debug'))
            <meta http-equiv="Content-Security-Policy" content="
                base-uri 'self';
                default-src 'self';
                frame-src 'self' {{ config('cms.theme.csp.frame-src') }};
                connect-src 'self' {{ config('cms.theme.csp.connect-src') }};
                img-src 'self' data: blob: {{ config('cms.theme.csp.media-src') }};
                media-src 'self' data: blob: {{ config('cms.theme.csp.media-src') }};
                style-src 'self' {{ config('cms.theme.csp.style-src') }} {!! cmshashes($page, 'config.styles.data.text') !!};
                script-src 'self' {{ config('cms.theme.csp.script-src') }} {!! cmshashes($page, 'config.javascript.data.text') !!};
                font-src 'self';
            ">
        @endif

        <meta name="color-scheme" content="light dark">
        <meta name="theme-color" media="(prefers-color-scheme: light)" content="{{ cms($page, 'config.theme.data.--pico-background-color') ?: '#FFFFFF' }}">
        <meta name="theme-color" media="(prefers-color-scheme: dark)" content="#0B1120">

        <title>{{ cms($page, 'title') }}</title>

        @unless(collect(cms($page, 'meta', []))->contains('type', 'canonical'))
            @include('cms::canonical', ['data' => (object) ['url' => cmsroute($page)]])
        @endunless

        @foreach(cms($page, 'meta', []) as $item)
            @includeFirst(cmsviews($page, $item), cmsdata($page, $item))
        @endforeach

        @foreach($page->ancestorsAndSelf->reverse() as $navItem)
            @if($fileId = cms($navItem, 'config.icon.data.file.id'))
                <link rel="icon" type="{{ cmsfile($navItem, $fileId)?->mime }}" href="{{ cmsasset($navItem, cmsfile($navItem, $fileId)) }}">
                @break
            @endif
        @endforeach

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
            @if($project = $page->ancestorsAndSelf->reverse()->map(fn($item) => cms($item, 'config.manual::project.data'))->first(fn($data) => $data))
            ,{
                "@@context": "https://schema.org",
                "@@type": "SoftwareSourceCode",
                "name": {!! cmsjson($project->name ?? '') !!},
                @if($project->description ?? null)
                "description": {!! cmsjson($project->description) !!},
                @endif
                @if($project->version ?? null)
                "version": {!! cmsjson($project->version) !!},
                @endif
                @if($project->language ?? null)
                "programmingLanguage": {!! cmsjson($project->language) !!},
                @endif
                @if($project->license ?? null)
                "license": {!! cmsjson($project->license) !!},
                @endif
                @if($repo = cmslink($project->repository ?? null))
                "codeRepository": {!! cmsjson($repo) !!},
                @endif
                "url": {!! cmsjson(url('/')) !!}
            }
            @endif
            @if(cms($page, 'type') === 'docs')
            ,{
                "@@context": "https://schema.org",
                "@@type": "TechArticle",
                "headline": {!! cmsjson(cms($page, 'title')) !!},
                "inLanguage": {!! cmsjson(cms($page, 'lang')) !!},
                @if($page->updated_at)
                "dateModified": {!! cmsjson($page->updated_at->toIso8601String()) !!},
                @endif
                "url": {!! cmsjson(cmsroute($page)) !!}
            }
            @endif
            ]
        </script>
    </head>
    <body class="theme-manual type-{{ cms($page, 'type') ?: 'page' }}">
        <a href="#main" class="skip-link">{{ __('Skip to main content') }}</a>
        <dialog id="modal-search" class="search">
            <article>
                <header>
                    <form action="{{ cmsroute('cms.search', ['q' => '_term_', 'locale' => cms($page, 'lang')]) }}" toolname="search" tooldescription="{{ __('Search the website and return matching pages with their titles and links') }}" toolautosubmit>
                        <input id="modal-search-input" placeholder="{{ __('Search website') }}" aria-label="{{ __('Search website') }}" name="q" minlength="{{ config('cms.theme.min-search') }}" required toolparamdescription="{{ __('Words or phrase to search for in the website content') }}">
                        <button type="reset" aria-label="{{ __('Close') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/>
                            </svg>
                        </button>
                    </form>
                </header>
                <div class="results" data-load-more="{{ __('Load more') }}" data-no-results="{{ __('No results found') }}" data-failed="{{ __('Search failed') }}">
                </div>
            </article>
        </dialog>

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
                        <a href="{{ cmsroute($nav->ancestors()->first() ?? $page) }}" title="{{ cmsconfig($page, 'website.data.title', cms($page->ancestorsAndSelf->first() ?? $page, 'name')) }}" aria-label="{{ cmsconfig($page, 'website.data.title', cms($page->ancestorsAndSelf->first() ?? $page, 'name')) }}">
                            @if(($navItem = $page->ancestorsAndSelf->reverse()->first(fn($item) => cms($item, 'config.logo.data.file.id'))) && ($fileId = cms($navItem, 'config.logo.data.file.id')))
                                <img src="{{ cmsasset($navItem, cmsfile($navItem, $fileId)) }}" alt="{{ cmsconfig($page, 'website.data.title', cms($page->ancestorsAndSelf->first() ?? $page, 'name')) }}">
                            @else
                                {{ cmsconfig($page, 'website.data.title', cms($page->ancestorsAndSelf->first() ?? $page, 'name')) }}
                            @endif
                        </a>
                        @if($project->version ?? null)
                            <span class="version">{{ $project->version }}</span>
                        @endif
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
                    <li class="search">
                        <a href="#" class="search" data-modal="modal-search" title="{{ __('Search docs') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0"/>
                            </svg>
                            <span>{{ __('Search docs') }}</span>
                            <kbd>Ctrl K</kbd>
                        </a>
                    </li>
                    @foreach($nav->items() as $item)
                        <li>
                            <a href="{{ cmsroute($item) }}" class="{{ $page->isSelfOrDescendantOf($item) ? 'active' : '' }}">
                                {{ cms($item, 'name') }}
                            </a>
                        </li>
                    @endforeach
                    @if($repo = cmslink($project->repository ?? null))
                        <li class="repository">
                            <a href="{{ $repo }}" title="{{ __('Repository') }}" aria-label="{{ __('Repository') }}" rel="noopener">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                    <path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27s1.36.09 2 .27c1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.01 8.01 0 0 0 16 8c0-4.42-3.58-8-8-8"/>
                                </svg>
                            </a>
                        </li>
                    @endif
                    @if(Route::has('login'))
                        <li class="login">
                            <a href="{{ route('login') }}" title="{{ __('Login') }}" aria-label="{{ __('Login') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
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

        @if($nav->ancestors()->count() > 1 && cms($page, 'type') !== 'docs')
            <nav class="breadcrumb" aria-label="{{ __('Breadcrumb navigation') }}">
                <ul>
                    @foreach($nav->ancestors()->skip(1) as $item)
                        <li>
                            <a role="button" href="{{ cmsroute($item) }}">{{ cms($item, 'name') }}</a>
                        </li>
                    @endforeach
                    <li>{{ cms($page, 'name') }}</li>
                </ul>
            </nav>
        @endif

        <main id="main">
            @yield('main')
        </main>

        @yield('footer')

        <footer class="bottom">
            <div class="container">
                <span class="copyright">
                    &copy; {{ date('Y') }} {{ cmsconfig($page, 'website.data.title', cms($page->ancestorsAndSelf->first() ?? $page, 'name')) }}
                </span>
                <span class="powered">
                    {!! __('Powered by :name', ['name' => '<a href="https://pagible.com">PagibleAI CMS</a>']) !!}
                </span>
                @if($project->license ?? null)
                    <span class="license">{{ __('License') }}: {{ $project->license }}</span>
                @endif
            </div>
        </footer>

        @include('cms::layouts.foot')
        <script defer src="{{ cmstheme($page, 'manual.js') }}"></script>
    </body>
</html>
