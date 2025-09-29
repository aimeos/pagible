@if(@$data->title)
	<h2>{{ $data->title }}</h2>
@endif
<div class="table">
	@foreach(@$data->table ?? [] as $rowidx => $row)
		<div class="row">
			@foreach((array) $row as $colidx => $col)
				<div class="col {{
					$colidx === 0 && in_array(@$data->header, ['col', 'row+col']) ||
					$rowidx === 0 && in_array(@$data->header, ['row', 'row+col']) ? 'th' : 'td'
				}}">
					@markdown((string) $col)
				</div>
			@endforeach
		</div>
	@endforeach
</div>
