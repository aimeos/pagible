@pushOnce('css')
<link type="text/css" rel="stylesheet" href="{{ cmsasset('vendor/cms/theme/cards.css') }}">
@endPushOnce

@if($data->title)
	<h2>{{ @$data->title }}</h2>
@endif

<div class="card-list">
	@foreach($data->cards ?? [] as $card)
		<div class="card-item">
			@if($file = cms($files, @$card->file?->id))
				@include('cms::pic', ['file' => $file, 'class' => 'image'])
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
