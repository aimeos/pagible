<!DOCTYPE html>
<html class="no-js" data-theme="light" lang="{{ cms($page, 'lang') }}" dir="{{ in_array(strtok((string) cms($page, 'lang'), '-_'), ['ar', 'dv', 'fa', 'he', 'ku', 'ur']) ? 'rtl' : 'ltr' }}">
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

        <meta name="theme-color" content="{{ cms($page, 'config.theme.data.--pico-background-color') ?: '#F7F2EC' }}">

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
            @if($business = $page->ancestorsAndSelf->reverse()->map(fn($item) => cms($item, 'config.mane::salon.data'))->first(fn($data) => $data))
            ,{
                "@@context": "https://schema.org",
                "@@type": {!! cmsjson($business->{'business-type'} ?? 'HairSalon') !!},
                "name": {!! cmsjson($business->name ?? '') !!},
                "url": {!! cmsjson(url('/')) !!},
                "address": {
                    "@@type": "PostalAddress",
                    "streetAddress": {!! cmsjson($business->{'street-address'} ?? '') !!},
                    "postalCode": {!! cmsjson($business->{'postal-code'} ?? '') !!},
                    "addressLocality": {!! cmsjson($business->locality ?? '') !!},
                    "addressCountry": {!! cmsjson($business->country ?? '') !!}
                },
                "openingHoursSpecification": [
                    @foreach(array_values((array) ($business->hours ?? [])) as $hours)
                    {
                        "@@type": "OpeningHoursSpecification",
                        "dayOfWeek": {!! cmsjson('https://schema.org/' . ($hours->day ?? '')) !!},
                        "opens": {!! cmsjson($hours->opens ?? '') !!},
                        "closes": {!! cmsjson($hours->closes ?? '') !!}
                    }@if(!$loop->last),@endif
                    @endforeach
                ],
                @if($link = cmslink($business->prices ?? null))
                "hasOfferCatalog": {
                    "@@type": "OfferCatalog",
                    "name": {!! cmsjson(__('Services and prices')) !!},
                    "url": {!! cmsjson(url($link)) !!}
                },
                @endif
                @if($link = cmslink($business->booking ?? null))
                "potentialAction": {
                    "@@type": "ReserveAction",
                    "target": {!! cmsjson(url($link)) !!}
                },
                @endif
                @if($business->email ?? null)
                "email": {!! cmsjson($business->email) !!},
                @endif
                @if($business->{'price-range'} ?? null)
                "priceRange": {!! cmsjson($business->{'price-range'}) !!},
                @endif
                "telephone": {!! cmsjson($business->telephone ?? '') !!}
            }
            @endif
            ]
        </script>
    </head>
    <body class="theme-mane type-{{ cms($page, 'type') ?: 'page' }}">
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
        @if($business->announcement ?? null)
            <div class="topbar">
                <div class="container">
                    <span>{{ $business->announcement }}</span>
                </div>
            </div>
        @endif
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
                    @foreach($nav->items() as $item)
                        <li>
                            @if($item->children->count())
                                <details class="dropdown is-menu">
                                    <summary class="{{ $page->isSelfOrDescendantOf($item) ? 'active' : '' }}">{{ cms($item, 'name') }}</summary>
                                    <ul class="align">
                                        <li>
                                            <a href="{{ cmsroute($item) }}" class="{{ $page->id === $item->id ? 'active' : '' }}">
                                                {{ cms($item, 'name') }}
                                            </a>
                                        </li>
                                        @foreach($item->children as $subItem)
                                            <li>
                                                <a href="{{ cmsroute($subItem) }}" class="{{ $page->isSelfOrDescendantOf($subItem) ? 'active' : '' }}">
                                                    {{ cms($subItem, 'name') }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            @else
                                <a href="{{ cmsroute($item) }}" class="{{ $page->isSelfOrDescendantOf($item) ? 'active' : '' }}">
                                    {{ cms($item, 'name') }}
                                </a>
                            @endif
                        </li>
                    @endforeach
                    @if($booking = cmslink($business->booking ?? null))
                        <li class="book">
                            <a href="{{ $booking }}">{{ __('Book now') }}</a>
                        </li>
                    @endif
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

        @if($nav->ancestors()->count() > 1)
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
                @if($business)
                    <address class="visit">
                        <span class="label">{{ __('Visit us') }}</span>
                        <span>{{ $business->name ?? '' }}</span>
                        <span>{{ $business->{'street-address'} ?? '' }}</span>
                        <span>{{ trim(($business->{'postal-code'} ?? '') . ' ' . ($business->locality ?? '')) }}</span>
                        @if($phone = preg_replace('/[^+0-9]/', '', (string) ($business->telephone ?? '')))
                            <a href="tel:{{ $phone }}">{{ $business->telephone }}</a>
                        @endif
                        @if($business->email ?? null)
                            <a href="mailto:{{ $business->email }}">{{ $business->email }}</a>
                        @endif
                    </address>
                    @if($business->hours ?? null)
                        <div class="hours">
                            <span class="label">{{ __('Opening hours') }}</span>
                            <dl>
                                @foreach(array_values((array) $business->hours) as $hours)
                                    @if($hours->day ?? null)
                                        <dt>{{ \Illuminate\Support\Carbon::parse($hours->day)->locale((string) cms($page, 'lang'))->isoFormat('dddd') }}</dt>
                                        <dd>{{ $hours->opens ?? '' }}–{{ $hours->closes ?? '' }}</dd>
                                    @endif
                                @endforeach
                            </dl>
                        </div>
                    @endif
                @endif
                @if($business->notice ?? null)
                    <p class="notice">{{ $business->notice }}</p>
                @endif
                <span class="copyright">
                    &copy; {{ date('Y') }} {{ cmsconfig($page, 'website.data.title', cms($page->ancestorsAndSelf->first() ?? $page, 'name')) }}
                </span>
            </div>
        </footer>

        @if($business && ($business->{'action-bar'} ?? true) && (($phone ?? null) || $booking))
            <div class="action-bar">
                @if($phone ?? null)
                    <a class="call" href="tel:{{ $phone }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                            <path fill-rule="evenodd" d="M1.885.511a1.745 1.745 0 0 1 2.61.163L6.29 2.98c.329.423.445.974.315 1.494l-.547 2.19a.68.68 0 0 0 .178.643l2.457 2.457a.68.68 0 0 0 .644.178l2.189-.547a1.75 1.75 0 0 1 1.494.315l2.306 1.794c.829.645.905 1.87.163 2.611l-1.034 1.034c-.74.74-1.846 1.065-2.877.702a18.6 18.6 0 0 1-7.01-4.42 18.6 18.6 0 0 1-4.42-7.009c-.362-1.03-.037-2.137.703-2.877z"/>
                        </svg>
                        {{ __('Call us') }}
                    </a>
                @endif
                @if($booking)
                    <a class="book" href="{{ $booking }}">{{ __('Book now') }}</a>
                @endif
            </div>
        @endif

        @include('cms::layouts.foot')
    </body>
</html>
