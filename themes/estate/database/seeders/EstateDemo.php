<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Database\Seeders;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Validation;


/**
 * Estate theme demo inspired by premium international real-estate portals.
 */
class EstateDemo extends AbstractDemo
{
    /** @var array<string, string> Meta descriptions keyed by page path */
    private const DESCRIPTIONS = [
        'properties' => 'Current residential, rental, and commercial properties, presented together for direct comparison.',
        'exposes' => 'Detailed presentations for every current residential and commercial property.',
        'exposes/urban-penthouse-berlin' => 'Luxury penthouse with rooftop access and premium interior context in central Berlin.',
        'exposes/riverfront-office-suite' => 'Converted office floorplan for mixed retail and hospitality-adjacent use.',
        'exposes/harbor-retail-loft' => 'Harbor-side commercial property with flexible frontage and modern infrastructure.',
        'news' => 'Weekly market updates and property strategy notes for clients evaluating high-end transactions.',
        'news/why-portfolio-diversification-matters' => 'How location, tenant mix, and lease duration affect long-term property performance.',
        'news/renovation-as-a-leverage-point' => 'Practical ways to preserve value through targeted upgrades and tenant-ready delivery.',
    ];

    /**
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const PHOTOS = [
        'advisory' => ['photo-1450101499163-c8848c66ca85', 'Property advisory documents', 'Advisor annotating documents during a property consultation'],
        'city' => ['photo-1449824913935-59a10b8d2000', 'International property market', 'Broad city avenue framed by dense commercial and residential towers'],
        'home' => ['photo-1600585154340-be6161a56a0c', 'Contemporary residence', 'Dark-clad contemporary residence with floor-to-ceiling glazing and a landscaped garden'],
        'market' => ['photo-1486406146926-c627a92ad1ab', 'Commercial property market', 'Modern commercial towers viewed from a city plaza'],
        'office-circulation' => ['photo-1497366754035-f200968a6e72', 'Office circulation', 'Polished office corridor with glazed meeting rooms and lounge areas'],
        'office-floor' => ['photo-1531973576160-7125cd663d86', 'Open-plan office floor', 'Large contemporary office floor with workstations, collaboration tables, and exposed ceiling services'],
        'office-reception' => ['photo-1587702068694-a909ef4aa346', 'Office reception', 'Double-height reception lounge with full-height glazing and street access'],
        'penthouse-bedroom' => ['photo-1702411200201-3061d0eea802', 'Penthouse bedroom', 'Dark-toned bedroom with panoramic glazing, a suspended fireplace, and a spiral stair'],
        'penthouse-living' => ['photo-1560448204-e02f11c3d0e2', 'Penthouse living room', 'Bright open-plan living and dining area with broad windows and terrace access'],
        'penthouse-terrace' => ['photo-1762195804066-2fece9b24496', 'Private roof terrace', 'Furnished roof terrace with city views, planting, and outdoor seating'],
        'renovation' => ['photo-1504307651254-35680f356dfd', 'Building renovation', 'Construction team completing structural work on an active building site'],
        'retail-fitout' => ['photo-1777136977034-71cbc026c9b1', 'Retail fit-out', 'Minimal retail display wall with integrated shelving and a garment rail'],
        'retail-frontage' => ['photo-1770902971692-e4b9e3cf3933', 'Retail frontage', 'Illuminated corner storefront with broad full-height display windows'],
        'retail-loft' => ['photo-1773069459487-3d2d7bb4532e', 'Retail loft interior', 'Open brick loft with tall windows, flexible floor space, and modern finishes'],
    ];

    private string $element;
    private string $logoFile;


    protected function addProperties( Page $home, string $exposesId, string $propertiesId ) : static
    {
        $properties = $this->page( [
            'id' => $propertiesId,
            'lang' => 'en',
            'name' => 'Properties',
            'title' => 'Properties',
            'path' => 'properties',
            'type' => 'page',
            'tag' => 'properties',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Properties',
                'subtitle' => 'Estate portfolio',
                'text' => 'Compare current residential, rental, and commercial properties by location, availability, and practical fit.',
                'background' => ['id' => $this->img( 'city' ), 'type' => 'file'],
                'background-animation' => 'zoom',
            ]],
            ['id' => Utils::uid(), 'type' => 'estate::properties', 'group' => 'main', 'data' => [
                'title' => 'Current properties',
                'layout' => 'cards',
                'filters' => true,
                'limit' => 24,
                'order' => '_lft',
                'parent-page' => ['value' => $exposesId, 'label' => 'Exposes'],
            ]],
        ], $home );

        $exposes = $this->page( [
            'id' => $exposesId,
            'lang' => 'en',
            'name' => 'Exposes',
            'title' => 'Property exposes',
            'path' => 'exposes',
            'tag' => 'exposes',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Property exposes',
                'subtitle' => 'Detailed presentations',
                'text' => 'Open the full presentation for each current property, or return to the complete overview to compare location, price, availability, and fit.',
                'background' => ['id' => $this->img( 'market' ), 'type' => 'file'],
                'background-animation' => 'zoom',
                'url' => '/properties',
                'button' => 'View all properties',
            ]],
        ], $home );

        $this->page( [
            'lang' => 'en',
            'name' => 'Urban Penthouse Berlin',
            'title' => 'Urban Penthouse, Berlin',
            'path' => 'exposes/urban-penthouse-berlin',
            'tag' => 'property',
            'type' => 'property',
            'status' => 1,
        ], [
            $this->property(
                'A bright penthouse with skyline orientation, private terrace, and building security in one of Berlin’s most active districts.',
                [
                    $this->img( 'penthouse-living' ),
                    $this->img( 'penthouse-bedroom' ),
                    $this->img( 'penthouse-terrace' ),
                ],
                'sale',
                'available',
                3250000,
                'Berlin Mitte',
                310,
                4,
                3,
                [
                    'reference' => 'EST-UB-001',
                    'property_type' => 'penthouse',
                    'address' => 'Kopenhagener Straße 112',
                    'city' => 'Berlin',
                    'country' => 'Germany',
                    'zip_code' => '10437',
                    'available_from' => '2026-09-01',
                    'living_area' => 245,
                    'plot_area' => null,
                    'rooms' => 10,
                    'year_built' => 2015,
                    'location' => 'Set between Prenzlauer Berg and the historic centre, the address offers quick tram and S-Bahn connections. Independent cafés, daily shopping, parks, and cultural venues are all within easy reach.',
                    'values' => [
                        ['Floor', '7'],
                        ['Parking', '2 parking spaces'],
                        ['Energy class', 'A'],
                        ['Condition', 'Turnkey'],
                        ['Heating', 'District heating with cooling'],
                        ['Terrace', 'Private roof terrace'],
                        ['Lift', 'Direct penthouse access'],
                    ],
                    'features' => "## Property features\n- 3 bedrooms / 3 bathrooms\n- Private terrace and panoramic views\n- Smart home controls\n- Private parking garage",
                ],
            ),
        ], $exposes );

        $this->page( [
            'lang' => 'en',
            'name' => 'Riverfront Office Suite',
            'title' => 'Riverfront Office Suite',
            'path' => 'exposes/riverfront-office-suite',
            'tag' => 'property',
            'type' => 'property',
            'status' => 1,
        ], [
            $this->property(
                'A flexible office suite with polished circulation, high-speed infrastructure, and direct access to mixed-use retail frontage.',
                [
                    $this->img( 'office-floor' ),
                    $this->img( 'office-circulation' ),
                    $this->img( 'office-reception' ),
                ],
                'rent',
                'available',
                62000,
                'Hamburg Waterfront',
                1240,
                0,
                2,
                [
                    'reference' => 'EST-OF-044',
                    'property_type' => 'office',
                    'address' => 'Hafencity Promenade 24',
                    'city' => 'Hamburg',
                    'country' => 'Germany',
                    'zip_code' => '20457',
                    'available_from' => '2026-08-15',
                    'price_period' => 'month',
                    'living_area' => 920,
                    'plot_area' => null,
                    'rooms' => 8,
                    'year_built' => 2018,
                    'location' => 'The suite sits within Hamburg’s eastern HafenCity business district, close to the main station, riverfront restaurants, and mixed-use services. Local transit links connect the property directly with the city centre.',
                    'values' => [
                        ['Floor', '2'],
                        ['Parking', '6 parking spaces'],
                        ['Energy class', 'B'],
                        ['Condition', 'Ready to move'],
                        ['Heating', 'Gas hybrid'],
                        ['Lease term', 'Flexible'],
                        ['Workstations', 'Up to 80'],
                    ],
                    'features' => "## Property features\n- Open floor plan with co-working layout\n- Reception and loading access\n- 24/7 elevator and security\n- Built-in audio-visual systems",
                ],
            ),
        ], $exposes );

        $this->page( [
            'lang' => 'en',
            'name' => 'Harbor Retail Loft',
            'title' => 'Harbor Retail Loft',
            'path' => 'exposes/harbor-retail-loft',
            'tag' => 'property',
            'type' => 'property',
            'status' => 1,
        ], [
            $this->property(
                'An adaptable waterfront retail loft with high visibility, clear tenant routes, and broad frontage for premium tenant conversions.',
                [
                    $this->img( 'retail-loft' ),
                    $this->img( 'retail-frontage' ),
                    $this->img( 'retail-fitout' ),
                ],
                'sale',
                'under_offer',
                2150000,
                'Hamburg Hafen City',
                380,
                0,
                1,
                [
                    'reference' => 'EST-RT-117',
                    'property_type' => 'retail',
                    'address' => 'Überseehafen Straße 9',
                    'city' => 'Hamburg',
                    'country' => 'Germany',
                    'zip_code' => '20457',
                    'living_area' => 320,
                    'plot_area' => null,
                    'rooms' => 5,
                    'year_built' => 2005,
                    'location' => 'The loft occupies a visible waterfront corner in HafenCity, surrounded by offices, apartments, hotels, and visitor destinations. Its broad frontage benefits from steady pedestrian movement throughout the working week and at weekends.',
                    'values' => [
                        ['Floor', 'Ground + 1'],
                        ['Parking', '1 valet bay'],
                        ['Energy class', 'B+'],
                        ['Condition', 'As-is with fit-out scope'],
                        ['Heating', 'District heating'],
                        ['Frontage', 'Waterfront'],
                        ['Access', 'Street and rear'],
                    ],
                    'features' => "## Property features\n- Street frontage and high visibility\n- Flexible tenant circulation\n- Storage annex and rear access\n- Elevator connection to mezzanine",
                ],
            ),
        ], $exposes );

        return $this;
    }


    protected function addNews( Page $home, string $newsId ) : static
    {
        $news = $this->page( [
            'id' => $newsId,
            'lang' => 'en',
            'name' => 'News',
            'title' => 'Estate News',
            'path' => 'news',
            'to' => '/news/why-portfolio-diversification-matters',
            'tag' => 'blog',
            'type' => 'blog',
            'status' => 1,
        ], [], $home );

        $this->page( [
            'lang' => 'en',
            'name' => 'Why portfolio diversification matters',
            'title' => 'Why portfolio diversification matters now',
            'path' => 'news/why-portfolio-diversification-matters',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'Why portfolio diversification matters now',
                "A concentrated position can perform well when cycles align, but long-horizon owners usually protect downside with location and tenant balance. That remains true for both residential and commercial portfolios.\n\nUseful diversification is not a property count. It is a deliberate spread of income drivers, lease events, building needs, and local demand.",
                $this->img( 'market' )
            ),
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'city' ), 'type' => 'file'],
                'position' => 'end',
                'ratio' => '1-2',
                'text' => "## Diversify the source of demand\n\nTwo properties in different postcodes can still depend on the same employer base, tenant profile, or financing conditions. Before expanding allocation, compare rental carry, demand resilience, and tenant retention rather than headline yield alone.\n\nThe useful question is not whether the next address is different. It is whether the income behaves differently when the market changes.",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Three portfolio lenses',
                'columns' => 3,
                'cards' => [
                    [
                        'title' => 'Location',
                        'text' => 'Compare employment, infrastructure, supply pipelines, and the depth of local buyer and tenant demand.',
                    ],
                    [
                        'title' => 'Use',
                        'text' => 'Residential, office, and retail income respond to different operating cycles, regulations, and customer habits.',
                    ],
                    [
                        'title' => 'Lease profile',
                        'text' => 'Spread expiry dates, tenant concentration, indexation terms, and capital obligations instead of only adding units.',
                    ],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Read concentration before headline return',
                'header' => 'row',
                'table' => [
                    ['Portfolio lens', 'Question to ask', 'Evidence to compare'],
                    ['Demand', 'What keeps this location occupied?', 'Vacancy, enquiries, absorption, employer mix'],
                    ['Income', 'How much revenue depends on one event?', 'Tenant share, lease expiries, indexation'],
                    ['Asset', 'Which costs could arrive together?', 'Building age, energy plan, maintenance cycle'],
                    ['Exit', 'Who is likely to buy next?', 'Comparable sales, lot size, financing appetite'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Questions before the next acquisition',
                'items' => [
                    [
                        'title' => 'How many properties make a portfolio diversified?',
                        'text' => 'There is no useful universal number. A larger portfolio can remain concentrated when the assets share a city, tenant sector, lease cycle, or refinancing date. Measure the exposure behind each income stream.',
                    ],
                    [
                        'title' => 'Does buying in a second city reduce risk?',
                        'text' => 'Only when the second market has genuinely different demand drivers. Compare employment, supply, regulation, and tenant behaviour before treating distance as diversification.',
                    ],
                    [
                        'title' => 'When should an existing asset be sold?',
                        'text' => 'Review assets that dominate portfolio income, require disproportionate capital, or no longer fit the intended risk profile. A sale should improve the portfolio, not merely remove an inconvenient building.',
                    ],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Review the portfolio before the next purchase',
                'subtitle' => 'Estate advisory',
                'text' => 'Map income, lease events, location exposure, and planned capital work before comparing the next opportunity.',
                'url' => '/#home-contact',
                'button' => 'Request a portfolio review',
                'url-alternative' => '/properties',
                'button-alternative' => 'Compare current properties',
            ]],
        ], $news );

        $this->page( [
            'lang' => 'en',
            'name' => 'Renovation as a leverage point',
            'title' => 'Renovation as a leverage point',
            'path' => 'news/renovation-as-a-leverage-point',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'Renovation as a leverage point',
                "Small, high-signal upgrades often beat full rebuilds when they follow a lease-cycle and market review. Targeted updates improve conversion speed and reduce pricing friction without committing capital to work buyers or tenants will not value.\n\nThe strongest brief starts with the obstacle: slow viewings, recurring defects, dated systems, poor circulation, or a widening gap to comparable properties.",
                $this->img( 'renovation' )
            ),
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'retail-fitout' ), 'type' => 'file'],
                'position' => 'start',
                'ratio' => '1-2',
                'text' => "## Start with property friction\n\nViewing feedback, maintenance records, tenant requests, and comparable listings usually show where value is being lost. Translate those signals into a short brief before discussing finishes.\n\nThat keeps the work tied to daylight, acoustics, storage, circulation, energy use, and move-in readiness rather than a catalogue of upgrades.",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Upgrades people notice',
                'columns' => 3,
                'cards' => [
                    [
                        'title' => 'Everyday usability',
                        'text' => 'Improve light, storage, circulation, acoustics, and access where they interrupt how the property is used.',
                    ],
                    [
                        'title' => 'Building confidence',
                        'text' => 'Resolve visible defects and clarify the condition of heating, ventilation, electrics, windows, and the envelope.',
                    ],
                    [
                        'title' => 'Market readiness',
                        'text' => 'Choose durable, neutral work that shortens the path from viewing to occupation without erasing the property’s character.',
                    ],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Match the scope to the outcome',
                'header' => 'row',
                'table' => [
                    ['Objective', 'Start with', 'Keep the scope disciplined by'],
                    ['Faster letting', 'Viewing feedback and vacancy causes', 'Removing objections that repeat across prospects'],
                    ['Protect income', 'Repair history and tenant requests', 'Fixing recurring failures before cosmetic work'],
                    ['Reposition the asset', 'Comparable supply and target user', 'Defining the intended market before design begins'],
                    ['Plan a sale', 'Survey findings and buyer expectations', 'Separating essential work from owner preference'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Questions before approving works',
                'items' => [
                    [
                        'title' => 'Which work belongs between tenancies?',
                        'text' => 'Prioritise intrusive repairs, safety work, services, flooring, decoration, and changes that would otherwise disrupt occupation. Confirm lead times before the existing tenancy ends.',
                    ],
                    [
                        'title' => 'How do you avoid over-improving?',
                        'text' => 'Set the target tenant or buyer, compare nearby alternatives, and agree the required outcome for each cost line. Premium materials do not create a premium result when the location or layout sets a lower ceiling.',
                    ],
                    [
                        'title' => 'When is a full refurbishment justified?',
                        'text' => 'Consider it when systems are near the end of their life, the layout blocks the intended use, compliance work is substantial, or several isolated projects would duplicate cost and disruption.',
                    ],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Turn the survey into a renovation brief',
                'subtitle' => 'Estate advisory',
                'text' => 'Set the market objective, essential work, budget range, and decision points before design and procurement begin.',
                'url' => '/#home-contact',
                'button' => 'Discuss a renovation strategy',
                'url-alternative' => '/properties',
                'button-alternative' => 'View current properties',
            ]],
        ], $news );

        return $this;
    }


    protected function property( string $text, array $fileIds, string $offerType, string $status, int|float $price, string $district, int|float $area, int $bedrooms, int $bathrooms, array $extra = [] ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'estate::property', 'group' => 'main', 'files' => $fileIds, 'data' => [
            'text' => $text,
            'files' => array_map( fn( $id ) => ['id' => $id, 'type' => 'file'], $fileIds ),
            'offer_type' => $offerType,
            'status' => $status,
            'price' => $price,
            'currency' => $extra['currency'] ?? 'EUR',
            'price_period' => $extra['price_period'] ?? null,
            'district' => $district,
            'area' => $area,
            'area_unit' => $extra['area_unit'] ?? 'm²',
            'bedrooms' => $bedrooms,
            'bathrooms' => $bathrooms,
            'reference' => $extra['reference'] ?? null,
            'property_type' => $extra['property_type'] ?? null,
            'address' => $extra['address'] ?? null,
            'city' => $extra['city'] ?? null,
            'country' => $extra['country'] ?? null,
            'zip_code' => $extra['zip_code'] ?? null,
            'available_from' => $extra['available_from'] ?? null,
            'living_area' => $extra['living_area'] ?? null,
            'plot_area' => $extra['plot_area'] ?? null,
            'rooms' => $extra['rooms'] ?? null,
            'year_built' => $extra['year_built'] ?? null,
            'location' => $extra['location'] ?? null,
            'values' => $extra['values'] ?? [],
            'features' => $extra['features'] ?? null,
        ]];
    }


    protected function article( string $title, string $text, string $fileId ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'article', 'group' => 'main', 'files' => [$fileId], 'data' => [
            'title' => $title,
            'file' => ['id' => $fileId, 'type' => 'file'],
            'text' => $text,
        ]];
    }


    protected function element() : string
    {
        if( !isset( $this->element ) )
        {
            $cards = [
                ['title' => 'Explore', 'text' => "- [Home](/)\n- [Current properties](/properties)\n- [Property news](/news)"],
                ['title' => 'Property search', 'text' => "- [Properties for sale](/properties?offer=sale)\n- [Properties for rent](/properties?offer=rent)\n- [Commercial properties](/properties?type=office)"],
                ['title' => 'Advisory services', 'text' => "- [Request a sales valuation](/#home-contact)\n- [Discuss a commercial property](/#home-contact)\n- [Plan a relocation](/#home-contact)"],
                ['title' => 'Contact', 'text' => "- [hello@estate.example](mailto:hello@estate.example)\n- [Request a private consultation](/#home-contact)\n- Berlin · Hamburg"],
            ];

            $element = Element::forceCreate( [
                'lang' => 'en',
                'type' => 'cards',
                'name' => 'Estate footer',
                'data' => ['type' => 'cards', 'data' => ['cards' => $cards]],
                'editor' => 'demo',
            ] );

            $version = $element->versions()->forceCreate( [
                'lang' => 'en',
                'data' => [
                    'lang' => 'en',
                    'type' => 'cards',
                    'name' => 'Estate footer',
                    'data' => ['cards' => $cards],
                ],
                'editor' => 'demo',
            ] );

            $element->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $element->publish( $version );
            $this->element = (string) $element->refresh()->id;
        }

        return $this->element;
    }


    protected function file() : string
    {
        return $this->img( 'home' );
    }


    protected function home( string $exposesId, string $newsId, string $propertiesId ) : Page
    {
        $elementId = $this->element();
        $fileId = $this->file();
        $logoId = $this->logoFile();

        $config = [
            'logo' => [
                'type' => 'logo',
                'files' => [$logoId],
                'data' => ['file' => ['id' => $logoId, 'type' => 'file']],
            ],
            'logo-alternative' => [
                'type' => 'logo-alternative',
                'files' => [$logoId],
                'data' => ['file' => ['id' => $logoId, 'type' => 'file']],
            ],
        ];

        $content = [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Find the property behind your next move',
                'subtitle' => 'Estate',
                'text' => 'Compare residential, rental, and commercial properties with clear pricing, availability, location details, and practical market context.',
                'url' => '/properties',
                'button' => 'View properties',
                'url-alternative' => '/news',
                'button-alternative' => 'Read property news',
                'background' => ['id' => $fileId, 'type' => 'file'],
                'background-animation' => 'zoom',
            ]],
            ['id' => 'home-properties', 'type' => 'estate::properties', 'group' => 'main', 'data' => [
                'title' => 'Current properties',
                'layout' => 'cards',
                'filters' => true,
                'limit' => 6,
                'order' => '_lft',
                'parent-page' => ['value' => $exposesId, 'label' => 'Exposes'],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'advisory' ), 'type' => 'file'],
                'position' => 'grid-start',
                'ratio' => '1-1',
                'text' => "## Your property deserves informed positioning\n\nStrong results begin before a listing goes live. Estate Group combines local market evidence with an international view of buyer demand, so pricing, presentation, and timing support the same objective.\n\n[Request a property consultation](/#home-contact)",
            ]],
            ['id' => 'home-services', 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Guidance for every property decision',
                'columns' => 3,
                'cards' => [
                    [
                        'title' => 'Buy with perspective',
                        'file' => ['id' => $this->img( 'penthouse-living' ), 'type' => 'file'],
                        'text' => "Compare location, condition, and long-term fit with an advisor who understands the market behind the asking price.\n\n[Explore current properties](/properties)",
                    ],
                    [
                        'title' => 'Sell with a clear strategy',
                        'file' => ['id' => $this->img( 'home' ), 'type' => 'file'],
                        'text' => "Build the right positioning, presentation, and launch plan around your property and the buyers most likely to value it.\n\n[Request a property consultation](/#home-contact)",
                    ],
                    [
                        'title' => 'Navigate commercial property',
                        'file' => ['id' => $this->img( 'office-reception' ), 'type' => 'file'],
                        'text' => "Assess tenant profile, lease structure, access, and operational potential before committing capital or floor space.\n\n[Discuss a commercial property](/#home-contact)",
                    ],
                ],
            ]],
            ['id' => 'home-insights', 'type' => 'blog', 'group' => 'main', 'data' => [
                'title' => 'Latest property insights',
                'layout' => 'cards',
                'limit' => 2,
                'order' => '_lft',
                'parent-page' => ['value' => $newsId, 'label' => 'News'],
            ]],
            ['id' => 'home-contact', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Request a private consultation',
            ]],
            ['id' => Utils::uid(), 'type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        $meta = [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => 'Estate Group presents residential, rental, and commercial properties with clear details and current market news.',
                'keywords' => 'estate, properties, property news, rentals, commercial real estate, premium residences',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => 'Estate | Premium Real Estate Portal',
                'description' => 'Residential, rental, and commercial properties with practical details and current market news.',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        $page = Page::forceCreate( [
            'lang' => 'en',
            'name' => 'Home',
            'title' => 'Estate | Properties and Real Estate News',
            'path' => '',
            'tag' => 'root',
            'theme' => $this->theme,
            'status' => 1,
            'cache' => 5,
            'editor' => 'demo',
            'config' => $config,
            'meta' => $meta,
            'content' => $content,
        ] );

        $version = $page->versions()->forceCreate( [
            'lang' => 'en',
            'data' => [
                'name' => 'Home',
                'title' => 'Estate | Properties and Real Estate News',
                'path' => '',
                'tag' => 'root',
                'domain' => '',
                'theme' => $this->theme,
                'status' => 1,
                'cache' => 5,
            ],
            'aux' => ['config' => $config, 'meta' => $meta, 'content' => $content],
            'editor' => 'demo',
        ] );

        $version->files()->attach( array_unique( array_merge( [$fileId], $this->ids( $config ), $this->ids( $content ), $this->ids( $meta ) ) ) );
        $version->elements()->attach( $elementId );
        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->publish( $version );

        return $page;
    }


    protected function ids( mixed $value ) : array
    {
        $ids = [];

        if( is_array( $value ) )
        {
            if( ( $value['type'] ?? null ) === 'file' && is_string( $value['id'] ?? null )
                && !isset( $value['data'] ) && !isset( $value['group'] )
            ) {
                $ids[] = $value['id'];
            }

            foreach( $value as $item ) {
                $ids = array_merge( $ids, $this->ids( $item ) );
            }
        }

        return $ids;
    }


    protected function img( string $key ) : string
    {
        [$photo, $name, $desc] = self::PHOTOS[$key];
        return $this->image( $photo, $name, $desc );
    }


    protected function logoFile() : string
    {
        if( !isset( $this->logoFile ) )
        {
            $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 260 64" role="img" aria-labelledby="title desc">
  <title id="title">Estate logo</title>
  <desc id="desc">Estate wordmark with a geometric skyline icon</desc>
  <g fill="none" fill-rule="evenodd">
    <rect x="4" y="29" width="8" height="23" fill="#242424"/>
    <rect x="15" y="21" width="8" height="31" fill="#242424"/>
    <rect x="26" y="12" width="8" height="40" fill="#242424"/>
    <rect x="37" y="25" width="8" height="27" fill="#242424"/>
    <rect x="48" y="33" width="8" height="19" fill="#D00000"/>
    <path d="M2 55h56" stroke="#242424" stroke-width="2"/>
    <g fill="#242424">
      <path d="M72 14h22v6H79v9h13v6H79v9h15v6H72z"/>
      <path d="M101 14h22v6h-15v9h15v21h-22v-6h15v-9h-15z"/>
      <path d="M130 14h25v6h-9v30h-7V20h-9z"/>
      <path d="M169 14h8l11 36h-7l-3-10h-11l-3 10h-7zm0 20h7l-3.5-12z" fill-rule="evenodd"/>
      <path d="M193 14h25v6h-9v30h-7V20h-9z"/>
      <path d="M225 14h22v6h-15v9h13v6h-13v9h15v6h-22z"/>
    </g>
  </g>
</svg>
SVG;

            $this->logoFile = $this->svgFile(
                $svg,
                'estate-logo.svg',
                'Estate logo',
                'Estate portfolio logo',
            );
        }

        return $this->logoFile;
    }


    protected function page( array $data, array $content, Page $parent, array $fileIds = [], array $meta = [] ) : Page
    {
        $elementId = $this->element();
        $fileId = $this->file();
        $description = self::DESCRIPTIONS[$data['path'] ?? ''] ?? $data['title'] ?? '';

        $meta = $data['meta'] ?? $meta ?: [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => $description,
                'keywords' => 'Estate Group, real estate, buy, rent, sell, commercial',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => $data['title'] ?? '',
                'description' => $description,
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        if( empty( $data['to'] ) ) {
            $content[] = ['id' => Utils::uid(), 'type' => 'reference', 'refid' => $elementId, 'group' => 'footer'];
        }

        $page = Page::forceCreate( $data + [
            'theme' => $this->theme,
            'editor' => 'demo',
            'meta' => $meta,
            'content' => $content,
        ] );
        $page->appendToNode( $parent )->save();

        $version = $page->versions()->forceCreate( [
            'lang' => $data['lang'] ?? 'en',
            'data' => array_diff_key( $data, ['content' => 1, 'meta' => 1, 'id' => 1] ) + [
                'domain' => '',
                'theme' => $this->theme,
            ],
            'aux' => ['meta' => $meta, 'content' => $content],
            'editor' => 'demo',
        ] );

        $version->elements()->attach( $elementId );
        $version->files()->attach( array_unique( array_merge( [$fileId], $fileIds, $this->ids( $content ), $this->ids( $meta ) ) ) );

        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->publish( $version );

        return $page;
    }


    protected function pages() : void
    {
        $page = new Page();
        $exposesId = $page->newUniqueId();
        $newsId = $page->newUniqueId();
        $propertiesId = $page->newUniqueId();
        $home = $this->home( $exposesId, $newsId, $propertiesId );

        $this->addProperties( $home, $exposesId, $propertiesId )
            ->addNews( $home, $newsId );
    }
}
