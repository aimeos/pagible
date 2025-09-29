<table class="table">
	@foreach(@$data->table ?? [] as $rowidx => $row)
		<tr>
			@foreach((array) $row as $colidx => $col)
				@if($rowidx === 0 && @$data->header === 'row'
					|| $colidx === 0 && @$data->header === 'col'
					|| ($rowidx === 0 || $colidx === 0) && @$data->header === 'row+col')

					<th>
				@else
					<td>
				@endif

				@markdown((string) $col)

				@if($rowidx === 0 && @$data->header === 'row'
					|| $colidx === 0 && @$data->header === 'col'
					|| ($rowidx === 0 || $colidx === 0) && @$data->header === 'row+col')
					</th>
				@else
					</td>
				@endif
			@endforeach
		</tr>
	@endforeach
	@if(@$data->title)
		<caption>{{ $data->title }}</caption>
	@endif
</table>
