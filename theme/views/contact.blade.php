@pushOnce('foot:grid')
<link href="{{ cmstheme($page, 'pico.grid.min.css') }}" rel="preload" as="style">
@endPushOnce

@pushOnce('foot')
<link href="{{ cmstheme($page, 'contact.css') }}" rel="preload" as="style">
<script defer src="{{ cmstheme($page, 'contact.js') }}"></script>
@endPushOnce

<h2 class="title">{{ $data->title ?? '' }}</h2>

<form action="{{ cmsroute('cms.api.contact') }}" method="POST" aria-describedby="contact-errors-{{ $data->id ?? cms($page, 'id') }}" toolname="contact" tooldescription="{{ __('Send a message to the site owner through the contact form') }}">
    <input type="hidden" name="_token" value="">
    @if($source ?? null)
        <input type="hidden" name="source" value="{{ $source }}">
    @endif

    <div class="grid">
        <div>
            <label for="name-{{ $data->id ?? cms($page, 'id') }}">{{ __('Name') }}</label>
            <input id="name-{{ $data->id ?? cms($page, 'id') }}" type="text" name="name" placeholder="{{ __('Your name') }}" required toolparamdescription="{{ __('Full name of the person sending the message') }}" />
        </div>
        <div>
            <label for="email-{{ $data->id ?? cms($page, 'id') }}">{{ __('E-Mail') }}</label>
            <input id="email-{{ $data->id ?? cms($page, 'id') }}" type="email" name="email" placeholder="{{ __('Your e-mail address') }}" required toolparamdescription="{{ __('E-mail address of the sender for the reply') }}" />
        </div>
    </div>
    <div>
        <label for="message-{{ $data->id ?? cms($page, 'id') }}">{{ __('Message') }}</label>
        <textarea id="message-{{ $data->id ?? cms($page, 'id') }}" name="message" placeholder="{{ __('Your message') }}" required rows="6" toolparamdescription="{{ __('Message text to send to the site owner') }}"></textarea>
    </div>
    <div id="contact-errors-{{ $data->id ?? cms($page, 'id') }}" class="errors" role="alert" aria-live="polite" tabindex="-1"></div>
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
