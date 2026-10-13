@if(str_contains($data->text ?? '', '[!'))
    <div class="cms-text">{!! preg_replace_callback('#<blockquote>\s*<p>\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\](?:\s*</p>\s*<p>|\s*)#', fn($m) => '<blockquote class="callout ' . strtolower($m[1]) . '"><p class="callout-title">' . e(['NOTE' => __('Note'), 'TIP' => __('Tip'), 'IMPORTANT' => __('Important'), 'WARNING' => __('Warning'), 'CAUTION' => __('Caution')][$m[1]]) . '</p><p>', trim(\Illuminate\Support\Str::markdown($data->text, ['html_input' => 'strip', 'allow_unsafe_links' => false, 'max_nesting_level' => 25, 'renderer' => ['block_separator' => '', 'inner_separator' => '']]))) !!}</div>
@else
    <div class="cms-text">@markdown($data->text ?? '')</div>
@endif
