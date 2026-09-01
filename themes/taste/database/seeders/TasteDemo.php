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
 * Taste theme demo for the fictional Sumi noodle bar.
 */
class TasteDemo extends AbstractDemo
{
    /** @var array<string, string> Meta descriptions keyed by page path */
    private const DESCRIPTIONS = [
        'kitchen' => 'Meet the cooks and suppliers behind Sumi, a modern neighborhood noodle bar built around slow broth, live fire and seasonal produce.',
        'menu' => "Explore Sumi's noodle bowls, grilled small plates, sake and highballs on Kastanienallee in Prenzlauer Berg, with vegetarian and allergen notes.",
        'visit' => "Find Sumi at Kastanienallee 48 in Berlin's Prenzlauer Berg, check Tuesday–Sunday opening hours, and send a table or group dining request.",
    ];

    /**
     * Curated Unsplash photos used by the restaurant demo.
     *
     * @var array<string, array{0: string, 1: string, 2: array{en: string, de: string}}>
     */
    private const PHOTOS = [
        'broth' => ['photo-1731460202531-bf8389d565f7', 'Citrus shio ramen', [
            'en' => 'Overhead view of a clear noodle broth with egg, sliced meat, vegetables and sesame',
            'de' => 'Draufsicht auf eine klare Nudelsuppe mit Ei, Fleischscheiben, Gemüse und Sesam',
        ]],
        'counter' => ['photo-1552566626-52f8b828add9', 'Sumi dining room', [
            'en' => 'Warm contemporary restaurant dining room with timber tables and an open counter',
            'de' => 'Warmes modernes Restaurant mit Holztischen und einer offenen Theke',
        ]],
        'drinks' => ['photo-1551024709-8f23befc6f87', 'Sumi bar drinks', [
            'en' => 'Citrus drinks with ice and fresh garnish at a softly lit restaurant bar',
            'de' => 'Zitrusgetränke mit Eis und frischer Garnitur an einer sanft beleuchteten Restaurantbar',
        ]],
        'fire' => ['photo-1781160327123-dbb1d77f70ae', 'Sumi open kitchen', [
            'en' => 'Restaurant cook working beside stockpots in an open professional kitchen',
            'de' => 'Koch neben Suppentöpfen in einer offenen professionellen Restaurantküche',
        ]],
        'miso' => ['photo-1772217261042-0175d0b2fcb0', 'Charred miso ramen', [
            'en' => 'Rich ramen with pork, noodles, egg, seaweed and aromatic broth',
            'de' => 'Kräftige Ramen mit Schweinefleisch, Nudeln, Ei, Algen und aromatischer Brühe',
        ]],
        'sake' => ['photo-1756260853158-a63f71b4bff6', 'Junmai sake', [
            'en' => 'Black Junmai sake bottle with a clear tasting glass on a dark table',
            'de' => 'Schwarze Flasche Junmai-Sake mit klarem Probierglas auf einem dunklen Tisch',
        ]],
        'sencha' => ['photo-1767840272016-36dc5dd51f79', 'Cold-brew sencha', [
            'en' => 'Clear green tea beside fresh sencha leaves in a glass pot on a wooden counter',
            'de' => 'Klarer grüner Tee neben frischen Sencha-Blättern in einer Glaskanne auf einer Holztheke',
        ]],
        'tofu' => ['photo-1769031240699-e15f4818224a', 'Roasted tofu ramen', [
            'en' => 'Plant-based ramen with crisp tofu, vegetables, seaweed and citrus',
            'de' => 'Pflanzliche Ramen mit knusprigem Tofu, Gemüse, Algen und Zitrusfrüchten',
        ]],
        'plates' => ['photo-1504674900247-0877df9cc836', 'Sumi sharing plates', [
            'en' => 'Colorful small plates arranged for sharing across a restaurant table',
            'de' => 'Farbenfrohe kleine Gerichte zum Teilen auf einem Restauranttisch',
        ]],
        'team' => ['photo-1764408182167-043042cb086e', 'Sumi kitchen team', [
            'en' => 'Two restaurant cooks plating a bowl together at the kitchen pass',
            'de' => 'Zwei Köche richten gemeinsam eine Schale am Küchenpass an',
        ]],
    ];

