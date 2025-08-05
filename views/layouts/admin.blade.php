<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar', 'az', 'dv', 'fa', 'he', 'ku', 'ur']) ? 'rtl' : 'ltr' }}">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
		<meta name="csrf-token" content="{{ csrf_token() }}">

    <title>PagibleAI CMS Admin</title>

    <script type="module" crossorigin src="{{ cmsasset('vendor/cms/admin/index.js') }}"></script>
    <link rel="stylesheet" crossorigin href="{{ cmsasset('vendor/cms/admin/index.css') }}">

    <script nonce="{{ $nonce }}">
      window.__APP_CONFIG__ = {
        email: {!! json_encode(env('CMS_ADMIN_EMAIL', '')) !!},
        password: {!! json_encode(env('CMS_ADMIN_PASSWORD', '')) !!}
      }
    </script>
  </head>
  <body>
    <div id="app"
      data-urlgraphql="{{ route('graphql') }}"
      data-urladmin="{{ route('cms.admin', [], false) }}"
      data-urlproxy="{{ route('cms.proxy', ['url' => '_url_']) }}"
      data-urlpage="{{ route('cms.page', ['path' => '_path_'] + (config('cms.multidomain') ? ['domain' => '_domain_'] : [])) }}"
      data-urlfile="{{ \Illuminate\Support\Facades\Storage::disk( config( 'cms.disk', 'public' ) )->url( '' ) }}"
      data-config="{{ json_encode( config( 'cms.config', new \stdClass() ) ) }}"
      data-schemas="{{ json_encode( config( 'cms.schemas', new \stdClass() ) ) }}"
      data-multidomain="{{ (int) config('cms.multidomain', false) }}"
    ></div>
  </body>
</html>
