<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar', 'az', 'dv', 'fa', 'he', 'ku', 'ur']) ? 'rtl' : 'ltr' }}">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
		<meta name="csrf-token" content="{{ csrf_token() }}">

    <title>PagibleAI CMS Admin</title>

    @if($index = cmsadmin('vendor/cms/admin/.vite/manifest.json'))
      <script type="module" crossorigin src="{{ asset('vendor/cms/admin/' . ($index['file'] ?? '')) }}"></script>
      @foreach($index['css'] ?? [] as $file)
        <link rel="stylesheet" crossorigin href="{{ asset('vendor/cms/admin/' . $file) }}">
      @endforeach
    @endif

    @if(env('CMS_ADMIN_EMAIL'))
      <script nonce="{{ $nonce }}">
        window.__APP_CONFIG__ = {
          email: {!! json_encode(env('CMS_ADMIN_EMAIL', '')) !!},
          password: {!! json_encode(env('CMS_ADMIN_PASSWORD', '')) !!}
        }
      </script>
    @endif
  </head>
  <body>
    <div id="app"
      data-urlgraphql="{{ route('graphql') }}"
      data-urladmin="{{ route('cms.admin', [], false) }}"
      data-urlproxy="{{ route('cms.proxy', ['url' => '_url_']) }}"
      data-urlpage="{{ Route::has('cms.page') ? route('cms.page', ['path' => '_path_'] + (config('cms.multidomain') ? ['domain' => '_domain_'] : [])) : '' }}"
      data-urlfile="{{ \Illuminate\Support\Facades\Storage::disk( config( 'cms.disk', 'public' ) )->url( '' ) }}"
      data-theme="{{ json_encode( config( 'cms.admin.colors', [] ) ) }}"
      data-locales="{{ json_encode( config( 'cms.locales', ['en'] ) ) }}"
      data-multidomain="{{ (int) config('cms.multidomain', false) }}"
      @if(config('cms.broadcast'))
        data-reverb="{{ json_encode([
            'key' => config('reverb.apps.0.key', ''),
            'host' => config('reverb.servers.reverb.host', '127.0.0.1'),
            'port' => config('reverb.servers.reverb.port', 8080),
            'scheme' => config('reverb.servers.reverb.hostname') ? 'https' : 'http',
        ]) }}"
      @endif
    ></div>
  </body>
</html>
