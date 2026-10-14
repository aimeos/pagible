@pushOnce('foot')
<link href="{{ cmstheme($page, 'cards.css') }}" rel="preload" as="style">
@endPushOnce

@if($data->title ?? null)
	<h2>{{ $data->title }}</h2>
@endif

<div class="card-list cols-{{ $data->columns ?? 'auto' }} layout-{{ $data->layout ?? 'cards' }}{{ ($data->layout ?? '') === 'badges' ? ' marquee' : '' }}">
	@foreach(($data->layout ?? '') === 'badges' ? [false, true] : [false] as $clone)
		@foreach($data->cards ?? [] as $card)
			<div class="card-item{{ $clone ? ' clone' : '' }}"{!! $clone ? ' inert' : '' !!}>
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
					@if((string) ($card->title ?? '') !== '')
						<h3 class="title">@if(cmslink($card->url ?? null))<a href="{{ cmslink($card->url) }}" rel="{{ $card->{'url-rel'} ?? '' }}">{{ $card->title }}</a>@elseif(($data->layout ?? '') === 'figures' && preg_match('/^(\D*)(\d{1,6})(?:([.,])(\d{1,2}))?(\D*)$/su', trim($card->title), $num))<span class="count" data-count="{{ (int) ($num[2] . ($num[4] ?? '')) }}" data-scale="{{ 10 ** strlen($num[4] ?? '') }}" data-prefix="{{ $num[1] }}" data-sep="{{ $num[3] ?? '' }}" data-digits="{{ strlen($num[4] ?? '') }}" data-suffix="{{ $num[5] ?? '' }}" aria-hidden="true"></span><span class="value">{{ $card->title }}</span>@else{{ $card->title }}@endif</h3>
					@endif
					@if($card->text ?? null)
						<div class="cms-text">@markdown($card->text)</div>
					@endif
				</div>
			</div>
		@endforeach
	@endforeach
</div>
