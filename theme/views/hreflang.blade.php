@if(isset($nav) && ($variants = $nav->variants())->count() > 1)
    @foreach($variants as $variant)
        <link rel="alternate" hreflang="{{ $variant->lang }}" href="{{ cmsroute('cms.page', ['path' => $variant->path], $variant->domain) }}">
    @endforeach
    @if($default = $variants->firstWhere('lang', config('app.fallback_locale')))
        <link rel="alternate" hreflang="x-default" href="{{ cmsroute('cms.page', ['path' => $default->path], $default->domain) }}">
    @endif
@endif
