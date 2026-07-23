<link href="{{ cmstheme($page, 'pico.modal.min.css') }}" rel="preload" as="style">
<link href="{{ cmstheme($page, 'cms-lazy.css') }}" rel="preload" as="style">
<script defer src="{{ cmstheme($page, 'csrf.js') }}"></script>
<script defer src="{{ cmstheme($page, 'cms.js') }}"></script>
@stack('foot')

@foreach($page->ancestorsAndSelf as $navItem)
    @if($text = cms($navItem, 'config.styles.data.text'))
        <style>{!! $text !!}</style>
    @endif
@endforeach

@foreach($page->ancestorsAndSelf as $navItem)
    @if($text = cms($navItem, 'config.javascript.data.text'))
        <script>{!! $text !!}</script>
    @endif
@endforeach

@if(\Aimeos\Cms\Permission::can('page:save', auth()->user()))
    @includeIf('cms::editor')
@else
    <script defer src="{{ cmstheme($page, 'stats.js') }}"></script>
@endif
