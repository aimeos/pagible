@pushOnce('head')
<link href="{{ cmstheme($page, 'property.css') }}" rel="stylesheet">
@endPushOnce

@unless(collect(cms($page, 'meta', []))->contains(fn( $item ) => cms($item, 'type') === 'meta-tags'))
    @push('head')
        <meta name="description" content="{{ str(html_entity_decode(strip_tags(\Illuminate\Support\Str::markdown((string) ($data->text ?? ''))), ENT_QUOTES | ENT_HTML5, 'UTF-8'))->squish()->limit(220) }}">
    @endpush
@endunless

@unless(collect(cms($page, 'meta', []))->contains(fn( $item ) => cms($item, 'type') === 'social-media'))
    @push('head')
        <meta property="og:title" content="{{ cms($page, 'title') }}">
        <meta property="og:description" content="{{ str(html_entity_decode(strip_tags(\Illuminate\Support\Str::markdown((string) ($data->text ?? ''))), ENT_QUOTES | ENT_HTML5, 'UTF-8'))->squish()->limit(220) }}">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:url" content="{{ cmsroute($page) }}">
        <meta property="og:type" content="website">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ cms($page, 'title') }}">
        <meta name="twitter:description" content="{{ str(html_entity_decode(strip_tags(\Illuminate\Support\Str::markdown((string) ($data->text ?? ''))), ENT_QUOTES | ENT_HTML5, 'UTF-8'))->squish()->limit(220) }}">
        @if(($socialFile = collect((array) ($data->files ?? []))
            ->map( fn( $fileId ) => cms($files, is_scalar($fileId) ? (string) $fileId : data_get($fileId, 'id')) )
            ->filter()
            ->first()) && ($socialImage = current(array_reverse((array) cms($socialFile, 'previews', []))) ?: cms($socialFile, 'path')))
            <meta property="og:image" content="{{ cmsasset($page, $socialFile, $socialImage) }}">
            <meta property="og:image:url" content="{{ cmsasset($page, $socialFile, $socialImage) }}">
            <meta name="twitter:image" content="{{ cmsasset($page, $socialFile, $socialImage) }}">
            @if($socialImageAlt = cms($socialFile, 'description')?->{cms($page, 'lang')} ?: cms($socialFile, 'name'))
                <meta property="og:image:alt" content="{{ $socialImageAlt }}">
                <meta name="twitter:image:alt" content="{{ $socialImageAlt }}">
            @endif
        @endif
    @endpush
@endunless

<section class="property-gallery">
    @if($slideshowFiles = collect((array) ( $data->files ?? [] ))
        ->map( fn( $fileId ) => is_scalar( $fileId ) ? (object) ['id' => (string) $fileId] : (object) [
            'id' => ( is_array( $fileId ) || is_object( $fileId ) ) ? data_get( $fileId, 'id' ) : null,
        ] )
        ->filter( fn( $file ) => !empty( $file->id ) && cms($files, $file->id) )
        ->all())
        @include('cms::slideshow', [
            'data' => (object) [
                'title' => null,
                'files' => $slideshowFiles,
                'main' => true,
                'autoplay' => false,
                'captions' => true,
            ],
            'page' => $page,
            'files' => $files,
        ])
    @elseif($file = cms($files, $data->file?->id ?? null))
        @include('cms::pic', ['file' => $file, 'main' => true, 'class' => 'cover', 'sizes' => '(max-width: 1280px) 100vw, 1280px'])
    @else
        <div class="property-gallery-empty">
            <span class="property-gallery-empty-brand" aria-hidden="true">Estate</span>
            <span>{{ __('No image available') }}</span>
        </div>
    @endif

    @if(count($slideshowFiles) > 3)
        <details class="property-gallery-more">
            <summary>{{ __('View all images') }}</summary>
            <div class="property-gallery-grid">
                @foreach($slideshowFiles as $item)
                    @if($galleryFile = cms($files, $item->id))
                        <figure>
                            @include('cms::pic', ['file' => $galleryFile, 'sizes' => '(max-width: 767px) 100vw, 50vw'])
                            @if($caption = cms($galleryFile, 'description')?->{cms($page, 'lang')})
                                <figcaption>{{ $caption }}</figcaption>
                            @endif
                        </figure>
                    @endif
                @endforeach
            </div>
        </details>
    @endif
</section>

