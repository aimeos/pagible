@pushOnce('css')
<link type="text/css" rel="stylesheet" href="{{ cmsasset('vendor/cms/theme/article.css') }}">
@endPushOnce

@if($file = cms($files, @$data->file?->id))
	@include('cms::pic', ['file' => $file, 'main' => true, 'class' => 'cover'])
@endif

<h1 class="title">{{ cms($page, 'title') }}</h1>

<div class="text">
	@markdown(@$data->text)
</div>
