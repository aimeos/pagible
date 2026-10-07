@if($data->main ?? false)
@pushOnce('head', 'cms-before-after-head')
<link href="{{ cmstheme($page, 'before-after.css') }}" rel="stylesheet">
@endPushOnce
@else
@pushOnce('foot', 'cms-before-after-css')
<link href="{{ cmstheme($page, 'before-after.css') }}" rel="preload" as="style">
@endPushOnce
@endif
@pushOnce('foot', 'cms-before-after')
<script defer src="{{ cmstheme($page, 'before-after.js') }}"></script>
@endPushOnce

@if($data->title ?? null)
	<h2>{{ $data->title }}</h2>
@endif
@if(($before = cms($files, $data->before?->id ?? null)) && ($after = cms($files, $data->after?->id ?? null)))
	<div class="compare">
		@include('cms::pic', ['file' => $after, 'class' => 'after', 'main' => $data->main ?? false, 'sizes' => $sizes ?? '(max-width: 640px) 90vw, (max-width: 1200px) calc(100vw - 4rem), 1136px'])
		@include('cms::pic', ['file' => $before, 'class' => 'before', 'main' => $data->main ?? false, 'sizes' => $sizes ?? '(max-width: 640px) 90vw, (max-width: 1200px) calc(100vw - 4rem), 1136px'])
		<span class="label label-before" aria-hidden="true">{{ __('Before') }}</span>
		<span class="label label-after" aria-hidden="true">{{ __('After') }}</span>
		<span class="handle" aria-hidden="true"></span>
		<input type="range" min="0" max="100" value="50" aria-label="{{ __('Before and after comparison') }}">
	</div>
@else
	<!-- no image files -->
@endif
