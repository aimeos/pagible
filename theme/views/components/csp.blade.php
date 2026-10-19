@props(['page', 'style' => ''])
@if(!config('app.debug'))
	<meta http-equiv="Content-Security-Policy" content="
		base-uri 'self';
		default-src 'self';
		frame-src 'self' {{ config('cms.theme.csp.frame-src') }};
		connect-src 'self' {{ config('cms.theme.csp.connect-src') }};
		img-src 'self' data: blob: {{ config('cms.theme.csp.media-src') }};
		media-src 'self' data: blob: {{ config('cms.theme.csp.media-src') }};
		style-src 'self' {{ config('cms.theme.csp.style-src') }} {!! \Aimeos\Cms\Theme::hashes($page, 'config.styles.data.text') !!} {!! \Aimeos\Cms\Theme::hash((string) $style) !!};
		script-src 'self' {{ config('cms.theme.csp.script-src') }} {!! \Aimeos\Cms\Theme::hashes($page, 'config.javascript.data.text') !!};
		font-src 'self';
	">
@endif
