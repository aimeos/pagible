@pushOnce('foot')
<link href="{{ cmstheme($page, 'timeline.css') }}" rel="preload" as="style">
@endPushOnce

@if($data->title ?? null)
	<h2>{{ $data->title }}</h2>
@endif

<ol class="steps layout-{{ $data->layout ?? 'vertical' }}">
	@foreach($data->items ?? [] as $item)
		<li class="step">
			<span class="marker" aria-hidden="true"></span>
			@if($item->label ?? null)
				<p class="label">{{ $item->label }}</p>
			@endif
			<h3 class="title">{{ $item->title ?? '' }}</h3>
			@if($item->text ?? null)
				<div class="cms-text">@markdown($item->text)</div>
			@endif
		</li>
	@endforeach
</ol>
