@props(['page', 'nav'])
@if(($variants = $nav->variants())->count() > 1)
	<li class="languages">
		<details class="dropdown is-menu">
			<summary aria-label="{{ __('Language') }}">{{ strtoupper((string) cms($page, 'lang')) }}</summary>
			<ul class="align">
				@foreach($variants as $variant)
					<li>
						<a href="{{ $variant->url }}" hreflang="{{ $variant->lang }}" lang="{{ $variant->lang }}" class="{{ $variant->lang === cms($page, 'lang') ? 'active' : '' }}">
							{{ class_exists(\Locale::class) ? \Locale::getDisplayLanguage($variant->lang, $variant->lang) : $variant->lang }}
						</a>
					</li>
				@endforeach
			</ul>
		</details>
	</li>
@endif
