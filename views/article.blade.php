@pushOnce('css')
<link href="{{ cmsasset('vendor/cms/theme/article.css') }}" rel="stylesheet">
@endPushOnce

@if($file = cms($files, @$data->file?->id))
	@include('cms::pic', ['file' => $file, 'main' => true, 'class' => 'cover', 'sizes' => '(max-width: 960px) 100vw, 960px'])
@endif

<h1 class="title">{{ cms($page, 'title') }}</h1>

<div class="text">
	@markdown(@$data->text)
</div>
