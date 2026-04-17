@if(!empty($action))
	<nav class="toc">
		@if(@$data->title)
			<p>{{ $data->title }}</p>
		@endif

		@include('cms::toc-list', ['items' => $action])
	</nav>
@endif
