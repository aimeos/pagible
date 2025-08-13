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
        @yield('header')
        @yield('main')
        @yield('footer')

        <footer class="copyright">
            &copy; {{ date('Y') }} {{ config('app.name') }}
        </footer>
    </body>
</html>
