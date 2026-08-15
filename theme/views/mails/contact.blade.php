@component('mail::message')
# Contact message

@foreach($data['fields'] as $field)
@if($field['required'] || (($field['value'] ?? null) !== null && $field['value'] !== ''))
**{{ __($field['name'] === 'email' ? 'E-Mail' : \Illuminate\Support\Str::headline($field['name'])) }}:** {{ $field['value'] }}
@endif
@endforeach
@if($data['source'] ?? null)
**{{ __('Source page') }}:** {{ $data['source'] }}
@endif

---

{{ $data['message'] }}

@endcomponent
