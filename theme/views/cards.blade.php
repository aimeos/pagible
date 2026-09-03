@pushOnce('foot')
<link href="{{ cmstheme($page, 'cards.css') }}" rel="preload" as="style">
@endPushOnce

@if($data->title ?? null)
	<h2>{{ $data->title }}</h2>
@endif

<div class="card-list cols-{{ $data->columns ?? 'auto' }}">
	@foreach($data->cards ?? [] as $card)
		@php($url = cmslink($card->url ?? null))
		<div class="card-item">
			@if($file = cms($files, $card->file?->id ?? null))
				@if($url)
					<a class="card-image" href="{{ $url }}">
						@include('cms::pic', ['file' => $file, 'class' => 'image', 'sizes' => '(max-width: 576px) 100vw, (max-width: 768px) 66vw, 33vw'])
					</a>
				@else
					@include('cms::pic', ['file' => $file, 'class' => 'image', 'sizes' => '(max-width: 576px) 100vw, (max-width: 768px) 66vw, 33vw'])
				@endif
			@endif
			<div class="card-text">
				@if($card->title ?? null)
					<h3 class="title">@if($url)<a href="{{ $url }}">@endif{{ $card->title }}@if($url)</a>@endif</h3>
				@endif
				@if($card->text ?? null)
					<div class="cms-text">@markdown($card->text)</div>
				@endif
			</div>
		</div>
	@endforeach
</div>
