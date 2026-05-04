@pushOnce('js')
<link href="{{ cmstheme($page, 'code.css') }}" rel="stylesheet">
@endPushOnce

<pre><code class="language-{{ @$data->language?->value }}" dir="ltr">{{ @$data->text }}</code></pre>
