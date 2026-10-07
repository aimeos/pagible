@pushOnce('foot')
<link href="{{ cmstheme($page, 'pico.grid.min.css') }}" rel="preload" as="style">
@endPushOnce

@pushOnce('foot')
<link href="{{ cmstheme($page, 'contact.css') }}" rel="preload" as="style">
<script defer src="{{ cmstheme($page, 'contact.js') }}"></script>
@endPushOnce

<h2 class="title">{{ $data->title ?? '' }}</h2>

@if($data->description ?? null)
    <div class="cms-text">@markdown($data->description)</div>
@endif

@php
    $value = fn($field) => is_object($field) ? ($field->value ?? null) : $field;
    $order = null;
    $types = [];

    if(is_array($data->inputs ?? null)) {
        $inputs = array_map(fn($input) => (object) $input, array_values($data->inputs));
        $order = array_map(fn($input) => $value($input->field ?? null), $inputs);
        $mandatory = array_values(array_map(fn($input) => $value($input->field ?? null), array_filter($inputs, fn($input) => !empty($input->required))));
        $optional = $order;

        foreach($inputs as $input) {
            $field = $value($input->field ?? null);
            $type = $value($input->input ?? null);

            if(is_string($field) && !isset($types[$field]) && in_array($type, ['select', 'textarea'], true)) {
                $types[$field] = $type === 'select'
                    ? array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($input->options ?? '')) ?: [])))
                    : $type;
            }
        }
    } else {
        $mandatory = $data->mandatory ?? $data->fields ?? null;
        $mandatory = is_array($mandatory) ? array_map($value, $mandatory) : $mandatory;
        $optional = array_map($value, (array) ($data->optional ?? []));
    }

    $schema = \Aimeos\Cms\Requests\ContactRequest::schema($mandatory, $optional, $types, (int) ($data->attachments ?? 0));
    $sets = json_decode($schema, true);
    $fields = array_values(array_unique(array_filter(
        $order ?? [...$sets['mandatory'], ...$sets['optional']],
        fn($field) => in_array($field, $sets['mandatory'], true) || in_array($field, $sets['optional'], true)
    )));
    $placeholders = [
        'name' => __('Your name'),
        'email' => __('Your e-mail address'),
    ];
    $descriptions = [
        'name' => __('Full name of the person sending the message'),
        'email' => __('E-mail address of the sender for the reply'),
    ];
    $htmltypes = ['email' => 'email', 'telephone' => 'tel'];
    $autocomplete = ['name' => 'name', 'company' => 'organization', 'email' => 'email', 'telephone' => 'tel'];
    $formid = $data->id ?? cms($page, 'id');
@endphp

<form action="{{ cmsroute('cms.api.contact') }}" method="POST" enctype="multipart/form-data" aria-describedby="contact-errors-{{ $formid }}"
    @if($sets['files'] ?? 0) data-toolarge="{{ __(':attribute: The files are too large.', ['attribute' => __('Attachments')]) }}" @endif
    toolname="contact" tooldescription="{{ __('Send a message to the site owner through the contact form') }}">
    <input type="hidden" name="_token" value="">
    <input type="hidden" name="schema" value="{{ $schema }}">
    <input type="hidden" name="locale" value="{{ app()->getLocale() }}">
    <input type="hidden" name="signature" value="{{ \Aimeos\Cms\Requests\ContactRequest::signature($schema) }}">
    @if($source ?? null)
        <input type="hidden" name="source" value="{{ $source }}">
    @endif

    @foreach(array_chunk($fields, 2) as $row)
        <div class="grid">
            @foreach($row as $field)
                @php
                    $label = __($field === 'email' ? 'E-Mail' : \Illuminate\Support\Str::headline($field));
                    $name = \Aimeos\Cms\Requests\ContactRequest::key($field);
                    $required = in_array($field, $sets['mandatory'], true);
                    $type = $sets['types'][$field] ?? null;
                @endphp
                <div>
                    <label for="{{ $name }}-{{ $formid }}">{{ $label }}</label>
                    @if(is_array($type))
                        <select id="{{ $name }}-{{ $formid }}" name="{{ $name }}"
                            @if($required) required @endif
                            toolparamdescription="{{ $label }}">
                            <option value="">{{ __('Please select') }}</option>
                            @foreach($type as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                    @elseif($type === 'textarea')
                        <textarea id="{{ $name }}-{{ $formid }}" name="{{ $name }}" placeholder="{{ $label }}"
                            maxlength="5000" rows="4"
                            @if($required) required @endif
                            toolparamdescription="{{ $label }}"></textarea>
                    @else
                        <input id="{{ $name }}-{{ $formid }}" type="{{ $htmltypes[$field] ?? 'text' }}"
                            name="{{ $name }}" placeholder="{{ $placeholders[$field] ?? $label }}"
                            maxlength="{{ $field === 'email' ? 254 : 255 }}"
                            @if(isset($autocomplete[$field])) autocomplete="{{ $autocomplete[$field] }}" @endif
                            @if($required) required @endif
                            toolparamdescription="{{ $descriptions[$field] ?? $label }}" />
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach
    <div>
        <label for="message-{{ $formid }}">{{ __('Message') }}</label>
        <textarea id="message-{{ $formid }}" name="message" placeholder="{{ __('Your message') }}" required rows="6"
            toolparamdescription="{{ __('Message text to send to the site owner') }}"></textarea>
    </div>
    @if($sets['files'] ?? 0)
        <div class="files">
            <label for="files-{{ $formid }}">{{ __('Attachments') }}</label>
            <input id="files-{{ $formid }}" type="file" name="files[]" @if($sets['files'] > 1) multiple @endif
                accept=".{{ implode(',.', \Aimeos\Cms\Requests\ContactRequest::FILE_EXTENSIONS) }}"
                aria-describedby="files-hint-{{ $formid }}"
                toolparamdescription="{{ __('Attachments') }}" />
            <small id="files-hint-{{ $formid }}">{{ __('Images or PDF files, max. :size MB each', ['size' => \Aimeos\Cms\Requests\ContactRequest::FILE_SIZE]) }}</small>
        </div>
    @endif
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
