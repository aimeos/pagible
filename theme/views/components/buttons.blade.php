@props(['buttons' => []])
@if($buttons)
	<div {{ $attributes->merge(['class' => 'actions']) }}>
		@foreach($buttons as $button)
			@if($url = cmslink($button->url ?? null))
				<a class="btn" href="{{ $url }}" rel="{{ $button->{'url-rel'} ?? '' }}">{{ $button->label ?? '' }}</a>
			@endif
		@endforeach
	</div>
@endif
