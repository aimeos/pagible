@props(['page', 'nav', 'dropdown' => true, 'parent' => false, 'highlight' => null, 'linkClass' => ''])
@foreach($nav->items() as $item)
	<li @if($highlight && $item->path === $highlight) class="{{ $highlight }}" @endif>
		@if($dropdown && $item->children->count())
			<details class="dropdown is-menu">
				<summary @if($parent) class="{{ $page->isSelfOrDescendantOf($item) ? 'active' : '' }}" @endif>{{ cms($item, 'name') }}</summary>
				<ul class="align">
					@if($parent)
						<li>
							<a href="{{ cmsroute($item) }}" class="{{ trim(($page->id === $item->id ? 'active ' : '') . $linkClass) }}"@if($page->id === $item->id) aria-current="page"@endif>
								{{ cms($item, 'name') }}
							</a>
						</li>
					@endif
					@foreach($item->children as $subItem)
						<li>
							<a href="{{ cmsroute($subItem) }}" class="{{ trim(($page->isSelfOrDescendantOf($subItem) ? 'active ' : '') . $linkClass) }}"@if($page->id === $subItem->id) aria-current="page"@endif>
								{{ cms($subItem, 'name') }}
							</a>
						</li>
					@endforeach
				</ul>
			</details>
		@else
			<a href="{{ cmsroute($item) }}" class="{{ trim(($page->isSelfOrDescendantOf($item) ? 'active ' : '') . $linkClass) }}"@if($page->id === $item->id) aria-current="page"@endif>
				{{ cms($item, 'name') }}
			</a>
		@endif
	</li>
@endforeach
