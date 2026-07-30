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

<div class="pricing-list">
	@foreach(cms($data, 'items', []) as $item)
		<div class="pricing-item{{ ($item->highlight ?? false) ? ' highlight' : '' }}">

			@if($item->badge ?? null)
				<div class="badge">{{ $item->badge }}</div>
			@endif

			@if($file = cms($files, $item->file?->id ?? null))
				@include('cms::pic', ['file' => $file, 'class' => 'pricing-image', 'sizes' => '(max-width: 576px) 100vw, 33vw'])
			@endif

			<div class="pricing-header">
				<div class="price">
					@foreach($item->prices ?? [] as $price)
						<span class="pricing-price"
							data-priceid="{{ $price?->id ?? '' }}"
							data-unit="{{ $price?->unit ?? '' }}"
							@if(!$loop->first) hidden @endif
						>
							<span class="amount">{{ $price?->amount ?? '' }}</span>
							@if($price?->currency ?? null)
								<span class="currency">{{ $price?->currency }}</span>
							@endif
							@if($price?->unit ?? null)
								<span class="unit">{{ $price?->unit ?? '' }}</span>
							@endif
						</span>
					@endforeach
				</div>
				<h3 class="name">{{ $item->name ?? '' }}</h3>
				@if($item->text ?? null)
					<p class="cms-text">{{ $item->text }}</p>
				@endif
			</div>

			@if($item->features ?? null)
				<div class="features cms-text">@markdown($item->features)</div>
			@endif

			@if(($item->id ?? null) && ($item->access ?? null) && (($item->prices[0] ?? null)?->id ?? null) && Route::has('cms.cashier'))
				<form method="POST" action="{{ route('cms.cashier') }}">
					<input type="hidden" name="_token" value="">
					<input type="hidden" name="page" value="{{ $page->id }}">
					<input type="hidden" name="element" value="{{ $id }}">
					<input type="hidden" name="package" value="{{ $item->id }}">
					<input type="hidden" name="price" value="{{ ($item->prices[0] ?? null)?->id ?? '' }}">
					<button type="submit" class="btn">{{ ($item->button ?? null) ?: __('Get Started') }}</button>
				</form>
			@elseif(!($item->access ?? null) && ($item->url ?? null))
				<a class="btn" href="{{ cmslink($item->url) }}">{{ ($item->button ?? null) ?: __('Get Started') }}</a>
			@endif
		</div>
	@endforeach
</div>
