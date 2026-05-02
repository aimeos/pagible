@pushOnce('js')
<link rel="preload" href="{{ cmstheme($page, 'questions.css') }}" as="style">
@endPushOnce

<h2 class="title">{{ cms($data, 'title') }}</h2>

<div class="faqs">
	@foreach(cms($data, 'items', []) as $item)
		<details>
			<summary>
				<h3>{{ @$item->title }}</h3>
				<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16"> <path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708"/> </svg>
			</summary>
			@markdown(@$item->text)
		</details>
	@endforeach
</div>

<script type="application/ld+json">{
	"@@context": "https://schema.org",
	"@@type": "FAQPage",
	"mainEntity": [
	@foreach(cms($data, 'items', []) as $item)
		{
			"@@type": "Question",
			"name": {{ Js::from(@$item->title) }},
			"acceptedAnswer": {
				"@@type": "Answer",
				"text": {{ Js::from(strip_tags(Str::markdown(@$item->text ?? ''))) }}
			}
		}
		@if(!$loop->last),@endif
	@endforeach
	]
}</script>
