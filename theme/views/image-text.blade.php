@pushOnce('foot', 'css:image')
<link href="{{ cmstheme($page, 'image.css') }}" rel="preload" as="style">
@endPushOnce
@pushOnce('foot')
<link href="{{ cmstheme($page, 'image-text.css') }}" rel="preload" as="style">
@endPushOnce

<div class="{{ $data->position ?? 'start' }} r{{ $data->ratio ?? '1-3' }}">
	@if($file = cms($files, $data->file?->id ?? null))
		@include('cms::pic', ['file' => $file, 'class' => 'image', 'sizes' => match($data->ratio ?? '1-3') {
			'1-1' => '(max-width: 480px) 100vw, 50vw',
			'1-2' => '(max-width: 480px) 100vw, 33vw',
			default => '(max-width: 480px) 100vw, 25vw',
		}])
	@endif

	<div class="text">
		@markdown($data->text ?? '')
	</div>
</div>
