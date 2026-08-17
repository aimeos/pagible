<a href="{{ cmsroute('cms.page', ['path' => $item->path], $item->domain ?? null) }}" class="list-item"
    aria-labelledby="property-title-{{ cms($item, 'id') ?: md5((string) $item->path) }}"
    aria-describedby="property-meta-{{ cms($item, 'id') ?: md5((string) $item->path) }}">
@if($property = $property ?? collect(cms($item, 'content'))
    ->filter(fn($element) => ($element->type ?? null) === 'estate::property')
    ->map(fn($element) => (object) data_get($element, 'data', []))
    ->first())
    @if($file = cms(cms($item, 'files'), data_get(collect((array) ($property->files ?? []))
        ->map( fn( $fileId ) => (object) ['id' => is_scalar( $fileId ) ? (string) $fileId : data_get( $fileId, 'id' )] )
        ->first(), 'id', $property->file?->id ?? null)))
        @include('cms::pic', [
            'file' => $file,
            'main' => (bool) ($featured ?? false),
            'sizes' => '(max-width: 768px) 100vw, 45vw',
        ])
    @else
        <div class="property-image-empty">
            <span class="property-image-empty-brand" aria-hidden="true">Estate</span>
            <span>{{ __('No image available') }}</span>
        </div>
    @endif

    <div class="content">
        <div id="property-meta-{{ cms($item, 'id') ?: md5((string) $item->path) }}" class="property-meta">
            @if(($location = collect([
                $property->district ?? null,
                empty($property->district) ? ($property->city ?? null) : null,
                $property->country ?? null,
            ])->filter())->isNotEmpty())
                <p class="property-location">
                    @foreach($location as $value)
                        <span>{{ $value }}</span>
                    @endforeach
                </p>
            @endif
        </div>

        <h3 id="property-title-{{ cms($item, 'id') ?: md5((string) $item->path) }}">{{ cms($item, 'title') }}</h3>

        <p class="property-price">
            {{ $property->offer_type === 'rent' && $property->price_period
                ? __(':currency :value per :period', [
                    'currency' => $property->currency,
                    'value' => \Illuminate\Support\Number::format($property->price, maxPrecision: 2, locale: app()->getLocale()),
                    'period' => __(ucfirst((string) $property->price_period)),
                ])
                : __(':currency :value', [
                    'currency' => $property->currency,
                    'value' => \Illuminate\Support\Number::format($property->price, maxPrecision: 2, locale: app()->getLocale()),
                ]) }}
        </p>

        @if(($facts = collect([
            'rooms' => $property->rooms !== null ? __(':value :unit', [
                'value' => \Illuminate\Support\Number::format($property->rooms, maxPrecision: 1, locale: app()->getLocale()),
                'unit' => __('rooms'),
            ]) : null,
            'bathrooms' => $property->bathrooms !== null ? __(':value :unit', [
                'value' => \Illuminate\Support\Number::format($property->bathrooms, locale: app()->getLocale()),
                'unit' => __('Bathrooms'),
            ]) : null,
            'area' => __(':value :unit', [
                'value' => \Illuminate\Support\Number::format($property->living_area ?? $property->area, maxPrecision: 2, locale: app()->getLocale()),
                'unit' => $property->area_unit,
            ]) . ' ' . __('Living area'),
        ])->filter( fn( $value ) => $value !== null && $value !== '' ))->isNotEmpty())
            <p class="property-line property-line-main">
                @foreach($facts as $prop => $value)
                    @if(!$loop->first)
                        <span class="property-meta-separator" aria-hidden="true">·</span>
                    @endif
                    <span class="property-line-item property-{{ $prop }}">{{ $value }}</span>
                @endforeach
            </p>
        @endif

        <p class="property-line property-line-status">
            <span class="property-status property-status-{{ $property->status }}">
                {{ __(ucfirst(str_replace('_', ' ', (string) $property->status))) }}
            </span>
        </p>

        @if($property->available_from ?? null)
            <p class="property-line property-line-availability">
                <span class="property-available_from">{{ __('Available from') }}</span>
                <time datetime="{{ $property->available_from }}">{{ \Illuminate\Support\Carbon::parse($property->available_from)->translatedFormat('j F Y') }}</time>
            </p>
        @endif

        @if($property->text ?? null)
            <p class="intro">{{ str(html_entity_decode(strip_tags(\Illuminate\Support\Str::markdown((string) $property->text)), ENT_QUOTES | ENT_HTML5, 'UTF-8'))->squish()->limit(($layout ?? 'cards') === 'list' ? 220 : 160) }}</p>
        @endif
    </div>
@endif
</a>
