<!DOCTYPE html>
<html class="no-js" lang="{{ cms($page, 'lang') }}" dir="{{ in_array(cms($page, 'lang'), ['ar', 'az', 'dv', 'fa', 'he', 'ku', 'ur']) ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta http-equiv="Content-Security-Policy" content="
            base-uri 'self';
            default-src 'self';
            img-src 'self' data: blob:;
            media-src 'self' data: blob:;
            style-src 'self' https://hcaptcha.com https://*.hcaptcha.com;
            script-src 'self' https://hcaptcha.com https://*.hcaptcha.com;
            frame-src 'self' https://hcaptcha.com https://*.hcaptcha.com;
            connect-src 'self' https://hcaptcha.com https://*.hcaptcha.com
        ">

        <title>{{ cms($page, 'title') }}</title>

        @foreach(cms($page, 'meta', []) as $item)
            @includeFirst(cmsviews($page, $item), cmsdata($page, $item))
        @endforeach

        <link href="{{ cmsasset('vendor/cms/theme/pico.min.css') }}" rel="stylesheet">
        <link href="{{ cmsasset('vendor/cms/theme/pico.nav.min.css') }}" rel="stylesheet">
        <link href="{{ cmsasset('vendor/cms/theme/pico.dropdown.min.css') }}" rel="stylesheet">
        <link href="{{ cmsasset('vendor/cms/theme/cms.css') }}" rel="stylesheet">
        @stack('css')

        <script defer src="{{ cmsasset('vendor/cms/theme/cms.js') }}"></script>
        @stack('js')

        @if(\Aimeos\Cms\Permission::can('page:save', auth()->user()))
            <link href="{{ cmsasset('vendor/cms/admin/editor.css') }}" rel="stylesheet">
            <script defer src="{{ cmsasset('vendor/cms/admin/editor.js') }}"></script>
        @endif
    </head>
    <body class="theme-{{ cms($page, 'theme') ?: 'cms' }} type-{{ cms($page, 'type') ?: 'page' }}">
        <header>
            <nav>
                <ul>
                    <li class="sidebar-open show">
                        <button aria-label="{{ __('Open sidebar') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-chevron-right" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708"/>
                            </svg>
                        </button>
                    </li>
                    <li class="sidebar-close">
                        <button aria-label="{{ __('Close sidebar') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-chevron-left" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0"/>
                            </svg>
                        </button>
                    </li>
                    <li class="brand">
                        <a href="{{ cmsroute($page->ancestors?->first() ?? $page) }}" class="contrast">
                            <strong>{{ config('app.name') }}</strong>
                        </a>
                    </li>
                    <li class="menu-close">
                        <button aria-label="{{ __('Close navigation') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-x-lg" viewBox="0 0 16 16">
                                <path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/>
                            </svg>
                        </button>
                    </li>
                </ul>
                <ul class="menu">
                    @foreach($page->nav() as $item)
                        @if(cms($item, 'status') == 1)
                            <li>
                                @if($item->children->count())
                                    <details class="dropdown is-menu">
                                        <summary role>{{ cms($item, 'name') }}</summary>
                                        <ul class="align">
                                            @foreach($item->children as $subItem)
                                                @if(cms($subItem, 'status') == 1)
                                                    <li>
                                                        <a href="{{ cmsroute($subItem) }}" class="{{ $page->isSelfOrDescendantOf($subItem) ? 'active' : '' }} contrast">
                                                            {{ cms($subItem, 'name') }}
                                                        </a>
                                                    </li>
                                                @endif
                                            @endforeach
                                        </ul>
                                    </details>
                                @else
                                    <a href="{{ cmsroute($item) }}" class="{{ $page->isSelfOrDescendantOf($item) ? 'active' : '' }} contrast">
                                        {{ cms($item, 'name') }}
                                    </a>
                                @endif
                            </li>
                        @endif
                    @endforeach
                </ul>
                <ul class="menu-open show">
                    <li>
                        <button aria-label="{{ __('Open navigation') }}">
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
                    @foreach($page->ancestors->skip(1) as $item)
                        @if(cms($item, 'status') == 1)
                            <li>
                                <a href="{{ cmsroute($item) }}">{{ cms($item, 'name') }}</a>
                            </li>
                        @else
                            @break
                        @endif
                    @endforeach
                    <li>{{ cms($page, 'name') }}</li>
                </ul>
            </nav>
        @endif

        @yield('main')

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
    </body>
</html>
