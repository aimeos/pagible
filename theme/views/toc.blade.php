@if(!empty($action))
	<nav class="toc">
		@if(@$data->title)
			<h2>{{ $data->title }}</h2>
		@endif

		@include('cms::toc-list', ['items' => $action])
	</nav>
@endif
