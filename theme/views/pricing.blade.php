@pushOnce('js')
<link rel="preload" href="{{ cmstheme($page, 'pricing.css') }}" as="style">
@endPushOnce

@if(@$data->title)
	<h2 class="title">{{ $data->title }}</h2>
@endif

@if(@$data->text)
	<p class="subtitle">{{ $data->text }}</p>
@endif

<div class="pricing-list">
	@foreach(cms($data, 'items', []) as $item)
		<div class="pricing-item{{ @$item->highlight ? ' highlight' : '' }}">
			@if(@$item->highlight)
				<div class="badge">{{ __('Most Popular') }}</div>
			@endif

			<div class="pricing-header">
				<div class="price">
					<span class="amount">{{ @$item->price }}</span>
					@if(@$item->unit)
						<span class="unit">{{ $item->unit }}</span>
					@endif
				</div>
				<h3 class="name">{{ @$item->name }}</h3>
				@if(@$item->text)
					<p class="text">{{ $item->text }}</p>
				@endif
			</div>

			@if(@$item->features)
				<div class="features">
					@markdown($item->features)
				</div>
			@endif

			@if(@$item->url)
				<a class="btn" href="{{ $item->url }}">{{ @$item->button ?: __('Get Started') }}</a>
			@endif
		</div>
	@endforeach
</div>