    private string $element;
    private string $logoFile;


    /**
     * Creates the story page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addKitchen( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Kitchen',
            'title' => 'The Sumi Kitchen | Broth, Fire and Good Produce',
            'path' => 'kitchen',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'The work behind the bowl',
                'subtitle' => 'Our kitchen',
                'text' => 'Broth starts before the room opens. Noodles are portioned for each service, vegetables meet the grill to order, and every garnish has a reason to be there.',
                'url' => '/menu',
                'button' => 'See the menu',
                'url-alternative' => '/visit',
                'button-alternative' => 'Visit Sumi',
                'files' => [['id' => $this->img( 'fire' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'team' ), 'type' => 'file'],
                'position' => 'start',
                'ratio' => '1-2',
                'text' => "## Built for the neighborhood\n\nSumi began with a six-seat counter, one stockpot and a short menu written around what the market could supply each week. The room is larger now, but the decisions still happen close to the stove.\n\nChef Mara Lin develops the broths with Kenji Ito, whose family has made noodles for three generations. Vegetables come from farms within a morning's drive; seafood arrives whole and is portioned in the kitchen. Nothing is added merely to fill the bowl.",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'What stays constant',
                'cards' => [
                    ['title' => 'Broth takes time', 'text' => 'Chicken and kombu stock simmers for twelve hours. The plant broth rests overnight before it is clarified and seasoned.'],
                    ['title' => 'Fire adds the edge', 'text' => 'Corn, cabbage, mushrooms and pork meet the grill just before serving, bringing smoke without masking the broth.'],
                    ['title' => 'The menu follows the season', 'text' => 'Three core bowls remain. Small plates and one monthly noodle bowl change with the produce arriving at the kitchen door.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'From across the counter',
                'items' => [
                    ['name' => 'Leonie Weiss', 'role' => 'Vegetable grower', 'text' => 'The kitchen asks what is at its best before deciding how to use it. That sounds simple, but it changes the whole relationship.'],
                    ['name' => 'Kenji Ito', 'role' => 'Noodle maker', 'text' => 'We adjusted the noodle three times until it held the broth without losing its bite. Sumi notices details that many rooms rush past.'],
                    ['name' => 'Mara Lin', 'role' => 'Chef and co-owner', 'text' => 'A bowl should feel complete after the last spoonful. Rich enough to satisfy, clear enough that you still taste every ingredient.'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the menu page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addMenu( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Menu',
            'title' => 'Sumi Menu | Noodles, Small Plates and Drinks',
            'path' => 'menu',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Choose the bowl that fits tonight',
                'subtitle' => 'Noodles · plates · drinks',
                'text' => 'Three long-cooked broths, one seasonal bowl, a handful of plates for the table, and cold drinks mixed to cut through the warmth.',
                'url' => '#bowls',
                'button' => 'See the bowls',
                'url-alternative' => '/visit',
                'button-alternative' => 'Plan your visit',
                'files' => [['id' => $this->img( 'plates' ), 'type' => 'file']],
            ]],
            ['id' => 'bowls', 'type' => 'pricing', 'group' => 'main', 'data' => [
                'title' => 'The bowls',
                'text' => 'Noodles are made daily. Add a marinated egg for €2 or extra noodles for €3.',
                'items' => $this->bowls(),
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Small plates',
                'header' => 'row',
                'table' => [
                    ['Plate', 'What is on it', 'Price'],
                    ['Crisp chicken', 'Rice-flour crust, Sansho pepper, fermented chili', '€9'],
                    ['Burnt cabbage', 'Sesame cream, lime leaf, crispy shallot', '€8'],
                    ['Market pickles', 'Cucumber, radish, ginger and rice vinegar', '€6'],
                    ['Pork gyoza', 'Six dumplings, black vinegar and scallion oil', '€9'],
                    ['Soft tofu', 'Tomato dashi, shiso and toasted sesame', '€7'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'At the bar',
                'cards' => [
                    ['title' => 'Yuzu highball · €10', 'text' => 'Japanese whisky, yuzu, soda and a pinch of sea salt.', 'file' => ['id' => $this->img( 'drinks' ), 'type' => 'file']],
                    ['title' => 'Cold-brew sencha · €5', 'text' => 'Green tea steeped cold for eight hours and served over clear ice.', 'file' => ['id' => $this->img( 'sencha' ), 'type' => 'file']],
                    ['title' => 'Junmai sake · from €7', 'text' => 'A short rotating list poured by the glass, carafe or bottle.', 'file' => ['id' => $this->img( 'sake' ), 'type' => 'file']],
                ],
            ]],
            ['id' => 'dietary-notes', 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Dietary notes',
                'items' => [
                    ['title' => 'Which bowls are vegetarian?', 'text' => 'Roasted Tofu is fully plant-based. The seasonal bowl is vegetarian whenever the kitchen can build it without compromise; ask the team for the current version.'],
                    ['title' => 'What about gluten and other allergens?', 'text' => 'Broths can be served with rice instead of wheat noodles. Tell the counter team before ordering about egg, sesame, soy, allium or other allergies. The open kitchen handles all of them, so Sumi cannot promise a coeliac-safe or allergen-free meal.'],
                    ['title' => 'How spicy is Charred Miso?', 'text' => 'The standard bowl has a steady warmth rather than sharp heat. Extra fermented chili is served on the side.'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the visit page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addVisit( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Visit',
            'title' => 'Visit Sumi | Hours, Location and Table Requests',
            'path' => 'visit',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Your seat is closer than you think',
                'subtitle' => 'Visit Sumi',
                'text' => "Find us at Kastanienallee 48 in Prenzlauer Berg. Walk in for counter seats or send a table request for groups of four or more. The kitchen serves the full menu until thirty minutes before closing.",
                'url' => '#table-request',
                'button' => 'Request a table',
                'url-alternative' => '/menu',
                'button-alternative' => 'Read the menu',
                'files' => [['id' => $this->img( 'counter' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Opening hours',
                'header' => 'row',
                'table' => [
                    ['Day', 'Lunch', 'Dinner'],
                    ['Monday', 'Closed', 'Closed'],
                    ['Tuesday–Thursday', '12:00–15:00', '17:30–22:30'],
                    ['Friday–Saturday', '12:00–15:00', '17:30–23:30'],
                    ['Sunday', '12:00–16:00', 'Closed'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'map', 'group' => 'main', 'data' => [
                'title' => 'Find Us',
                'text' => "**Address**\nKastanienallee 48 · 10435 Berlin\nTwo minutes from U Eberswalder Straße.\n\n**Call**\n+49 30 555 01 48 · answered Tuesday to Saturday from 11:00.\n\nFor eight to sixteen guests, the back table can be served family-style with advance notice.",
                'location' => [
                    'latitude' => 52.538456,
                    'longitude' => 13.409564,
                    'zoom' => 16,
                ],
                'button' => 'Open in OpenStreetMap',
            ]],
            ['id' => 'table-request', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Send a table request',
            ]],
        ], $home );

        return $this;
    }


    /**
     * Returns the three signature bowl definitions.
     *
     * @return array<int, array<string, mixed>> Pricing items
     */
    protected function bowls() : array
    {
        return [
            [
                'name' => 'Charred Miso',
                'prices' => [['id' => 'miso', 'amount' => 17, 'label' => '€17']],
                'text' => 'Deep chicken broth, three misos and smoke from the grill.',
                'features' => "- Charred pork shoulder\n- Sweet corn and cabbage\n- Marinated egg\n- Fermented chili",
                'file' => ['id' => $this->img( 'miso' ), 'type' => 'file'],
                'url' => '/menu#bowls',
                'button' => 'View full menu',
                'highlight' => true,
                'badge' => 'House bowl · gluten-free option',
            ],
            [
                'name' => 'Citrus Shio',
                'prices' => [['id' => 'shio', 'amount' => 16, 'label' => '€16']],
                'text' => 'Clear chicken and kombu broth lifted with yuzu and sea salt.',
                'features' => "- Braised pork\n- Scallions and sesame\n- Marinated egg\n- Yuzu peel",
                'file' => ['id' => $this->img( 'broth' ), 'type' => 'file'],
                'url' => '/menu#bowls',
                'button' => 'View full menu',
                'badge' => 'Gluten-free option',
            ],
            [
                'name' => 'Roasted Tofu',
                'prices' => [['id' => 'tofu', 'amount' => 15, 'label' => '€15']],
                'text' => 'A creamy plant broth with roasted tomato depth and sesame.',
                'features' => "- Crisp tofu\n- Grilled peppers and greens\n- Lemon and seaweed\n- Black garlic oil",
                'file' => ['id' => $this->img( 'tofu' ), 'type' => 'file'],
                'url' => '/menu#bowls',
                'button' => 'View full menu',
                'badge' => 'Vegan · gluten-free option',
            ],
        ];
    }


