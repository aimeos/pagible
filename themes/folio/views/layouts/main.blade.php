<!DOCTYPE html>
<html class="no-js" lang="{{ cms($page, 'lang') }}" dir="{{ \Aimeos\Cms\Utils::textDir(cms($page, 'lang')) }}">
    <head>
        <x-cms::head :page="$page" :nav="$nav" />

        <meta name="color-scheme" content="light dark">

        <script src="{{ cmstheme($page, 'folio.js') }}"></script>
        <link href="{{ cmstheme($page, 'pico.min.css') }}" rel="stylesheet">
        <link href="{{ cmstheme($page, 'pico.nav.min.css') }}" rel="stylesheet">
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
                "@@type": {!! cmsjson($nav->ancestors()->isEmpty() ? 'ProfilePage' : 'WebPage') !!},
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
            @if(($profileItem = $page->ancestorsAndSelf->reverse()->first(fn($item) => cms($item, 'config.folio::profile.data'))) && ($profile = cms($profileItem, 'config.folio::profile.data')))
            ,{
                "@@context": "https://schema.org",
                "@@type": "Person",
                "name": {!! cmsjson($profile->name ?? '') !!},
                @if($profile->job ?? null)
                "jobTitle": {!! cmsjson($profile->job) !!},
                @endif
                @if($profile->company ?? null)
                "worksFor": {"@@type": "Organization", "name": {!! cmsjson($profile->company) !!}},
                @endif
                @if($profile->location ?? null)
                "homeLocation": {"@@type": "Place", "name": {!! cmsjson($profile->location) !!}},
                @endif
                @if($profile->bio ?? null)
                "description": {!! cmsjson($profile->bio) !!},
                @endif
                @if(($avatarId = $profile->avatar->id ?? null) && ($avatar = cmsfile($profileItem, $avatarId)))
                "image": {!! cmsjson(cmsasset($profileItem, $avatar)) !!},
                @endif
                @if($sameAs = collect($profile->links ?? [])->map(fn($link) => cmslink($link->url ?? null))->filter()->values()->all())
                "sameAs": {!! cmsjson($sameAs) !!},
                @endif
                "url": {!! cmsjson(url('/')) !!}
            }
            @endif
            ]
        </script>
    </head>
    <body class="theme-folio type-{{ cms($page, 'type') ?: 'page' }}">
        <a href="#main" class="skip-link">{{ __('Skip to main content') }}</a>
        <x-cms::search :page="$page" />

        <header>
            <nav role="navigation" aria-label="{{ __('Main navigation') }}">
                <ul>
                    <li class="brand">
                        <x-cms::brand :page="$page" :nav="$nav" :name="$profile->name ?? null" />
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
                    <x-cms::menu :page="$page" :nav="$nav" :dropdown="false" />
                </ul>
                <ul class="tools">
                    <li>
                        <a href="#" class="search" data-modal="modal-search" title="{{ __('Search') }}" aria-label="{{ __('Search') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0"/>
                            </svg>
                        </a>
                    </li>
                    <li>
                        <button type="button" class="mode" title="{{ __('Switch between light and dark mode') }}" aria-label="{{ __('Switch between light and dark mode') }}">
                            <svg class="sun" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                <path d="M8 11a3 3 0 1 1 0-6 3 3 0 0 1 0 6m0 1a4 4 0 1 0 0-8 4 4 0 0 0 0 8M8 0a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 0m0 13a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 13m8-5a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2a.5.5 0 0 1 .5.5M3 8a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2A.5.5 0 0 1 3 8m10.657-5.657a.5.5 0 0 1 0 .707l-1.414 1.415a.5.5 0 1 1-.707-.708l1.414-1.414a.5.5 0 0 1 .707 0m-9.193 9.193a.5.5 0 0 1 0 .707L3.05 13.657a.5.5 0 0 1-.707-.707l1.414-1.414a.5.5 0 0 1 .707 0m9.193 2.121a.5.5 0 0 1-.707 0l-1.414-1.414a.5.5 0 0 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .707M4.464 4.465a.5.5 0 0 1-.707 0L2.343 3.05a.5.5 0 1 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .708"/>
                            </svg>
                            <svg class="moon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                <path d="M6 .278a.77.77 0 0 1 .08.858 7.2 7.2 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277q.792-.001 1.533-.16a.79.79 0 0 1 .81.316.73.73 0 0 1-.031.893A8.35 8.35 0 0 1 8.344 16C3.734 16 0 12.286 0 7.71 0 4.266 2.114 1.312 5.124.06A.75.75 0 0 1 6 .278"/>
                            </svg>
                        </button>
                    </li>
                    <x-cms::languages :page="$page" :nav="$nav" />
                    @if(Route::has('login'))
                        <li class="login">
                            <a href="{{ route('login') }}" title="{{ __('Login') }}" aria-label="{{ __('Login') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12,4A4,4 0 0,1 16,8A4,4 0 0,1 12,12A4,4 0 0,1 8,8A4,4 0 0,1 12,4M12,14C16.42,14 20,15.79 20,18V20H4V18C4,15.79 7.58,14 12,14Z" />
                                </svg>
                            </a>
                        </li>
                    @endif
                    <li class="menu-open show">
                        <button aria-label="{{ __('Open navigation') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5"/>
                            </svg>
                        </button>
                    </li>
                </ul>
            </nav>
        </header>

        <main id="main">
            @yield('main')
        </main>

        @yield('footer')

        <footer class="bottom">
            <div class="container">
                @if(($profile->links ?? null) || ($profile->email ?? null))
                    <ul class="profiles">
                        @foreach($profile->links ?? [] as $link)
                            @if($url = cmslink($link->url ?? null))
                                <li><a href="{{ $url }}" rel="me noopener">{{ $link->label ?? '' }}</a></li>
                            @endif
                        @endforeach
                        @if($profile->email ?? null)
                            <li class="email">
                                <a href="mailto:{{ $profile->email }}">{{ $profile->email }}</a>
                                <button type="button" class="copy" data-copy="{{ $profile->email }}" data-copied="{{ __('Copied') }}" title="{{ __('Copy e-mail address') }}" aria-label="{{ __('Copy e-mail address') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M4 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zm2-1a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1zM2 5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-1h1v1a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h1v1z"/>
                                    </svg>
                                </button>
                            </li>
                        @endif
                    </ul>
                @endif
                <p class="colophon">
                    <span>&copy; {{ date('Y') }} {{ $profile->name ?? cmsconfig($page, 'website.data.title', cms($page->ancestorsAndSelf->first() ?? $page, 'name')) }}</span>
                    <span>{!! __('Powered by :name', ['name' => '<a href="https://pagible.com">PagibleAI CMS</a>']) !!}</span>
                </p>
            </div>
        </footer>

        @include('cms::layouts.foot')
    </body>
</html>
