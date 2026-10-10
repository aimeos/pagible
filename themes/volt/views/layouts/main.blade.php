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

        <meta name="theme-color" content="#0B1530">

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
            @if($business = $page->ancestorsAndSelf->reverse()->map(fn($item) => cms($item, 'config.volt::business.data'))->first(fn($data) => $data))
            ,{
                "@@context": "https://schema.org",
                "@@type": {!! cmsjson($business->{'business-type'} ?? 'Electrician') !!},
                "name": {!! cmsjson($business->name ?? '') !!},
                "url": {!! cmsjson(url('/')) !!},
                "address": {
                    "@@type": "PostalAddress",
                    "streetAddress": {!! cmsjson($business->{'street-address'} ?? '') !!},
                    "postalCode": {!! cmsjson($business->{'postal-code'} ?? '') !!},
                    "addressLocality": {!! cmsjson($business->locality ?? '') !!},
                    "addressCountry": {!! cmsjson($business->country ?? '') !!}
                },
                "areaServed": [
                    @foreach(array_values(array_filter(array_map('trim', explode(',', (string) ($business->area ?? ''))))) as $place)
                    {"@@type": "Place", "name": {!! cmsjson($place) !!}}@if(!$loop->last),@endif
                    @endforeach
                ],
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
                @if($business->email ?? null)
                "email": {!! cmsjson($business->email) !!},
                @endif
                @if($business->{'price-range'} ?? null)
                "priceRange": {!! cmsjson($business->{'price-range'}) !!},
                @endif
                @if($business->{'emergency-phone'} ?? null)
                "contactPoint": {
                    "@@type": "ContactPoint",
                    "contactType": "emergency",
                    @if($business->emergency ?? false)
                    "hoursAvailable": {
                        "@@type": "OpeningHoursSpecification",
                        "dayOfWeek": ["https://schema.org/Monday", "https://schema.org/Tuesday", "https://schema.org/Wednesday", "https://schema.org/Thursday", "https://schema.org/Friday", "https://schema.org/Saturday", "https://schema.org/Sunday"],
                        "opens": "00:00",
                        "closes": "23:59"
                    },
                    @endif
                    "telephone": {!! cmsjson($business->{'emergency-phone'}) !!}
                },
                @endif
                "telephone": {!! cmsjson($business->telephone ?? '') !!}
            }
            @endif
            ]
        </script>
    </head>
    <body class="theme-volt type-{{ cms($page, 'type') ?: 'page' }}">
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
            @if($emergency = preg_replace('/[^+0-9]/', '', (string) ($business->{'emergency-phone'} ?? '')))
                <div class="emergency">
                    {{ ($business->emergency ?? false) ? __('24/7 emergency service') : __('Emergency') }}
                    <a href="tel:{{ $emergency }}">{{ $business->{'emergency-phone'} }}</a>
                </div>
            @endif
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
                                    <summary>{{ cms($item, 'name') }}</summary>
                                    <ul class="align">
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
                    @if($phone = preg_replace('/[^+0-9]/', '', (string) ($business->telephone ?? '')))
                        <li class="phone">
                            <a href="tel:{{ $phone }}" aria-label="{{ __('Call us') }}: {{ $business->telephone }}">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M1.885.511a1.745 1.745 0 0 1 2.61.163L6.29 2.98c.329.423.445.974.315 1.494l-.547 2.19a.68.68 0 0 0 .178.643l2.457 2.457a.68.68 0 0 0 .644.178l2.189-.547a1.75 1.75 0 0 1 1.494.315l2.306 1.794c.829.645.905 1.87.163 2.611l-1.034 1.034c-.74.74-1.846 1.065-2.877.702a18.6 18.6 0 0 1-7.01-4.42 18.6 18.6 0 0 1-4.42-7.009c-.362-1.03-.037-2.137.703-2.877z"/>
                                </svg>
                                <span>{{ $business->telephone }}</span>
                            </a>
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
                <span class="copyright">
                    &copy; {{ date('Y') }} {{ cmsconfig($page, 'website.data.title', cms($page->ancestorsAndSelf->first() ?? $page, 'name')) }}
                </span>
                @if($business)
                    <span class="contact">
                        @if($tel = preg_replace('/[^+0-9]/', '', (string) ($business->telephone ?? '')))
                            <a href="tel:{{ $tel }}">{{ $business->telephone }}</a>
                        @endif
                        @if($business->email ?? null)
                            <a href="mailto:{{ $business->email }}">{{ $business->email }}</a>
                        @endif
                    </span>
                @endif
            </div>
        </footer>

        @if(($business->{'call-button'} ?? true) && ($tel = preg_replace('/[^+0-9]/', '', (string) ($business->telephone ?? ''))))
            <a class="call-button" href="tel:{{ $tel }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                    <path fill-rule="evenodd" d="M1.885.511a1.745 1.745 0 0 1 2.61.163L6.29 2.98c.329.423.445.974.315 1.494l-.547 2.19a.68.68 0 0 0 .178.643l2.457 2.457a.68.68 0 0 0 .644.178l2.189-.547a1.75 1.75 0 0 1 1.494.315l2.306 1.794c.829.645.905 1.87.163 2.611l-1.034 1.034c-.74.74-1.846 1.065-2.877.702a18.6 18.6 0 0 1-7.01-4.42 18.6 18.6 0 0 1-4.42-7.009c-.362-1.03-.037-2.137.703-2.877z"/>
                </svg>
                {{ __('Call us') }}
            </a>
        @endif


        @include('cms::layouts.foot')
    </body>
</html>
