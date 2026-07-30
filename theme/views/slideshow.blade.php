@pushOnce('foot')
<link href="{{ cmstheme($page, 'slideshow.css') }}" rel="preload" as="style">
@endPushOnce

@pushOnce('foot')
<script defer src="{{ cmstheme($page, 'slideshow.js') }}"></script>
@endPushOnce

@if($data->title ?? null)
	<h2>{{ $data->title }}</h2>
@endif
	<div class="swiffy-slider slider-item-nogap slider-nav-animation slider-nav-round slider-nav-dark{{ ($data->autoplay ?? true) ? ' slider-nav-autoplay slider-nav-autopause' : '' }}"
		@if($data->autoplay ?? true) data-slider-nav-autoplay-interval="4000" @endif>

		<div class="slider-container">
			@foreach($data->files ?? [] as $idx => $item)
				@if($file = cms($files, $item->id ?? null))
					@if($data->captions ?? false)
						<figure>
							@include('cms::pic', ['file' => $file, 'main' => ($idx == 0 ? ($data->main ?? false) : false), 'sizes' => '(max-width: 1200px) 100vw, 1200px'])
							@if($caption = cms($file, 'description')?->{cms($page, 'lang')})
								<figcaption>{{ $caption }}</figcaption>
							@endif
						</figure>
					@else
						@include('cms::pic', ['file' => $file, 'main' => ($idx == 0 ? ($data->main ?? false) : false), 'sizes' => '(max-width: 1200px) 100vw, 1200px'])
					@endif
				@else
					<!-- no image file -->
			@endif
		@endforeach
	</div>

	<button type="button" class="slider-nav slider-nav-prev" aria-label="{{ __('Go to previous') }}"></button>
	<button type="button" class="slider-nav slider-nav-next" aria-label="{{ __('Go to next') }}"></button>
</div>

<script type="application/ld+json">{!! cmsjson([
	'@context' => 'https://schema.org',
	'@type' => 'ImageGallery',
	'name' => $data->title ?? cms($page, 'title'),
	'image' => collect($data->files ?? [])
		->map( fn( $item ) => cms($files, $item->id ?? null) )
		->filter()
		->map( fn( $file ) => [
			'@type' => 'ImageObject',
			'contentUrl' => cmsasset($page, $file),
			'name' => cms($file, 'name') ?? '',
			'description' => cms($file, 'description')?->{cms($page, 'lang')} ?? '',
		] )
		->values()
		->all(),
]) !!}</script>