<article class="property" aria-labelledby="property-title-{{ cms($page, 'id') }}">
    <p class="property-print-meta">
        @if($data->reference ?? null)
            <span>{{ __('Ref.') }} {{ $data->reference }}</span>
        @endif
        <span>{{ __('Source page') }}: {{ cmsroute($page) }}</span>
    </p>

    <div class="property-layout">
        <div class="property-overview">
            @if(($location = collect([
                $data->country ?? null,
                $data->city ?? null,
                $data->district ?? null,
            ])->filter())->isNotEmpty())
                <p class="property-location">
                    @foreach($location as $value)
                        <span>{{ $value }}</span>
                    @endforeach
                </p>
            @endif

            <h1 id="property-title-{{ cms($page, 'id') }}" class="title">{{ cms($page, 'title') }}</h1>

            @if(($data->address ?? null) || ($data->available_from ?? null))
                <dl class="property-primary-facts">
                    @if($data->address ?? null)
                        <div class="property-primary-fact property-address">
                            <dt>{{ __('Address') }}</dt>
                            <dd>
                                <address>
                                    <span>{{ $data->address }}</span>
                                    @if(($locality = collect([$data->zip_code ?? null, $data->city ?? null])->filter())->isNotEmpty())
                                        <span>{{ $locality->implode(' ') }}</span>
                                    @endif
                                </address>
                            </dd>
                        </div>
                    @endif
                    @if($data->available_from ?? null)
                        <div class="property-primary-fact property-available_from">
                            <dt>{{ __('Available from') }}</dt>
                            <dd>
                                <time datetime="{{ $data->available_from }}">{{ \Illuminate\Support\Carbon::parse($data->available_from)->translatedFormat('j F Y') }}</time>
                            </dd>
                        </div>
                    @endif
                </dl>
            @endif

            <div class="property-summary">
                <div class="property-price-group">
                    <p class="property-price">
                        {{ __(':currency :value', ['currency' => $data->currency, 'value' => \Illuminate\Support\Number::format($data->price, maxPrecision: 2, locale: app()->getLocale())]) }}
                    </p>
                    @if($data->offer_type === 'rent' && $data->price_period)
                        <p class="property-price_period">{{ __('per :period', ['period' => __(ucfirst((string) $data->price_period))]) }}</p>
                    @endif
                    <p class="property-price_unit">
                        {{ __(':value :currency / :unit', ['currency' => $data->currency, 'value' => \Illuminate\Support\Number::format(round($data->price / $data->area), locale: app()->getLocale()), 'unit' => $data->area_unit]) }}
                    </p>
                </div>
            </div>

            @if(($highlights = collect([
                'rooms' => [
                    'label' => __('Rooms'),
                    'value' => $data->rooms !== null ? \Illuminate\Support\Number::format($data->rooms, maxPrecision: 1, locale: app()->getLocale()) : null,
                ],
                'bedrooms' => [
                    'label' => __('Bedrooms'),
                    'value' => $data->bedrooms !== null ? \Illuminate\Support\Number::format($data->bedrooms, locale: app()->getLocale()) : null,
                ],
                'bathrooms' => [
                    'label' => __('Bathrooms'),
                    'value' => $data->bathrooms !== null ? \Illuminate\Support\Number::format($data->bathrooms, locale: app()->getLocale()) : null,
                ],
                'living_area' => [
                    'label' => __('Living area'),
                    'value' => __(':value :unit', [
                        'value' => \Illuminate\Support\Number::format($data->living_area ?? $data->area, maxPrecision: 2, locale: app()->getLocale()),
                        'unit' => $data->area_unit,
                    ]),
                ],
            ])->filter( fn( $highlight ) => $highlight['value'] !== null && $highlight['value'] !== '' ))->isNotEmpty())
                <dl class="property-highlights">
                    @foreach($highlights as $prop => $highlight)
                        <div class="property-highlight property-{{ $prop }}">
                            <span class="property-highlight-icon" aria-hidden="true">
                                @if($prop === 'rooms')
                                    <svg viewBox="0 0 24 24">
                                        <path d="M5 3.5h12v17H5zM17 6h2v14.5M8 12h1"/>
                                    </svg>
                                @elseif($prop === 'bedrooms')
                                    <svg viewBox="0 0 24 24">
                                        <path d="M3 19v-9m18 9v-6H3m3 0V8h5a3 3 0 0 1 3 3v2M3 17h18"/>
                                    </svg>
                                @elseif($prop === 'bathrooms')
                                    <svg viewBox="0 0 24 24">
                                        <path d="M4 12h16v2a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5zm3 0V6a3 3 0 0 1 5.5-1.7M7 19l-1 2m11-2 1 2"/>
                                    </svg>
                                @else
                                    <svg viewBox="0 0 24 24">
                                        <path d="M4 9V4h5M15 4h5v5M20 15v5h-5M9 20H4v-5M9 9l6 6m0-6-6 6"/>
                                    </svg>
                                @endif
                            </span>
                            <dt>{{ $highlight['label'] }}</dt>
                            <dd>{{ $highlight['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif

            <section class="property-description">
                <h2>{{ __('Description') }}</h2>
                <div class="cms-text">@markdown($data->text ?? '')</div>
            </section>

            @if($data->features ?? null)
                <section class="property-features">
                    <h2>{{ __('Key details') }}</h2>
                    <div class="property-feature-text">@markdown($data->features)</div>
                </section>
            @endif
        </div>

        <aside class="property-enquiry">
            <p class="property-status property-status-{{ $data->status }}">{{ __(ucfirst(str_replace('_', ' ', (string) $data->status))) }}</p>
            <h2>
                {{ in_array($data->status, ['sold', 'rented'], true)
                    ? __('Ask about similar properties')
                    : __('Request a viewing') }}
            </h2>
            @if($data->reference ?? null)
                <p class="property-reference">{{ __('Ref.') }} {{ $data->reference }}</p>
            @endif
            <p class="property-updated">
                {{ __('Updated') }}
                <time datetime="{{ $page->updated_at->toDateString() }}">{{ $page->updated_at->translatedFormat('j F Y') }}</time>
            </p>
            <a class="property-contact-link" href="#property-contact-{{ cms($page, 'id') }}">
                {{ in_array($data->status, ['sold', 'rented'], true)
                    ? __('Ask about similar properties')
                    : __('Request a viewing') }}
            </a>
        </aside>
    </div>

    @if(($facts = collect([
        'property_type' => ['label' => __('Type'), 'value' => __(ucfirst(str_replace('_', ' ', (string) $data->property_type)))],
        'area' => ['label' => __('Area'), 'value' => __(':value :unit', ['value' => \Illuminate\Support\Number::format($data->area, maxPrecision: 2, locale: app()->getLocale()), 'unit' => $data->area_unit])],
        'living_area' => ['label' => __('Living area'), 'value' => $data->living_area !== null ? __(':value :unit', ['value' => \Illuminate\Support\Number::format($data->living_area, maxPrecision: 2, locale: app()->getLocale()), 'unit' => $data->area_unit]) : null],
        'plot_area' => ['label' => __('Plot area'), 'value' => $data->plot_area !== null ? __(':value :unit', ['value' => \Illuminate\Support\Number::format($data->plot_area, maxPrecision: 2, locale: app()->getLocale()), 'unit' => $data->area_unit]) : null],
        'rooms' => ['label' => __('Rooms'), 'value' => $data->rooms !== null ? \Illuminate\Support\Number::format($data->rooms, maxPrecision: 1, locale: app()->getLocale()) : null],
        'bedrooms' => ['label' => __('Bedrooms'), 'value' => $data->bedrooms !== null ? \Illuminate\Support\Number::format($data->bedrooms, locale: app()->getLocale()) : null],
        'bathrooms' => ['label' => __('Bathrooms'), 'value' => $data->bathrooms !== null ? \Illuminate\Support\Number::format($data->bathrooms, locale: app()->getLocale()) : null],
        'year_built' => ['label' => __('Year built'), 'value' => $data->year_built !== null ? $data->year_built : null],
    ])->filter( fn( $fact ) => $fact['value'] !== null && $fact['value'] !== '' )->merge(
        collect($data->values ?? [])
            ->filter( fn( $row ) => trim((string) ($row[0] ?? '')) !== '' && trim((string) ($row[1] ?? '')) !== '' )
            ->values()
            ->mapWithKeys( fn( $row, $idx ) => ['value_' . $idx => [
                'label' => (string) $row[0],
                'value' => (string) $row[1],
            ]] )
    ))->isNotEmpty())
        <section class="property-details">
            <div class="property-details-inner">
                <h2>{{ __('Property details') }}</h2>
                <div class="property-fact-columns">
                    @foreach($facts->split(2) as $column)
                        <dl class="property-facts">
                            @foreach($column as $prop => $fact)
                                <div class="property-fact property-{{ $prop }}">
                                    <dt>{{ $fact['label'] }}</dt>
                                    <dd>{{ $fact['value'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if(
        is_numeric(data_get($data, 'map.latitude'))
        && (float) data_get($data, 'map.latitude') >= -90
        && (float) data_get($data, 'map.latitude') <= 90
        && is_numeric(data_get($data, 'map.longitude'))
        && (float) data_get($data, 'map.longitude') >= -180
        && (float) data_get($data, 'map.longitude') <= 180
    )
        <section class="property-location-description property-map map">
            @include('cms::map', [
                'data' => (object) [
                    'title' => __('Location'),
                    'text' => $data->location ?? null,
                    'location' => $data->map,
                ],
                'page' => $page,
            ])
        </section>
    @elseif($data->location ?? null)
        <section class="property-location-description">
            <h2>{{ __('Location') }}</h2>
            <div class="cms-text">@markdown($data->location)</div>
        </section>
    @endif

    @if(($documents = collect($data->documents ?? [])->map( fn( $document ) => [
        'title' => trim((string) data_get($document, 'title')),
        'file' => cms($files, data_get($document, 'file.id')),
    ])->filter( fn( $document ) => $document['file'] )->values())->isNotEmpty())
        <section class="property-documents">
            <h2>{{ __('Documents') }}</h2>
            <ul>
                @foreach($documents as $document)
                    <li>
                        <a href="{{ cmsasset($page, $document['file']) }}" download>
                            <span class="property-document-meta">
                                <span>{{ $document['title'] !== '' ? $document['title'] : $document['file']->name }}</span>
                                @if($documentType = strtoupper(pathinfo((string) ($document['file']->name ?: $document['file']->path), PATHINFO_EXTENSION)))
                                    <span class="property-document-type">{{ $documentType }}</span>
                                @endif
                            </span>
                            <span class="property-document-action">{{ __('Download') }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section id="property-contact-{{ cms($page, 'id') }}" class="property-contact contact">
        @include('cms::contact', [
            'data' => (object) [
                'id' => 'property-' . cms($page, 'id'),
                'title' => in_array($data->status, ['sold', 'rented'], true)
                    ? __('Ask about similar properties')
                    : __('Request a viewing'),
            ],
            'jsonld' => false,
            'page' => $page,
            'source' => cmsroute($page),
        ])
    </section>

    <script type="application/ld+json">{!! cmsjson(array_filter([
        '@@context' => 'https://schema.org',
        '@@type' => 'RealEstateListing',
        'name' => cms($page, 'title'),
        'identifier' => $data->reference ?? null,
        'category' => __(ucfirst(str_replace('_', ' ', (string) $data->property_type))),
        'description' => str(html_entity_decode(strip_tags(\Illuminate\Support\Str::markdown((string) ($data->text ?? ''))), ENT_QUOTES | ENT_HTML5, 'UTF-8'))->squish()->limit(220),
        'datePublished' => $page->created_at->toIso8601String(),
        'dateModified' => $page->updated_at->toIso8601String(),
        'url' => cmsroute($page),
        'image' => ($propertyImages = collect($slideshowFiles)
            ->map( fn( $file ) => cms($files, $file->id) )
            ->filter()
            ->map( fn( $file ) => cmsasset($page, $file) )
            ->values()
            ->all()) ? $propertyImages : null,
        'availabilityStarts' => $data->available_from ?? null,
        'address' => ($propertyAddress = array_filter([
            'streetAddress' => $data->address ?? null,
            'addressLocality' => $data->city ?? null,
            'addressRegion' => $data->district ?? null,
            'postalCode' => $data->zip_code ?? null,
            'addressCountry' => $data->country ?? null,
        ], fn( $value ) => $value !== null && $value !== ''))
            ? ['@@type' => 'PostalAddress'] + $propertyAddress
            : null,
        'floorSize' => [
            '@@type' => 'QuantitativeValue',
            'value' => $data->area,
            'unitText' => $data->area_unit,
        ],
        'numberOfRooms' => $data->rooms ?? null,
        'numberOfBedrooms' => $data->bedrooms ?? null,
        'numberOfBathroomsTotal' => $data->bathrooms ?? null,
        'yearBuilt' => $data->year_built ?? null,
        'availability' => 'https://schema.org/' . match( strtolower((string) $data->status) ) {
            'under_offer' => 'LimitedAvailability',
            'sold', 'rented' => 'OutOfStock',
            default => 'InStock',
        },
        'offers' => array_filter([
            '@@type' => 'Offer',
            'price' => $data->price,
            'priceCurrency' => $data->currency,
            'seller' => ($seller = trim((string) config('app.name'))) !== '' && strcasecmp($seller, 'Laravel') !== 0 ? [
                '@@type' => 'Organization',
                'name' => $seller,
                'url' => url('/'),
            ] : null,
            'businessFunction' => 'http://purl.org/goodrelations/v1#' . ($data->offer_type === 'rent' ? 'LeaseOut' : 'Sell'),
            'priceSpecification' => $data->offer_type === 'rent' && $data->price_period ? [
                '@@type' => 'UnitPriceSpecification',
                'price' => $data->price,
                'priceCurrency' => $data->currency,
                'unitText' => $data->price_period,
            ] : null,
            'availability' => 'https://schema.org/' . match( strtolower((string) $data->status) ) {
                'under_offer' => 'LimitedAvailability',
                'sold', 'rented' => 'OutOfStock',
                default => 'InStock',
            },
            'url' => cmsroute($page),
        ], fn( $value ) => $value !== null && $value !== ''),
    ], fn( $value ) => $value !== null && $value !== '')) !!}</script>
</article>