    /**
     * Creates the shared Sumi footer and returns its ID.
     *
     * @return string Element ID
     */
    protected function element() : string
    {
        if( !isset( $this->element ) )
        {
            $cards = [
                ['title' => 'Sumi', 'text' => "A Prenzlauer Berg noodle bar built around broth, live fire and a good seat at the counter."],
                ['title' => 'Eat', 'text' => "- [Noodle and drinks menu](/menu)\n- [How the kitchen works](/kitchen)\n- [Dietary notes](/menu#dietary-notes)"],
                ['title' => 'Visit', 'text' => "- [Opening hours and location](/visit)\n- [Send a table request](/visit#table-request)\n- +49 30 555 01 48"],
                ['title' => 'Address', 'text' => "Kastanienallee 48\n10435 Berlin · Prenzlauer Berg\n\nTuesday–Sunday from 12:00"],
            ];

            $element = Element::forceCreate( [
                'lang' => 'en',
                'type' => 'cards',
                'name' => 'Sumi footer',
                'data' => ['type' => 'cards', 'data' => ['title' => 'Come hungry', 'cards' => $cards]],
                'editor' => 'demo',
            ] );

            $version = $element->versions()->forceCreate( [
                'lang' => 'en',
                'data' => [
                    'lang' => 'en',
                    'type' => 'cards',
                    'name' => 'Sumi footer',
                    'data' => ['title' => 'Come hungry', 'cards' => $cards],
                ],
                'editor' => 'demo',
            ] );

            $element->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $element->publish( $version );
            $this->element = (string) $element->refresh()->id;
        }

        return $this->element;
    }


