@props(['page', 'nav'])
@if($nav->ancestors()->count() > 1)
	<nav class="breadcrumb" aria-label="{{ __('Breadcrumb navigation') }}">
		<ul>
			@foreach($nav->ancestors()->skip(1) as $item)
				<li>
					<a role="button" href="{{ cmsroute($item) }}">{{ cms($item, 'name') }}</a>
				</li>
			@endforeach
			<li>{{ cms($page, 'name') }}</li>
		</ul>
	</nav>
@endif
