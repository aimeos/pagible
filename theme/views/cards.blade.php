@pushOnce('foot')
<link href="{{ cmstheme($page, 'cards.css') }}" rel="preload" as="style">
@endPushOnce

@if($data->title ?? null)
	<h2>{{ $data->title }}</h2>
@endif

<div class="card-list cols-{{ $data->columns ?? 'auto' }}">
	@foreach($data->cards ?? [] as $card)
		<div class="card-item">
			@if($file = cms($files, $card->file?->id ?? null))
				@if(cmslink($card->url ?? null))
					<a class="card-image" href="{{ cmslink($card->url) }}" rel="{{ $card->{'url-rel'} ?? '' }}">
						@include('cms::pic', ['file' => $file, 'class' => 'image', 'sizes' => '(max-width: 576px) 100vw, (max-width: 768px) 66vw, 33vw'])
					</a>
				@else
					@include('cms::pic', ['file' => $file, 'class' => 'image', 'sizes' => '(max-width: 576px) 100vw, (max-width: 768px) 66vw, 33vw'])
				@endif
			@endif
			<div class="card-text">
				@if($card->title ?? null)
					<h3 class="title">@if(cmslink($card->url ?? null))<a href="{{ cmslink($card->url) }}" rel="{{ $card->{'url-rel'} ?? '' }}">@endif{{ $card->title }}@if(cmslink($card->url ?? null))</a>@endif</h3>
				@endif
				@if($card->text ?? null)
					<div class="cms-text">@markdown($card->text)</div>
				@endif
			</div>
		</div>
	@endforeach
</div>
