@pushOnce('foot')
<link href="{{ cmstheme($page, 'testimonial.css') }}" rel="preload" as="style">
@endPushOnce

@if($data->title ?? null)
	<h2 class="title">{{ $data->title }}</h2>
@endif

<div class="testimonial-list">
	@foreach(cms($data, 'items', []) as $item)
		<figure class="testimonial-item">
			<blockquote class="cms-text">@text($item->text ?? '')</blockquote>
			<figcaption>
				@if($file = cms($files, $item->file?->id ?? null))
					@include('cms::pic', ['file' => $file, 'class' => 'avatar', 'sizes' => '4rem'])
				@endif
				<span class="person">
					@if($item->name ?? null)
						<span class="name">{{ $item->name }}</span>
					@endif
					@if($item->role ?? null)
						<span class="role">{{ $item->role }}</span>
					@endif
				</span>
			</figcaption>
		</figure>
	@endforeach
</div>
