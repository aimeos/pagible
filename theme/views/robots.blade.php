@if($content = implode(', ', array_filter([$data->index ?? null, $data->follow ?? null])))
<meta name="robots" content="{{ $content }}" />
@endif
