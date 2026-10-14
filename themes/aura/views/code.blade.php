@once('prism')
    @pushOnce('head')
    <link href="{{ cmstheme($page, 'prism.css') }}" rel="stylesheet">
    @endPushOnce

    @pushOnce('foot')
    <script defer src="{{ cmstheme($page, 'prism.js') }}"></script>
    @endPushOnce
@endOnce

<div class="code-box">
    <div class="code-head">
        <span class="code-lang">{{ $data->language?->value ?? '' }}</span>
        <button type="button" class="code-copy" data-copied="{{ __('Copied') }}">{{ __('Copy code') }}</button>
    </div>
    <pre><code class="language-{{ $data->language?->value ?? '' }}" dir="ltr">{{ $data->text ?? '' }}</code></pre>
</div>
