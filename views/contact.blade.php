@pushOnce('css')
<link type="text/css" rel="stylesheet" href="{{ cmsasset('vendor/cms/theme/contact.css') }}">
@endPushOnce

@pushOnce('js')
<script defer src="{{ cmsasset('vendor/cms/theme/contact.js') }}"></script>
@endPushOnce

<h2 class="title">{{ @$data->title }}</h2>

<form action="{{ route('cms.api.contact') }}" method="POST">
    @csrf

    <input type="text" name="name" placeholder="{{ __('Your name') }}" required />
    <input type="email" name="email" placeholder="{{ __('Your e-mail address') }}" required />
    <textarea name="message" placeholder="{{ __('Your message') }}" required rows="6"></textarea>

    @if(!app()->environment('local') && config('services.hcaptcha.sitekey'))
        <div class="h-captcha" data-sitekey="{{ config('services.hcaptcha.sitekey') }}"></div>
        <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
    @endif

    <div class="errors"></div>

    <button type="submit" class="btn">
        <span class="send">{{ __('Send message') }}</span>
        <span class="sending hidden"aria-busy="true">{{ __('Message will be sent') }}</span>
        <span class="success hidden">{{ __('Successfully sent') }}</span>
        <span class="failure hidden">{{ __('Error sending e-mail') }}</span>
    </button>
</form>
