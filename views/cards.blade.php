@pushOnce('css')
<link href="{{ cmsasset('vendor/cms/theme/cards.css') }}" rel="stylesheet">
@endPushOnce

@if($data->title)
	<h2>{{ @$data->title }}</h2>
@endif

<div class="card-list">
	@foreach($data->cards ?? [] as $card)
		<div class="card-item">
			@if($file = cms($files, @$card->file?->id))
				@include('cms::pic', ['file' => $file, 'class' => 'image', 'sizes' => '240px'])
			@endif
			<h3 class="title">{{ @$card->title }}</h3>
			@if(@$card->text)
				<div class="text">
					@markdown($card->text)
				</div>
			@endif
		</div>
	@endforeach
</div>
