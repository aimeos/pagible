@pushOnce('css')
<link type="text/css" rel="stylesheet" href="{{ cmsasset('vendor/cms/theme/prism.css') }}">
<link type="text/css" rel="stylesheet" href="{{ cmsasset('vendor/cms/theme/code.css') }}">
@endPushOnce

@pushOnce('js')
<script defer src="{{ cmsasset('vendor/cms/theme/prism.js') }}"></script>
<script defer src="{{ cmsasset('vendor/cms/theme/code.js') }}"></script>
@endPushOnce

<div class="code-box">
    <button type="button" class="code-copy" aria-label="Copy code">
        <svg class="copy-code" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-copy" viewBox="0 0 16 16">
            <path fill-rule="evenodd" d="M4 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zm2-1a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1zM2 5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-1h1v1a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h1v1z"/>
        </svg>
        <svg class="copy-check hidden" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check-lg" viewBox="0 0 16 16">
            <path d="M12.736 3.97a.733.733 0 0 1 1.047 0c.286.289.29.756.01 1.05L7.88 12.01a.733.733 0 0 1-1.065.02L3.217 8.384a.757.757 0 0 1 0-1.06.733.733 0 0 1 1.047 0l3.052 3.093 5.4-6.425z"/>
        </svg>
    </button>

    <pre><code class="language-{{ @$data->language?->value }}" dir="ltr">{{ @$data->text }}</code></pre>
</div>
