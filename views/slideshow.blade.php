@pushOnce('css')
<link type="text/css" rel="stylesheet" href="{{ cmsasset('vendor/cms/theme/slideshow.css') }}">
@endPushOnce

@pushOnce('js')
<script defer src="{{ cmsasset('vendor/cms/theme/slideshow.js') }}"></script>
@endPushOnce

@if(@$data->title)
	<h2>{{ $data->title }}</h2>
@endif
<div class="swiffy-slider slider-item-nogap slider-nav-animation slider-nav-autoplay slider-nav-autopause slider-nav-round slider-nav-dark"
	data-slider-nav-autoplay-interval="4000">

	<div class="slider-container">
		@foreach($data->files ?? [] as $item)
			@if($file = cms($files, @$item->id))
				@include('cms::pic', ['file' => $file])
			@else
				<!-- no image file -->
			@endif
		@endforeach
	</div>

	<button type="button" class="slider-nav slider-nav-prev" aria-label="Go to previous"></button>
	<button type="button" class="slider-nav slider-nav-next" aria-label="Go to next"></button>
</div>