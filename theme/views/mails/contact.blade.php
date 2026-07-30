@component('mail::message')
# Contact message

**Name:** {{ $data['name'] }}
**Email:** {{ $data['email'] }}
@if($data['source'] ?? null)
**{{ __('Source page') }}:** {{ $data['source'] }}
@endif

---

{{ $data['message'] }}

@endcomponent
