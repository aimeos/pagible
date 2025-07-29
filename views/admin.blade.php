<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar', 'az', 'dv', 'fa', 'he', 'ku', 'ur']) ? 'rtl' : 'ltr' }}">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
		<meta name="csrf-token" content="{{ csrf_token() }}">

		@if( !config('app.debug') )
			<meta http-equiv="Content-Security-Policy" content="base-uri 'self'; default-src 'self'; style-src 'unsafe-inline' 'self'; img-src 'self' data:; }}">
		@endif

    <title>PagibleAI CMS Admin</title>

    <script type="module" crossorigin src="{{ cmsasset('vendor/cms/admin/index.js') }}"></script>
    <link rel="stylesheet" crossorigin href="{{ cmsasset('vendor/cms/admin/index.css') }}">

    <script>
      window.__APP_CONFIG__ = {
        email: env('CMS_ADMIN_EMAIL', ''),
        password: env('CMS_ADMIN_PASSWORD', '')
      }
    </script>
  </head>
  <body>
    <div id="app"
      data-urlgraphql="{{ route('graphql') }}"
      data-urladmin="{{ route('cms.admin', [], false) }}"
      data-urlproxy="{{ route('cms.proxy', ['url' => ':url']) }}"
      data-urlpage="{{ route('cms.page', ['path' => ':path']) }}"
      data-urlfile="{{ \Illuminate\Support\Facades\Storage::disk( config( 'cms.disk', 'public' ) )->url( '' ) }}"
      data-config="{{ json_encode( config( 'cms.config', new \stdClass() ) ) }}"
      data-schemas="{{ json_encode( config( 'cms.schemas', new \stdClass() ) ) }}"
    ></div>
  </body>
</html>
