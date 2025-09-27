@pushOnce('css')
<link href="{{ cmsasset('vendor/cms/theme/faq.css') }}" rel="stylesheet">
@endPushOnce

<h1 class="title">{{ cms($data, 'title') }}</h1>

<div class="faqs">
	@foreach(cms($data, 'items', []) as $item)
		<details>
			<summary>{{ @$item->title }}</summary>
			@markdown(@$item->text)
		</details>
		<hr />
	@endforeach
</div>
