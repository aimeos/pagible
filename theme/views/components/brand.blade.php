@props(['page', 'nav', 'name' => null, 'alternative' => false])
<a href="{{ cmsroute($nav->ancestors()->first() ?? $page) }}" title="{{ $name ??= cmsconfig($page, 'website.data.title', cms($page->ancestorsAndSelf->first() ?? $page, 'name')) }}" aria-label="{{ $name }}"@if($attributes->isNotEmpty()) {{ $attributes }}@endif>
	@if(($navItem = $page->ancestorsAndSelf->reverse()->first(fn($item) => ($alternative ? cms($item, 'config.logo-alternative.data.file.id') : null) ?: cms($item, 'config.logo.data.file.id'))) && ($fileId = ($alternative ? cms($navItem, 'config.logo-alternative.data.file.id') : null) ?: cms($navItem, 'config.logo.data.file.id')))
		<img src="{{ cmsasset($navItem, cmsfile($navItem, $fileId)) }}" alt="{{ $name }}">
	@else
		{{ $name }}
	@endif
</a>
