@pushOnce('foot')
<link href="{{ cmstheme($page, 'cards.css') }}" rel="preload" as="style">
@endPushOnce

@if($data->title ?? null)
	<h2>{{ $data->title }}</h2>
@endif

<div class="card-list">
	@foreach($data->cards ?? [] as $card)
		<div class="card-item">
			@if($file = cms($files, $card->file?->id ?? null))
				@include('cms::pic', ['file' => $file, 'class' => 'image', 'sizes' => '(max-width: 576px) 100vw, (max-width: 768px) 66vw, 33vw'])
			@endif
			<h3 class="title">{{ $card->title ?? '' }}</h3>
			@if($card->text ?? null)
				<div class="text">
					@markdown($card->text)
				</div>
			@endif
		</div>
	@endforeach
</div>
