@pushOnce('foot')
<link href="{{ cmstheme($page, 'pricing.css') }}" rel="preload" as="style">
<script defer src="{{ cmstheme($page, 'pricing.js') }}"></script>
@endPushOnce

@if($data->title ?? null)
	<h2 class="title">{{ $data->title }}</h2>
@endif

@if($data->text ?? null)
	<p class="subtitle">{{ $data->text }}</p>
@endif

@if(($data->label ?? null) && ($data->{'label-alternative'} ?? null))
	<div class="pricing-toggle">
		<span>{{ $data->label }}</span>
		<span>{{ $data->{'label-alternative'} }}</span>
	</div>
@endif

<div class="pricing-list">
	@foreach(cms($data, 'items', []) as $item)
		<div class="pricing-item{{ ($item->highlight ?? false) ? ' highlight' : '' }}"
			data-price="{{ $item->price ?? '' }}"
			data-unit="{{ $item->unit ?? '' }}"
			data-priceid="{{ $item->priceid ?? '' }}"
			data-price-alternative="{{ $item->{'price-alternative'} ?? '' }}"
			data-unit-alternative="{{ $item->{'unit-alternative'} ?? '' }}"
			data-priceid-alternative="{{ $item->{'priceid-alternative'} ?? '' }}">

			@if($item->badge ?? null)
				<div class="badge">{{ $item->badge }}</div>
			@endif

			@if($file = cms($files, $item->file?->id ?? null))
				@include('cms::pic', ['file' => $file, 'class' => 'pricing-image', 'sizes' => '(max-width: 576px) 100vw, 33vw'])
			@endif

			<div class="pricing-header">
				<div class="price">
					<span class="amount">{{ $item->price ?? '' }}</span>
					@if($item->unit ?? null)
						<span class="unit">{{ $item->unit }}</span>
					@endif
				</div>
				<h3 class="name">{{ $item->name ?? '' }}</h3>
				@if($item->text ?? null)
					<p class="text">{{ $item->text }}</p>
				@endif
			</div>

			@if($item->features ?? null)
				<div class="features">
					@markdown($item->features)
				</div>
			@endif

			@if(($item->priceid ?? null) && Route::has('cms.cashier'))
				<form method="POST" action="{{ route('cms.cashier') }}">
					@csrf
					<input type="hidden" name="priceid" value="{{ $item->priceid }}">
					<input type="hidden" name="success" value="{{ ($item->success ?? null) ?: '/' }}">
					<button type="submit" class="btn">{{ ($item->button ?? null) ?: __('Get Started') }}</button>
				</form>
			@elseif($item->url ?? null)
				<a class="btn" href="{{ $item->url }}">{{ ($item->button ?? null) ?: __('Get Started') }}</a>
			@endif
		</div>
	@endforeach
</div>