    /**
     * Returns the ID of the primary restaurant image.
     *
     * @return string File ID
     */
    protected function file() : string
    {
        return $this->img( 'miso' );
    }


    /**
     * Creates the Sumi home page and returns it.
     *
     * @return Page Home page
     */
    protected function home() : Page
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
            'taste::restaurant' => [
                'type' => 'taste::restaurant',
                'files' => [],
                'data' => [
                    'name' => 'Sumi Noodle Bar',
                    'street-address' => 'Kastanienallee 48',
                    'postal-code' => '10435',
                    'locality' => 'Berlin',
                    'country' => 'DE',
                    'cuisine' => 'Japanese, Ramen',
                    'menu' => '/menu',
                    'price-range' => '€€',
                    'telephone' => '+49 30 555 01 48',
                    'hours' => [
                        ['id' => 'tue-lunch', 'day' => 'Tuesday', 'opens' => '12:00', 'closes' => '15:00'],
                        ['id' => 'tue-dinner', 'day' => 'Tuesday', 'opens' => '17:30', 'closes' => '22:30'],
                        ['id' => 'wed-lunch', 'day' => 'Wednesday', 'opens' => '12:00', 'closes' => '15:00'],
                        ['id' => 'wed-dinner', 'day' => 'Wednesday', 'opens' => '17:30', 'closes' => '22:30'],
                        ['id' => 'thu-lunch', 'day' => 'Thursday', 'opens' => '12:00', 'closes' => '15:00'],
                        ['id' => 'thu-dinner', 'day' => 'Thursday', 'opens' => '17:30', 'closes' => '22:30'],
                        ['id' => 'fri-lunch', 'day' => 'Friday', 'opens' => '12:00', 'closes' => '15:00'],
                        ['id' => 'fri-dinner', 'day' => 'Friday', 'opens' => '17:30', 'closes' => '23:30'],
                        ['id' => 'sat-lunch', 'day' => 'Saturday', 'opens' => '12:00', 'closes' => '15:00'],
                        ['id' => 'sat-dinner', 'day' => 'Saturday', 'opens' => '17:30', 'closes' => '23:30'],
                        ['id' => 'sun-lunch', 'day' => 'Sunday', 'opens' => '12:00', 'closes' => '16:00'],
                    ],
                ],
            ],
        ];

        $content = [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Broth, fire, and a seat at the counter',
                'subtitle' => 'Walk in with 1–3 guests · Tue–Sun from 12:00',
                'text' => 'Slow broth and springy ramen, grilled small plates and cold drinks in Prenzlauer Berg, from lunch through late counter seats.',
                'url' => '/menu',
                'button' => 'See the menu',
                'url-alternative' => '/visit#table-request',
                'button-alternative' => 'Group table requests',
                'files' => [['id' => $this->img( 'miso' ), 'type' => 'file']],
            ]],
            ['id' => 'menu-highlights', 'type' => 'pricing', 'group' => 'main', 'data' => [
                'title' => 'Three bowls, three moods',
                'text' => 'The core menu stays short so the kitchen can give each broth the time it needs. Gluten-free options use rice instead of wheat noodles; the open kitchen is not coeliac-safe.',
                'items' => $this->bowls(),
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'counter' ), 'type' => 'file'],
                'position' => 'grid-end',
                'ratio' => '1-2',
                'text' => "## A neighborhood room on Kastanienallee\n\nThe Prenzlauer Berg counter is open for solo dinners, quick lunches and the extra bowl that follows a late drink. Tables sit close enough for sharing plates without turning the room formal.\n\nSumi cooks with Japanese technique and ingredients grown around Berlin. Broths run deep, garnishes stay bright, and the drinks list is short enough to read before your noodles arrive.\n\n[Meet the kitchen](/kitchen)",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Come when it suits you',
                'cards' => [
                    ['title' => 'Lunch', 'text' => 'A focused menu from noon: three bowls, two small plates and a house tea. Most tables turn in under an hour.'],
                    ['title' => 'Bar hour', 'text' => 'From 17:30, start with gyoza, crisp chicken, sake or a yuzu highball while the broth comes back to service.'],
                    ['title' => 'Late bowls', 'text' => 'Friday and Saturday dinner runs until 23:30, with the complete menu served until thirty minutes before close.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'What guests remember',
                'items' => [
                    ['name' => 'Nora K.', 'role' => 'Berlin', 'text' => 'The miso bowl is rich without becoming heavy. I finished the broth, then ordered the cabbage for the table.'],
                    ['name' => 'David M.', 'role' => 'Hamburg', 'text' => 'I came alone on a Friday and the counter felt easy, not like a compromise. The yuzu highball was exactly right with the shio.'],
                    ['name' => 'Amira S.', 'role' => 'Potsdam', 'text' => 'Our vegetarian friend had the most interesting bowl at the table. Nobody was handed a reduced version of dinner.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Before you arrive',
                'items' => [
                    ['title' => 'Do you keep tables for walk-ins?', 'text' => 'Yes. The counter and roughly half of the dining room remain available for walk-ins at every service.'],
                    ['title' => 'Can I reserve for a group?', 'text' => 'Table requests are accepted for four to sixteen guests. Send the date, time and group size through the visit page.'],
                    ['title' => 'Is there a plant-based bowl?', 'text' => 'Roasted Tofu is fully plant-based, including the noodles and seasoning oil. For gluten, egg, sesame, soy or allium adjustments, ask the counter team before ordering.'],
                    ['title' => 'Do you serve takeaway?', 'text' => 'A limited number of bowls can be collected before 18:30. Broth and noodles are packed separately and should be combined at home.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        $meta = [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => "Sumi is a noodle bar on Kastanienallee in Berlin's Prenzlauer Berg, serving slow broth, grilled small plates, sake and highballs from lunch into late dinner.",
                'keywords' => 'Sumi, noodle bar Berlin, ramen Prenzlauer Berg, Kastanienallee restaurant, Japanese food, vegetarian noodles, sake, highballs',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => 'Sumi Noodle Bar | Prenzlauer Berg, Berlin',
                'description' => "Slow broth, grilled small plates, sake and highballs on Kastanienallee in Berlin's Prenzlauer Berg.",
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        $page = Page::forceCreate( [
            'lang' => 'en',
            'name' => 'Home',
            'title' => 'Sumi Noodle Bar | Prenzlauer Berg, Berlin',
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
                'lang' => 'en',
                'name' => 'Home',
                'title' => 'Sumi Noodle Bar | Prenzlauer Berg, Berlin',
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


    /**
     * Returns file IDs referenced anywhere in the given data.
     *
     * @param mixed $value Content or metadata
     * @return array<int, string> File IDs
     */
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


    /**
     * Returns the file ID for a curated demo photo.
     *
     * @param string $key Photo key from self::PHOTOS
     * @return string File ID
     */
    protected function img( string $key ) : string
    {
        [$photo, $name, $desc] = self::PHOTOS[$key];
        return $this->image( $photo, $name, $desc );
    }


    /**
     * Creates the Sumi SVG logo and returns its file ID.
     *
     * @return string File ID
     */
    protected function logoFile() : string
    {
        if( !isset( $this->logoFile ) )
        {
            $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 360 92" role="img" aria-labelledby="title desc">
  <title id="title">Sumi logo</title>
  <desc id="desc">Sumi wordmark beside a simple bowl and rising chopsticks</desc>
  <g fill="none" fill-rule="evenodd">
    <path d="M18 50h62c-3 18-14 27-31 27S21 68 18 50Z" fill="#39D6B4"/>
    <path d="M29 42h40M38 11l12 31M59 11 49 42" stroke="#FF6B7A" stroke-width="6" stroke-linecap="round"/>
    <text x="101" y="67" fill="#F7FAFC" font-family="ui-rounded, Avenir Next, Segoe UI, sans-serif" font-size="58" font-weight="800" letter-spacing="5">SUMI</text>
  </g>
</svg>
SVG;

            $this->logoFile = $this->svgFile(
                $svg,
                'sumi-logo.svg',
                'Sumi logo',
                'Sumi wordmark beside a simple bowl and rising chopsticks',
                true,
            );
        }

        return $this->logoFile;
    }


    /**
     * Creates a Taste demo page below the given parent and returns it.
     *
     * @param array<string, mixed> $data Page attributes
     * @param array<int, array<string, mixed>> $content Content elements
     * @param Page $parent Parent page
     * @return Page Created page
     */
    protected function page( array $data, array $content, Page $parent ) : Page
    {
        $elementId = $this->element();
        $contentIds = $this->ids( $content );
        $fileId = $contentIds[0] ?? $this->file();
        $description = self::DESCRIPTIONS[$data['path'] ?? ''] ?? $data['title'] ?? '';
        $meta = [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => $description,
                'keywords' => 'Sumi, Berlin noodle bar, restaurant, ramen, food, drinks, Japanese kitchen',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => $data['title'] ?? '',
                'description' => $description,
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        $content[] = ['id' => Utils::uid(), 'type' => 'reference', 'refid' => $elementId, 'group' => 'footer'];

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
        $version->files()->attach( array_unique( array_merge( [$fileId], $contentIds, $this->ids( $meta ) ) ) );

        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->publish( $version );

        return $page;
    }


    /**
     * Builds the Taste restaurant demo page tree.
     */
    protected function pages() : void
    {
        $home = $this->home();

        $this->addMenu( $home )
            ->addKitchen( $home )
            ->addVisit( $home );
    }
}
