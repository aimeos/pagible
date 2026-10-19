@foreach($items as $item)
	<div id="{{ cmsattr($item->id ?? '') }}" class="{{ cmsattr(cmsref($page, $item)->type ?? '') }}">
		<div class="container">
			@element($page, $item)
		</div>
	</div>
@endforeach
