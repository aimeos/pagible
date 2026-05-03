@pushOnce('js')
<link rel="preload" href="{{ cmstheme($page, 'pricing.css') }}" as="style">
@endPushOnce

@if(@$data->title)
	<h2 class="title">{{ $data->title }}</h2>
@endif

@if(@$data->text)
	<p class="subtitle">{{ $data->text }}</p>
@endif

@if(@$data->label && @$data->{'label-alternative'})
	<div class="pricing-toggle">
		<span class="pricing-toggle-label">{{ $data->label }}</span>
		<label class="pricing-switch">
			<input type="checkbox" class="pricing-period">
			<span class="pricing-slider"></span>
		</label>
		<span class="pricing-toggle-label">{{ $data->{'label-alternative'} }}</span>
	</div>
@endif

<div class="pricing-list">
	@foreach(cms($data, 'items', []) as $item)
		<div class="pricing-item{{ @$item->highlight ? ' highlight' : '' }}"
			data-price="{{ @$item->price }}"
			data-unit="{{ @$item->unit }}"
			data-priceid="{{ @$item->priceid }}"
			data-price-alternative="{{ @$item->{'price-alternative'} }}"
			data-unit-alternative="{{ @$item->{'unit-alternative'} }}"
			data-priceid-alternative="{{ @$item->{'priceid-alternative'} }}">
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

			@if(@$item->priceid && Route::has('cms.cashier'))
				<form method="POST" action="{{ route('cms.cashier') }}">
					@csrf
					<input type="hidden" name="priceid" value="{{ $item->priceid }}">
					<input type="hidden" name="success" value="{{ @$item->success ?: '/' }}">
					<button type="submit" class="btn">{{ @$item->button ?: __('Get Started') }}</button>
				</form>
			@elseif(@$item->url)
				<a class="btn" href="{{ $item->url }}">{{ @$item->button ?: __('Get Started') }}</a>
			@endif
		</div>
	@endforeach
</div>

@pushOnce('js')
<script>
document.querySelectorAll('.pricing .pricing-period').forEach(function(toggle) {
	toggle.addEventListener('change', function() {
		const section = toggle.closest('.pricing');
		const alt = toggle.checked;

		section.querySelectorAll('.pricing-item').forEach(function(item) {
			const amount = item.querySelector('.amount');
			const unit = item.querySelector('.unit');
			const priceid = item.querySelector('input[name="priceid"]');
			const suffix = alt ? '-alternative' : '';

			if(amount) {
				amount.textContent = item.dataset['price' + (alt ? 'Alternative' : '')] || amount.textContent;
			}

			if(unit) {
				unit.textContent = item.dataset['unit' + (alt ? 'Alternative' : '')] || unit.textContent;
			}

			if(priceid) {
				priceid.value = item.dataset['priceid' + (alt ? 'Alternative' : '')] || priceid.value;
			}
		});
	});
});
</script>
@endPushOnce
