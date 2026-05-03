@pushOnce('css')
<link href="{{ cmstheme($page, 'slideshow.css') }}" rel="stylesheet">
@endPushOnce

@pushOnce('js')
<script defer src="{{ cmstheme($page, 'slideshow.js') }}"></script>
@endPushOnce

@if(@$data->title)
	<h2>{{ $data->title }}</h2>
@endif
<div class="swiffy-slider slider-item-nogap slider-nav-animation slider-nav-autoplay slider-nav-autopause slider-nav-round slider-nav-dark"
	data-slider-nav-autoplay-interval="4000">

	<div class="slider-container">
		@foreach($data->files ?? [] as $idx => $item)
			@if($file = cms($files, @$item->id))
				@include('cms::pic', ['file' => $file, 'main' => ($idx == 0 ? @$data->main : false), 'sizes' => '(max-width: 1200px) 100vw, 1200px'])
			@else
				<!-- no image file -->
			@endif
		@endforeach
	</div>

	<button type="button" class="slider-nav slider-nav-prev" aria-label="Go to previous"></button>
	<button type="button" class="slider-nav slider-nav-next" aria-label="Go to next"></button>
</div>

<script type="application/ld+json">{
	"@@context": "https://schema.org",
	"@@type": "ImageGallery",
	"name": {{ Js::from(@$data->title ?? cms($page, 'title')) }},
	"image": [
	@foreach($data->files ?? [] as $item)
		@if($file = cms($files, @$item->id))
			{
				"@@type": "ImageObject",
				"contentUrl": {{ Js::from(cmsurl(cms($file, 'path'))) }},
				"name": {{ Js::from(@cms($file, 'name')) }},
				"description": {{ Js::from(@cms($file, 'description')?->{cms($page, 'lang')}) }}
			}
			@if(!$loop->last),@endif
		@endif
	@endforeach
	]
}</script>