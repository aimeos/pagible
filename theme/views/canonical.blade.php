@if(($url = cmslink($data->url ?? '')) && ($pageNo = (int) request()->attributes->get('cms.pagination', 1)) > 1)
<link rel="canonical" href="{{ url()->query($url, ['p' => $pageNo]) }}" />
@else
<link rel="canonical" href="{{ $url }}" />
@endif
