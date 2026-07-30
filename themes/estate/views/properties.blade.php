@pushOnce('foot')
<link href="{{ cmstheme($page, 'list.css') }}" rel="preload" as="style">
<link href="{{ cmstheme($page, 'properties.css') }}" rel="preload" as="style">
<script defer src="{{ cmstheme($page, 'list.js') }}"></script>
@endPushOnce

<div class="list property-list">
    @if(($items = $action->items ?? null) && ($filters = $action->filters ?? null) && ($options = $action->options ?? null))
        @if($data->title ?? null)
            <h2>{{ $data->title }}</h2>
        @endif

        @if($data->filters ?? true)
            <form method="get" action="{{ cmsroute($page) }}" class="property-list-tools property-list-toolbar" aria-label="{{ __('Property filters') }}">
                <input type="hidden" name="p" value="1">

                <div class="property-filter">
                    <label for="property-list-city-{{ $data->id ?? 'list' }}">{{ __('City') }}</label>
                    <input id="property-list-city-{{ $data->id ?? 'list' }}" type="text" name="city" value="{{ $filters->city }}" placeholder="{{ __('All cities') }}">
                </div>

                <div class="property-filter">
                    <label for="property-list-type-{{ $data->id ?? 'list' }}">{{ __('Property type') }}</label>
                    <select id="property-list-type-{{ $data->id ?? 'list' }}" name="type" aria-label="{{ __('Property type') }}">
                        <option value="">{{ __('All types') }}</option>
                        @foreach($options->property_types as $option)
                            <option value="{{ $option['value'] ?? '' }}" @selected($filters->type === strtolower((string) ($option['value'] ?? '')))>
                                {{ __((string) ($option['label'] ?? ($option['value'] ?? ''))) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="property-filter">
                    <label for="property-list-offer-{{ $data->id ?? 'list' }}">{{ __('Offer type') }}</label>
                    <select id="property-list-offer-{{ $data->id ?? 'list' }}" name="offer" aria-label="{{ __('Offer type') }}">
                        <option value="">{{ __('All offer types') }}</option>
                        @foreach($options->offer_types as $option)
                            <option value="{{ $option['value'] ?? '' }}" @selected($filters->offer === (string) ($option['value'] ?? ''))>
                                {{ __((string) ($option['label'] ?? ($option['value'] ?? ''))) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="property-filter">
                    <label for="property-list-rooms-min-{{ $data->id ?? 'list' }}">{{ __('Minimum rooms') }}</label>
                    <input id="property-list-rooms-min-{{ $data->id ?? 'list' }}" type="number" name="rooms_min" value="{{ $filters->rooms_min ?? '' }}" min="1" max="999" step="0.5">
                </div>

                <div class="property-filter">
                    <label for="property-list-available-by-{{ $data->id ?? 'list' }}">{{ __('Available by') }}</label>
                    <input id="property-list-available-by-{{ $data->id ?? 'list' }}" type="date" name="available_by" value="{{ $filters->available_by }}">
                </div>

                <div class="property-filter">
                    <label for="property-list-sort-{{ $data->id ?? 'list' }}">{{ __('Sort') }}</label>
                    <select id="property-list-sort-{{ $data->id ?? 'list' }}" name="sort" aria-label="{{ __('Sort') }}">
                        <option value="_lft" @selected($filters->sort === '_lft')>{{ __('Position') }}</option>
                        <option value="-created_at" @selected($filters->sort === '-created_at')>{{ __('Newest') }}</option>
                        <option value="created_at" @selected($filters->sort === 'created_at')>{{ __('Oldest') }}</option>
                        <option value="updated_desc" @selected($filters->sort === 'updated_desc')>{{ __('Recently updated') }}</option>
                        <option value="updated_asc" @selected($filters->sort === 'updated_asc')>{{ __('Least recently updated') }}</option>
                    </select>
                </div>

                <div class="property-filter property-filter-actions">
                    <button type="submit">{{ __('Apply') }}</button>
                    <a class="button outline" href="{{ cmsroute($page) }}">{{ __('Clear filters') }}</a>
                </div>
            </form>
        @endif

        @if($items->isNotEmpty())
            <p id="property-list-summary-{{ $data->id ?? 'list' }}" class="property-list-summary" role="status" aria-live="polite">
                {{ trans_choice('Showing :from to :to of :total property|Showing :from to :to of :total properties', $items->total(), [
                    'from' => $items->firstItem(),
                    'to' => $items->lastItem(),
                    'total' => $items->total(),
                ]) }}
            </p>
            <div class="list-items list-{{ $data->layout ?? 'cards' }}" data-list="{{ $data->{'parent-page'}?->value ?? '' }}" role="list" aria-describedby="property-list-summary-{{ $data->id ?? 'list' }}">
                @foreach($items as $item)
                    @include('estate::property-item', [
                        'item' => $item,
                        'layout' => $data->layout ?? 'cards',
                        'featured' => false,
                        'property' => collect(cms($item, 'content'))
                            ->filter(fn($element) => ($element->type ?? null) === 'estate::property')
                            ->map(fn($element) => (object) data_get($element, 'data', []))
                            ->first(),
                    ])
                @endforeach
            </div>
            {{ $items->links() }}
        @else
            <div class="list-empty" role="status">
                <p>{{ __('No properties match your criteria') }}</p>
                @if(($data->filters ?? true) && collect((array) $filters)->except('sort')->contains(fn( $value ) => $value !== null && $value !== ''))
                    <a class="button outline" href="{{ cmsroute($page) }}">{{ __('Clear filters') }}</a>
                @endif
            </div>
        @endif

        @if($items->isNotEmpty())
            <script type="application/ld+json">{!! cmsjson([
                '@@context' => 'https://schema.org',
                '@@type' => 'ItemList',
                'name' => $data->title ?? cms($page, 'title'),
                'itemListElement' => $items->getCollection()->values()->map( fn( $item, $idx ) => [
                    '@@type' => 'ListItem',
                    'position' => $items->firstItem() + $idx,
                    'item' => [
                        '@@type' => 'RealEstateListing',
                        'name' => cms($item, 'title'),
                        'url' => route('cms.page', ['path' => $item->path]),
                    ],
                ] )->all(),
            ]) !!}</script>
        @endif
    @endif
</div>
