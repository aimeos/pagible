@pushOnce('foot:grid')
<link href="{{ cmstheme($page, 'pico.grid.min.css') }}" rel="preload" as="style">
@endPushOnce

@pushOnce('foot')
<link href="{{ cmstheme($page, 'contact.css') }}" rel="preload" as="style">
<script defer src="{{ cmstheme($page, 'contact.js') }}"></script>
@endPushOnce

<h2 class="title">{{ $data->title ?? '' }}</h2>

<form action="{{ route('cms.api.contact') }}" method="POST">
    @csrf

    <div class="grid">
        <div>
            <label for="name">{{ __('Name') }}</label>
            <input id="name" type="text" name="name" placeholder="{{ __('Your name') }}" required />
        </div>
        <div>
            <label for="email">{{ __('E-Mail') }}</label>
            <input id="email" type="email" name="email" placeholder="{{ __('Your e-mail address') }}" required />
        </div>
    </div>
    <div>
        <label for="message">{{ __('Message') }}</label>
        <textarea id="message" name="message" placeholder="{{ __('Your message') }}" required rows="6"></textarea>
    </div>
    <div class="errors"></div>
    <div class="grid">
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

<script type="application/ld+json">{
    "@@context": "https://schema.org",
    "@@type": "ContactPage",
    "name": {{ Js::from($data->title ?? cms($page, 'title')) }}
}</script>
