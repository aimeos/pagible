@pushOnce('foot:grid')
<link href="{{ cmstheme($page, 'pico.grid.min.css') }}" rel="preload" as="style">
@endPushOnce

@pushOnce('foot')
<link href="{{ cmstheme($page, 'contact.css') }}" rel="preload" as="style">
<script defer src="{{ cmstheme($page, 'contact.js') }}"></script>
@endPushOnce

<h2 class="title">{{ $data->title ?? '' }}</h2>

@php
    $schema = \Aimeos\Cms\Requests\ContactRequest::schema(
        $data->mandatory ?? $data->fields ?? null,
        $data->optional ?? []
    );
    $sets = json_decode($schema, true);
    $fieldsets = [
        ['fields' => $sets['mandatory'], 'required' => true],
        ['fields' => $sets['optional'], 'required' => false],
    ];
    $placeholders = [
        'name' => __('Your name'),
        'email' => __('Your e-mail address'),
    ];
    $descriptions = [
        'name' => __('Full name of the person sending the message'),
        'email' => __('E-mail address of the sender for the reply'),
    ];
    $types = ['email' => 'email', 'telephone' => 'tel'];
    $autocomplete = ['name' => 'name', 'company' => 'organization', 'email' => 'email', 'telephone' => 'tel'];
    $formid = $data->id ?? cms($page, 'id');
@endphp

<form action="{{ route('cms.api.contact') }}" method="POST" aria-describedby="contact-errors-{{ $formid }}"
    toolname="contact" tooldescription="{{ __('Send a message to the site owner through the contact form') }}">
    <input type="hidden" name="_token" value="">
    <input type="hidden" name="schema" value="{{ $schema }}">
    <input type="hidden" name="signature" value="{{ \Aimeos\Cms\Requests\ContactRequest::signature($schema) }}">
    @if($source ?? null)
        <input type="hidden" name="source" value="{{ $source }}">
    @endif

    @foreach($fieldsets as $fieldset)
        @foreach(array_chunk($fieldset['fields'], 2) as $row)
            <div class="grid">
                @foreach($row as $field)
                    @php
                        $label = __($field === 'email' ? 'E-Mail' : \Illuminate\Support\Str::headline($field));
                        $name = \Aimeos\Cms\Requests\ContactRequest::key($field);
                    @endphp
                    <div>
                        <label for="{{ $name }}-{{ $formid }}">{{ $label }}</label>
                        <input id="{{ $name }}-{{ $formid }}" type="{{ $types[$field] ?? 'text' }}"
                            name="{{ $name }}" placeholder="{{ $placeholders[$field] ?? $label }}"
                            maxlength="{{ $field === 'email' ? 254 : 255 }}"
                            @if(isset($autocomplete[$field])) autocomplete="{{ $autocomplete[$field] }}" @endif
                            @if($fieldset['required']) required @endif
                            toolparamdescription="{{ $descriptions[$field] ?? $label }}" />
                    </div>
                @endforeach
            </div>
        @endforeach
    @endforeach
    <div>
        <label for="message-{{ $formid }}">{{ __('Message') }}</label>
        <textarea id="message-{{ $formid }}" name="message" placeholder="{{ __('Your message') }}" required rows="6"
            toolparamdescription="{{ __('Message text to send to the site owner') }}"></textarea>
    </div>
    <div id="contact-errors-{{ $formid }}" class="errors" role="alert" aria-live="polite" tabindex="-1"></div>
    <div class="submit">
        @if(!app()->environment('local') && config('services.hcaptcha.sitekey'))
            <div>
                <div class="h-captcha" data-sitekey="{{ config('services.hcaptcha.sitekey') }}"></div>
            </div>
        @endif
        <div>
            <button type="submit" class="btn">
                <span class="send">{{ __('Send message') }}</span>
                <span class="sending hidden" aria-busy="true">{{ __('Message will be sent') }}</span>
                <span class="success hidden">{{ __('Successfully sent') }}</span>
                <span class="failure hidden">{{ __('Error sending e-mail') }}</span>
            </button>
        </div>
    </div>
</form>

@if($jsonld ?? true)
    <script type="application/ld+json">{
        "@@context": "https://schema.org",
        "@@type": "ContactPage",
        "name": {!! cmsjson($data->title ?? cms($page, 'title')) !!}
    }</script>
@endif
