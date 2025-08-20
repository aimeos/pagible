@if($file = cms($files, @$data->file?->id))
	@include('cms::pic', ['file' => $file, 'class' => 'image ' . (@$data->position ?? 'auto'), 'sizes' => '(max-width: 480px) 100vw, 240px'])
@endif

<div class="text">
	@markdown(@$data->text)
</div>
