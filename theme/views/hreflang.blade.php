@if(isset($nav) && ($variants = $nav->variants())->count() > 1)
    @foreach($variants as $variant)
        <link rel="alternate" hreflang="{{ $variant->lang }}" href="{{ $variant->url }}">
    @endforeach
    @if($default = $variants->firstWhere('lang', config('app.fallback_locale')))
        <link rel="alternate" hreflang="x-default" href="{{ $default->url }}">
    @endif
@endif
