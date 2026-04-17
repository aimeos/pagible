<ol>
	@foreach($items as $item)
		<li>
			<a href="#{{ e($item['id']) }}">{{ $item['title'] }}</a>
			@if(!empty($item['children']))
				@include('cms::toc-list', ['items' => $item['children']])
			@endif
		</li>
	@endforeach
</ol>
