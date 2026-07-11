<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar', 'az', 'dv', 'fa', 'he', 'ku', 'ur']) ? 'rtl' : 'ltr' }}">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
		<meta name="csrf-token" content="{{ csrf_token() }}">

    <title>PagibleAI CMS Admin</title>

    {{-- Import map for plugin modules; must precede the module script below so it governs every import. --}}
    @if($imports = cmsimports('vendor/cms/admin/.vite/manifest.json'))
      <script type="importmap" nonce="{{ $nonce }}">
        { "imports": {!! json_encode($imports, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!} }
      </script>
    @endif

    @if($index = cmsadmin('vendor/cms/admin/.vite/manifest.json')['index.html'] ?? null)
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
      data-urlchat="{{ Route::has('cms.chat') ? route('cms.chat') : '' }}"
      data-urladmin="{{ route('cms.admin', [], false) }}"
      data-urlproxy="{{ route('cms.proxy', ['url' => '_url_']) }}"
      data-urlpage="{{ Route::has('cms.page') ? route('cms.page', ['path' => '_path_'] + (config('cms.multidomain') ? ['domain' => '_domain_'] : [])) : '' }}"
      data-urlfile="{{ \Illuminate\Support\Facades\Storage::disk( config( 'cms.disk', 'public' ) )->url( '' ) }}"
      data-theme="{{ json_encode( config( 'cms.admin.colors', [] ) ) }}"
      data-locales="{{ json_encode( config( 'cms.locales', ['en'] ) ) }}"
      data-multidomain="{{ (int) config('cms.multidomain', false) }}"
      data-plugins="{{ json_encode(\Aimeos\Cms\Plugin::all()) }}"
      @if(config('cms.broadcast'))
        data-reverb="{{ json_encode([
            'key' => config('reverb.apps.apps.0.key', ''),
            'host' => config('reverb.apps.apps.0.options.host', config('reverb.servers.reverb.host', '127.0.0.1')),
            'port' => config('reverb.apps.apps.0.options.port', config('reverb.servers.reverb.port', 8080)),
            'scheme' => config('reverb.apps.apps.0.options.scheme', 'http'),
            'tenant' => \Aimeos\Cms\Tenancy::value(),
        ]) }}"
      @endif
    ></div>
  </body>
</html>
