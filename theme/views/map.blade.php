@php
	$location = $data->location ?? null;
	$latitude = is_numeric($location?->latitude ?? null) ? (float) $location->latitude : null;
	$longitude = is_numeric($location?->longitude ?? null) ? (float) $location->longitude : null;
	$zoom = is_numeric($location?->zoom ?? null) ? (int) $location->zoom : 15;
	$valid = $latitude !== null && $latitude >= -90 && $latitude <= 90
		&& $longitude !== null && $longitude >= -180 && $longitude <= 180;
	$zoom = max(1, min(19, $zoom));
	$hasCopy = !empty($data->title) || !empty($data->text);

	if($valid) {
		$span = 360 / (2 ** $zoom) * 1.5;
		$vertical = $span * 0.6;
		$format = fn(float $value) => number_format($value, 6, '.', '');
		$lat = $format($latitude);
		$lon = $format($longitude);
		$bbox = implode(',', [
			$format($longitude - $span),
			$format($latitude - $vertical),
			$format($longitude + $span),
			$format($latitude + $vertical),
		]);
		$embed = 'https://www.openstreetmap.org/export/embed.html?' . http_build_query([
			'bbox' => $bbox,
			'layer' => 'mapnik',
			'marker' => $lat . ',' . $lon,
		], '', '&', PHP_QUERY_RFC3986);
		$open = 'https://www.openstreetmap.org/?' . http_build_query([
			'mlat' => $lat,
			'mlon' => $lon,
		], '', '&', PHP_QUERY_RFC3986) . '#map=' . $zoom . '/' . $lat . '/' . $lon;
	}
@endphp

@if($valid)
	@pushOnce('foot')
	<link href="{{ cmstheme($page, 'map.css') }}" rel="preload" as="style">
	@endPushOnce

	<div class="map-layout {{ $hasCopy ? 'with-copy' : '' }}">
		@if($hasCopy)
			<div class="map-copy">
				@if($data->title ?? null)
					<h2>{{ $data->title }}</h2>
				@endif
				@if($data->text ?? null)
					<div class="cms-text">@markdown($data->text)</div>
				@endif
				@if($data->button ?? null)
					<a href="{{ $open }}" role="button" target="_blank" rel="noopener noreferrer">{{ $data->button }}</a>
				@endif
			</div>
		@endif

		<div class="map-frame">
			<iframe
				src="{{ $embed }}"
				title="{{ $data->title ?? 'OpenStreetMap' }}"
				loading="lazy"
				referrerpolicy="strict-origin-when-cross-origin"
				sandbox="allow-scripts allow-same-origin"
			></iframe>
			<a class="map-attribution" href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">© OpenStreetMap contributors</a>
		</div>
	</div>
@endif
