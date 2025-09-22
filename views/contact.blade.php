@pushOnce('css')
<link href="{{ cmsasset('vendor/cms/theme/contact.css') }}" rel="stylesheet">
@endPushOnce

@pushOnce('js')
<script defer src="{{ cmsasset('vendor/cms/theme/contact.js') }}"></script>
@endPushOnce

<h2 class="title">{{ @$data->title }}</h2>

<form action="{{ route('cms.api.contact') }}" method="POST">
    @csrf

    <div class="row">
        <div class="col">
            <label for="name">{{ __('Name') }}</label>
            <input id="name" type="text" name="name" placeholder="{{ __('Your name') }}" required />
        </div>
        <div class="col">
            <label for="email">{{ __('E-Mail') }}</label>
            <input id="email" type="email" name="email" placeholder="{{ __('Your e-mail address') }}" required />
        </div>
    </div>
    <div class="col">
        <label for="message">{{ __('Message') }}</label>
        <textarea id="message" name="message" placeholder="{{ __('Your message') }}" required rows="6"></textarea>
    </div>

    @if(!app()->environment('local') && config('services.hcaptcha.sitekey'))
        <div class="h-captcha" data-sitekey="{{ config('services.hcaptcha.sitekey') }}" data-theme="dark"></div>
    @endif

    <div class="errors"></div>

    <button type="submit" class="btn">
        <span class="send">{{ __('Send message') }}</span>
        <span class="sending hidden"aria-busy="true">{{ __('Message will be sent') }}</span>
        <span class="success hidden">{{ __('Successfully sent') }}</span>
        <span class="failure hidden">{{ __('Error sending e-mail') }}</span>
    </button>
</form>
