@props(['page', 'nav' => null, 'themeColor' => null])
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
@if($dark = \Aimeos\Cms\Theme::vars($page, 'theme-dark', defaults: true)['--pico-background-color'] ?? null)
	<meta name="theme-color" media="(prefers-color-scheme: light)" content="{{ \Aimeos\Cms\Theme::vars($page, 'theme', defaults: true)['--pico-background-color'] ?? '' }}">
	<meta name="theme-color" media="(prefers-color-scheme: dark)" content="{{ $dark }}">
@elseif($light = $themeColor ?? \Aimeos\Cms\Theme::vars($page, 'theme', defaults: true)['--pico-background-color'] ?? null)
	<meta name="theme-color" content="{{ $light }}">
@endif
@auth
	<meta name="csrf-token" content="{{ csrf_token() }}">
@endauth
@if($style = \Aimeos\Cms\Theme::style($page))
	@prepend('head')
		<style>{!! $style !!}</style>
	@endprepend
@endif
<x-cms::csp :page="$page" :style="$style" />

<title>{{ cms($page, 'title') }}</title>

@unless(collect(cms($page, 'meta', []))->contains('type', 'canonical'))
	@include('cms::canonical', ['data' => (object) ['url' => cmsroute($page)]])
@endunless

@foreach(cms($page, 'meta', []) as $item)
	@element($page, $item)
@endforeach

@foreach($page->ancestorsAndSelf->reverse() as $navItem)
	@if(($fileId = cms($navItem, 'config.icon.data.file.id')) && ($file = cmsfile($navItem, $fileId)))
		<link rel="icon" type="{{ $file->mime }}" href="{{ cmsasset($navItem, $file) }}">
		@break
	@endif
@endforeach
