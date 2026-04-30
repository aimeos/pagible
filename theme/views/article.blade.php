@pushOnce('js')
<link rel="preload" href="{{ cmstheme($page, 'article.css') }}" as="style">
@endPushOnce

<h1 class="title">{{ cms($page, 'title') }}</h1>

@if($file = cms($files, @$data->file?->id))
	@include('cms::pic', ['file' => $file, 'main' => true, 'class' => 'cover', 'sizes' => '(max-width: 960px) 100vw, 960px'])
@endif

<div class="text">
	@markdown(@$data->text)
</div>
