@component('mail::message')
# Contact message

**Name:** {{ $data['name'] }}
**Email:** {{ $data['email'] }}

---

{{ $data['message'] }}

@endcomponent
