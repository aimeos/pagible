@pushOnce('foot')
<link href="{{ cmstheme($page, 'text-trio.css') }}" rel="preload" as="style">
@endPushOnce

<section class="text-trio">
    @if($data->title ?? null)
        <h2 class="text-trio-title">{{ $data->title }}</h2>
    @endif
    @if($data->leading ?? null)
        <p class="text-trio-leading">{{ $data->leading }}</p>
    @endif
    @if($data->supporting ?? null)
        <p class="text-trio-supporting">{{ $data->supporting }}</p>
    @endif
</section>
