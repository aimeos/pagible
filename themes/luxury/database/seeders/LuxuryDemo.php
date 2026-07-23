<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Database\Seeders;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Validation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


/**
 * Luxury theme demo for the fictional Avelune boutique retreat.
 */
class LuxuryDemo extends AbstractDemo
{
    /** @var array<string, string> Meta descriptions keyed by page path */
    private const DESCRIPTIONS = [
        'stay' => 'Explore Avelune Retreat rooms and suites, from quiet garden rooms to a private limestone house with its own pool and terrace.',
        'wellness' => 'Discover Avelune wellness rituals, restorative treatments, movement classes, thermal bathing, and private retreat programmes in Mallorca.',
        'table' => 'Meet the growers, cooks, and island ingredients behind Avelune Table, with seasonal menus served in the courtyard and olive grove.',
        'experiences' => 'Plan private sails, guided ridge walks, ceramic studio visits, market mornings, and unhurried days around the Avelune estate.',
        'journal' => 'Read the Avelune Journal: field notes on sleep, bathing, Mallorca landscapes, seasonal cooking, and the quiet details of the retreat.',
        'why-rest-begins-before-bedtime' => 'Avelune sleep practitioner Mara Vidal explains how daylight, movement, food, and evening habits prepare the body for restorative sleep.',
        'the-old-grove-at-first-light' => 'Walk through Avelune olive grove at first light and meet the family restoring its dry-stone terraces, soil, and century-old trees.',
        'bathing-as-a-daily-ritual' => 'A practical guide to contrast bathing, mineral soaks, steam, and the gentle sequence used in the Avelune bath house.',
        'a-kitchen-led-by-the-island' => 'How Avelune chef Ines Ferrer writes each menu around what nearby growers, fishers, and the retreat garden can offer that morning.',
        'guest-guide' => 'Practical information for an Avelune stay, including arrival, transfers, climate, clothing, dining, wellness, accessibility, and contact details.',
        'guest-guide/arrival' => 'Plan your journey to Avelune Retreat with airport transfer details, driving directions, check-in times, luggage help, and late-arrival guidance.',
        'guest-guide/wellness' => 'Prepare for the Avelune bath house and treatment rooms with booking advice, clothing guidance, health notes, and cancellation terms.',
        'reserve' => 'Request an Avelune stay, treatment programme, private retreat, or celebration and receive a considered itinerary from the reservations team.',
    ];

    /**
     * Curated Unsplash photos used across the Avelune demo.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const PHOTOS = [
        'bath' => ['photo-1600566753086-00f18fb6b3ea', 'Avelune stone bath', 'Quiet stone bathroom with warm natural light and a deep soaking bath'],
        'chef' => ['photo-1577219491135-ce391730fb2c', 'Avelune kitchen', 'Chef preparing the evening menu in a calm professional kitchen'],
        'coast' => ['photo-1507525428034-b723cf961d3e', 'Mallorca cove', 'Clear blue water meeting a quiet pale-sand cove'],
        'dining' => ['photo-1517248135467-4c7edcad34c4', 'Avelune dining room', 'Intimate restaurant dining room set for an evening meal'],
        'forest' => ['photo-1441974231531-c6227db76b6e', 'Pine woodland', 'Sunlight filtering through a quiet woodland path near the retreat'],
        'garden' => ['photo-1416879595882-3373a0480b5b', 'Avelune kitchen garden', 'Lush kitchen garden planted with herbs and seasonal vegetables'],
        'home' => ['photo-1566073771259-6a8506099945', 'Avelune Retreat', 'Boutique limestone retreat surrounded by palms and Mediterranean planting'],
        'massage' => ['photo-1540555700478-4be289fbecef', 'Restorative treatment', 'Peaceful spa treatment room prepared for a restorative massage'],
        'plate' => ['photo-1414235077428-338989a2e8c0', 'Seasonal Avelune plate', 'Carefully composed seasonal dish served on handmade ceramic tableware'],
        'pool' => ['photo-1571896349842-33c89424de2d', 'Avelune pool', 'Long outdoor pool beside a quiet Mediterranean hotel terrace'],
        'spa' => ['photo-1544161515-4ab6ce6db874', 'Avelune bath house', 'Warm spa interior with towels, natural materials, and soft candlelight'],
        'suite' => ['photo-1611892440504-42a792e24d32', 'Avelune suite', 'Refined hotel suite with linen bedding, natural wood, and warm neutral tones'],
        'tea' => ['photo-1544787219-7f47ccb76574', 'Herbal infusion', 'Fresh herbal infusion served in a simple ceramic cup'],
        'terrace' => ['photo-1600607687939-ce8a6c25118c', 'Private suite terrace', 'Sheltered terrace opening from a refined contemporary interior'],
        'walk' => ['photo-1551632811-561732d1e306', 'Tramuntana walk', 'Walker following a mountain path through a dramatic Mediterranean landscape'],
        'yoga' => ['photo-1506126613408-eca07ce68773', 'Morning movement', 'Quiet morning yoga practice in soft natural light'],
    ];

    private string $element;
    private string $logoFile;
    /** @var array<string, string> File IDs for fixed-ratio pricing images */
    private array $pricingImages = [];
    /** @var array<string, string> File IDs for fixed-ratio slideshow images */
    private array $slideImages = [];


    /**
     * Creates the journal and its articles below the home page.
     */
    protected function addBlog( Page $home, string $journalId ) : static
    {
        $journal = $this->page( [
            'id' => $journalId,
            'lang' => 'en',
            'name' => 'Journal',
            'title' => 'The Avelune Journal',
            'path' => 'journal',
            'tag' => 'blog',
            'type' => 'blog',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Notes from a slower place',
                'subtitle' => 'The Avelune Journal',
                'text' => 'Field notes from the grove, bath house, kitchen, and mountains—written by the people who tend this place and know its seasons.',
                'files' => [['id' => $this->img( 'forest' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'blog', 'group' => 'main', 'data' => [
                'title' => 'Latest stories',
                'layout' => 'default',
                'limit' => 4,
                'order' => '_lft',
                'parent-page' => ['value' => $journalId, 'label' => 'Journal'],
            ]],
        ], $home );

        $this->page( [
            'lang' => 'en',
            'name' => 'Why rest begins before bedtime',
            'title' => 'Why Rest Begins Before Bedtime',
            'path' => 'why-rest-begins-before-bedtime',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'Why rest begins before bedtime',
                "A good night is shaped long before the lights go out. Morning daylight sets the body's clock. Movement builds a useful need for sleep. The timing of coffee, dinner, and the last difficult conversation all leave a trace.\n\nMara Vidal, Avelune's sleep practitioner, starts with the day rather than the pillow.",
                $this->img( 'suite' )
            ),
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Give the day a clear edge',
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'yoga' ), 'type' => 'file'],
                'position' => 'end',
                'ratio' => '1-2',
                'text' => "Sleep responds well to contrast. Step outside soon after waking, even on a grey morning. Keep demanding movement to daylight hours. Let the last two hours become recognisably different: lower light, a smaller meal, warmer water, fewer decisions.\n\nAt Avelune, evening turndown includes a carafe of lemon verbena and a card with the next day's sunrise. The details are modest. Their consistency is what matters.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'A gentle evening sequence',
                'header' => 'row',
                'table' => [
                    ['Time before bed', 'Practice', 'Purpose'],
                    ['Three hours', 'Finish dinner and alcohol', 'Allow digestion and temperature to settle'],
                    ['Two hours', 'Dim the room and set tomorrow aside', 'Reduce stimulation and unfinished decisions'],
                    ['One hour', 'Warm bath or shower', 'Support the natural cooling that follows'],
                    ['Twenty minutes', 'Read, breathe, or write by low light', 'Give attention one quiet place to rest'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "There is no perfect ritual, and sleep should not become another performance. Choose one or two cues that fit ordinary life. Keep them long enough for the body to recognise them, then leave the clock alone.",
            ]],
            $this->articleHero( 'Build a stay around deeper rest', 'Our restorative programmes pair private sleep consultations with movement, bathing, and evenings kept deliberately clear.' ),
        ], $journal );

        $this->page( [
            'lang' => 'en',
            'name' => 'The old grove at first light',
            'title' => 'The Old Grove at First Light',
            'path' => 'the-old-grove-at-first-light',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'The old grove at first light',
                "Before breakfast, the olive terraces hold the night's cool air. Thrushes move through the carob trees and the old irrigation channel begins to catch the sun. This is when Tomas Riera walks the grove, checking stone walls, new shoots, and signs of water stress.\n\nHis family has tended these slopes for four generations. The work now is as much restoration as harvest.",
                $this->img( 'garden' )
            ),
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'walk' ), 'type' => 'file'],
                'position' => 'start',
                'ratio' => '1-2',
                'text' => "## Stone, shade, and patient work\n\nDry-stone walls slow rain before it leaves the slope. Ground cover holds soil around the roots. Pruning opens each tree to air while keeping enough shade for hotter summers. None of the changes is dramatic on its own. Across twelve hectares, they determine whether the grove can thrive without heavy irrigation.\n\nGuests can join Tomas on Tuesday and Friday mornings. The walk ends with bread, crushed tomato, local salt, and oils from the previous three harvests.",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'What the grove teaches',
                'cards' => [
                    ['title' => 'Read the ground', 'text' => 'Wild plants reveal compacted soil, hidden damp, and where insects are returning.'],
                    ['title' => 'Keep the walls working', 'text' => 'Local stone is reset by hand so winter rain slows and sinks into each terrace.'],
                    ['title' => 'Harvest by tree', 'text' => 'Fruit is picked in small lots, then pressed the same day to preserve its character.'],
                ],
            ]],
            $this->articleHero( 'Walk the terraces with Tomas', 'Reserve the first-light grove walk privately or join the small weekly group during your stay.' ),
        ], $journal );

        $this->page( [
            'lang' => 'en',
            'name' => 'Bathing as a daily ritual',
            'title' => 'Bathing as a Daily Ritual',
            'path' => 'bathing-as-a-daily-ritual',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'Bathing as a daily ritual',
                "The bath house is not a challenge to endure. Heat, water, and rest should leave the nervous system quieter, not conquered. Avelune's sequence is short enough to repeat and flexible enough to follow how you feel that day.",
                $this->img( 'bath' )
            ),
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'spa' ), 'type' => 'file'],
                'position' => 'end',
                'ratio' => '1-2',
                'text' => "## The house sequence\n\nBegin with ten quiet minutes in the warm mineral pool. Move to steam only while breathing remains easy. Cool with the courtyard shower, starting at the feet and hands, then rest wrapped in linen with water or herbal tea.\n\nOne round is enough. A second can feel good after a long walk, but intensity adds little. Leave at least thirty minutes before a treatment or meal.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Bath house temperatures',
                'header' => 'row+col',
                'table' => [
                    ['Space', 'Temperature', 'Suggested time', 'Character'],
                    ['Mineral pool', '36–37°C', 'Ten minutes', 'Warm, buoyant, and gently saline'],
                    ['Herbal steam', '42–45°C', 'Five to eight minutes', 'Humid heat with rosemary and pine'],
                    ['Courtyard shower', 'Cool to cold', 'Thirty seconds', 'Brief and adjustable'],
                    ['Rest room', '22°C', 'At least ten minutes', 'Low light, linen beds, and quiet'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Before bathing',
                'items' => [
                    ['title' => 'Should I eat first?', 'text' => 'A light snack is fine. Leave at least ninety minutes after a full meal before using the heated spaces.'],
                    ['title' => 'Is cold water required?', 'text' => 'No. A cool shower or simply resting outside provides enough contrast for many guests.'],
                    ['title' => 'Who should ask for guidance?', 'text' => 'Tell the wellness team about pregnancy, heart or blood-pressure conditions, recent surgery, or heat sensitivity before bathing.'],
                    ['title' => 'How much time should I allow?', 'text' => 'Set aside forty-five to sixty minutes for one bathing round, a cool shower, and an unhurried rest afterwards.'],
                ],
            ]],
            $this->articleHero( 'Take time in the bath house', 'Bathing is included with every stay; private evening access can be reserved for two.' ),
        ], $journal );

        $this->page( [
            'lang' => 'en',
            'name' => 'A kitchen led by the island',
            'title' => 'A Kitchen Led by the Island',
            'path' => 'a-kitchen-led-by-the-island',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'A kitchen led by the island',
                "Chef Ines Ferrer does not begin the menu with a theme. She begins with three calls: the market gardener in Soller, the small boat landing in Port de Pollenca, and the baker whose rye starter is older than the restaurant.\n\nBy half past eight, lunch has a shape. Dinner follows when the garden team brings in the day's herbs and fruit.",
                $this->img( 'plate' )
            ),
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'chef' ), 'type' => 'file'],
                'position' => 'start',
                'ratio' => '1-2',
                'text' => "## Enough technique to reveal the ingredient\n\nAvelune food is precise without being fussy. Fish is grilled over olive wood and served with bitter leaves. Tomatoes are salted early, then dressed with their own water. Almonds appear fresh, toasted, fermented, and pressed depending on the month.\n\nThe kitchen keeps a small larder of preserved lemons, green olives, dried peppers, and broths so winter menus still belong to this landscape.",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Three close relationships',
                'cards' => [
                    ['title' => 'Can Llorenc garden', 'text' => 'Tender leaves, tomatoes, aubergines, and citrus picked for the kitchen six mornings a week.'],
                    ['title' => 'Port fishers', 'text' => 'Red mullet, squid, prawns, and rockfish bought from small day boats when the sea permits.'],
                    ['title' => 'The Avelune grove', 'text' => 'Olive oil, almonds, figs, fennel, rosemary, and carob gathered within walking distance of the table.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Guests are welcome to visit the kitchen garden with a cook at 10:30 each Wednesday. There is no formal lesson; the morning follows what needs picking, tasting, or preserving.",
            ]],
            $this->articleHero( 'Take a seat at Avelune Table', 'Breakfast, lunch, and dinner are open to resident guests, with a few evening tables held for neighbours.' ),
        ], $journal );

        return $this;
    }


    /**
     * Creates the dining page below the home page.
     */
    protected function addDining( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Table',
            'title' => 'Avelune Table | Seasonal Dining in Mallorca',
            'path' => 'table',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'The island decides the menu',
                'subtitle' => 'Avelune Table',
                'text' => 'Breakfast under the vines, lunch from the garden, and an evening menu shaped by the small boats and farms we know by name.',
                'url' => '/reserve',
                'button' => 'Request a table',
                'files' => [['id' => $this->img( 'dining' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'chef' ), 'type' => 'file'],
                'position' => 'end',
                'ratio' => '1-2',
                'text' => "## Cooked close to the source\n\nInes Ferrer and her team write the menu after speaking with growers and fishers each morning. The cooking is Mediterranean in rhythm rather than decoration: vegetables at the centre, fish bought in small quantities, meat used thoughtfully, and fruit served when it is ready.\n\nResident guests can eat simply or follow the full tasting menu. Vegetarian menus need no advance notice; vegan and allergy-aware menus are best arranged before arrival.",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Through the day',
                'cards' => [
                    ['title' => 'Breakfast', 'text' => '07:30–11:00. Fruit, warm bread, eggs, sheep yoghurt, garden herbs, and coffee roasted in Palma.'],
                    ['title' => 'Lunch', 'text' => '12:30–15:00. A short terrace menu of salads, grilled fish, rice, and dishes from the wood oven.'],
                    ['title' => 'Dinner', 'text' => '19:00–22:00. Four or seven courses, with a daily vegetarian menu and island-led wine pairing.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'A late-summer evening',
                'header' => 'row',
                'table' => [
                    ['Course', 'Plate', 'Pairing'],
                    ['Garden', 'Fig leaf, young almond, cucumber, and fennel pollen', 'Dry Malvasia'],
                    ['Sea', 'Charred red mullet, tomato water, and wild caper', 'Giró Ros rosé'],
                    ['Grove', 'Olive-oil rice with courgette flower and aged sheep cheese', 'Callet red'],
                    ['Orchard', 'Roasted peach, bay custard, and green almond', 'Herbal infusion or sweet Moscatel'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'slideshow', 'group' => 'main', 'data' => [
                'title' => 'From garden to table',
                'main' => true,
                'files' => [
                    ['id' => $this->slideImg( 'garden' ), 'type' => 'file'],
                    ['id' => $this->slideImg( 'chef' ), 'type' => 'file'],
                    ['id' => $this->slideImg( 'plate' ), 'type' => 'file'],
                    ['id' => $this->slideImg( 'dining' ), 'type' => 'file'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Dining details',
                'items' => [
                    ['title' => 'Is breakfast included?', 'text' => 'Breakfast is included in every room and suite rate and may be taken in the courtyard or in your room.'],
                    ['title' => 'Can non-residents dine?', 'text' => 'A small number of dinner reservations are released to non-residents seven days ahead.'],
                    ['title' => 'How are allergies handled?', 'text' => 'Tell us before arrival. The kitchen will explain what can be adapted and where cross-contact cannot be ruled out.'],
                    ['title' => 'Is there a dress code?', 'text' => 'No formal dress code. The courtyard is stone, so comfortable footwear is useful.'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the experience page below the home page.
     */
    protected function addExperiences( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Experiences',
            'title' => 'Avelune Experiences | Mallorca at an Unhurried Pace',
            'path' => 'experiences',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Go out slowly. Come back restored.',
                'subtitle' => 'Beyond the estate',
                'text' => 'Walk with people who know the mountain paths, sail with the morning wind, or spend an afternoon learning one local material well.',
                'files' => [['id' => $this->img( 'coast' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Ways into the island',
                'cards' => [
                    ['title' => 'Private cove sail', 'text' => 'A half-day aboard a traditional llaut with a local skipper, breakfast, towels, and snorkelling gear.', 'file' => ['id' => $this->img( 'coast' ), 'type' => 'file']],
                    ['title' => 'Tramuntana on foot', 'text' => 'A guided ridge or village walk chosen for the weather, your pace, and how long you want to be out.', 'file' => ['id' => $this->img( 'walk' ), 'type' => 'file']],
                    ['title' => 'The working grove', 'text' => 'Walk the olive terraces with Tomas, taste three harvests, and share a simple breakfast under the trees.', 'file' => ['id' => $this->img( 'garden' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'forest' ), 'type' => 'file'],
                'position' => 'start',
                'ratio' => '1-2',
                'text' => "## Let the weather choose\n\nThe best plan often changes on the morning itself. A still day belongs on the water; cloud makes the ridge gentler; rain brings guests into the ceramic studio or kitchen. Your host checks conditions, confirms guides, and leaves enough empty time around the outing.\n\nEvery experience is private unless described as a house gathering. Water, a light picnic, transfers, and the equipment listed in your itinerary are included.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'A balanced three-day stay',
                'header' => 'row+col',
                'table' => [
                    ['Day', 'Morning', 'Afternoon', 'Evening'],
                    ['Arrival', '—', 'Settle in and bathe', 'Courtyard dinner'],
                    ['Second day', 'Grove walk and breakfast', 'Pool, treatment, or free time', 'Sunset sail'],
                    ['Third day', 'Movement and mountain walk', 'Long lunch and rest', 'Bath house and tasting menu'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'Days remembered well',
                'items' => [
                    ['name' => 'Lucia and Ben', 'role' => 'London', 'text' => 'Our guide changed the route when the cloud came in. We walked through oak woods, ate lunch in a village, and never felt hurried toward the next thing.'],
                    ['name' => 'Mina Sato', 'role' => 'Kyoto', 'text' => 'The sail was quiet and simple: coffee on deck, two empty coves, and enough time to swim without watching the clock.'],
                    ['name' => 'Hugo Martins', 'role' => 'Lisbon', 'text' => 'Tomas made the olive walk feel like a morning with a neighbour rather than an organised excursion.'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates practical guest guide pages below the home page.
     */
    protected function addGuide( Page $home ) : static
    {
        $guide = $this->page( [
            'lang' => 'en',
            'name' => 'Guest guide',
            'title' => 'Avelune Guest Guide',
            'path' => 'guest-guide',
            'type' => 'docs',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'toc', 'group' => 'main', 'data' => ['title' => 'On this page']],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => ['level' => 2, 'title' => 'Before you travel']],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Avelune is a small retreat in the foothills of Mallorca's Serra de Tramuntana, forty-five minutes from Palma airport. The estate has twenty-eight rooms and suites, so transfers, treatments, and dietary details are easiest to arrange before travel.\n\nYour pre-arrival note arrives ten days before check-in. Reply with flight times, food allergies, mobility needs, treatment preferences, and anything worth knowing about the reason for your stay.",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Detailed guides',
                'cards' => [
                    ['title' => 'Arrival and departure', 'text' => "Transfers, driving directions, check-in, luggage, and late arrivals.\n\n[Plan your arrival](/guest-guide/arrival)", 'file' => ['id' => $this->img( 'home' ), 'type' => 'file']],
                    ['title' => 'Wellness visits', 'text' => "What to wear, when to arrive, health notes, private bathing, and changes.\n\n[Prepare for wellness](/guest-guide/wellness)", 'file' => ['id' => $this->img( 'spa' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Useful times',
                'header' => 'row',
                'table' => [
                    ['Service', 'Hours', 'Notes'],
                    ['Reception', '24 hours', 'Night entrance is staffed'],
                    ['Breakfast', '07:30–11:00', 'Earlier trays by arrangement'],
                    ['Bath house', '07:00–21:00', 'Quiet hours before 09:00 and after 19:00'],
                    ['Treatments', '09:00–20:00', 'Advance booking recommended'],
                    ['Pool', 'Sunrise–20:00', 'Unsupervised; adults only before 09:00'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => ['level' => 2, 'title' => 'What to bring']],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Rooms include robes, pool sandals, a beach bag, yoga mats, refillable water bottles, and natural insect repellent. Bring shoes with grip for the mountain paths and one warm layer outside high summer.\n\nThere is no formal dress code. A few dining tables sit on uneven stone, and the estate is most comfortable in clothes that move easily between garden, bath house, and dinner.",
            ]],
        ], $home );

        $this->page( [
            'lang' => 'en',
            'name' => 'Arrival and departure',
            'title' => 'Arrival and Departure | Avelune Guest Guide',
            'path' => 'guest-guide/arrival',
            'type' => 'docs',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'toc', 'group' => 'main', 'data' => ['title' => 'On this page']],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => ['level' => 2, 'title' => 'From Palma airport']],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "A private car takes about forty-five minutes outside the morning and evening commute. Your driver waits in the arrivals hall with an Avelune sign and tracks delays automatically. The fixed transfer is €95 each way for up to three guests.\n\nIf you drive, use the route link in your confirmation rather than the postal address; the final lane is private and some navigation services stop at the lower gate.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Arrival timings',
                'header' => 'row',
                'table' => [
                    ['Time', 'Arrangement'],
                    ['Before 15:00', 'Leave luggage, use the pool and bath house, and take lunch while the room is prepared'],
                    ['15:00–23:00', 'Standard check-in at reception with a short estate orientation'],
                    ['After 23:00', 'Night host greets you; a light supper can be left in the room'],
                    ['Departure', 'Rooms by 12:00; estate facilities remain available until 17:00'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'home' ), 'type' => 'file'],
                'position' => 'end',
                'ratio' => '1-2',
                'text' => "### Arrive without a list of errands\n\nReception can arrange a local eSIM, pharmacy stop, forgotten toiletries, hiking equipment, garment pressing, and onward travel. Electric cars charge overnight at four covered spaces.\n\nFor an early flight, breakfast can be packed and your account settled the evening before.",
            ]],
        ], $guide );

        $this->page( [
            'lang' => 'en',
            'name' => 'Wellness visits',
            'title' => 'Wellness Visits | Avelune Guest Guide',
            'path' => 'guest-guide/wellness',
            'type' => 'docs',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'toc', 'group' => 'main', 'data' => ['title' => 'On this page']],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => ['level' => 2, 'title' => 'Before a treatment']],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Arrive at the wellness house ten minutes before your appointment in a robe or comfortable clothing. Treatment rooms have secure drawers, showers, and everything needed for changing.\n\nYour practitioner will ask about injuries, medication, pregnancy, allergies, and the pressure or pace you prefer. This conversation is part of the appointment and need not be repeated in detail at reception.",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'A considerate visit',
                'cards' => [
                    ['title' => 'Bathing', 'text' => 'Swimwear is worn in shared areas. Private bath sessions may be arranged for individuals or couples.'],
                    ['title' => 'Phones', 'text' => 'Keep devices silent and out of sight in the bath house, movement studio, and treatment corridor.'],
                    ['title' => 'Changes', 'text' => 'Move or cancel treatments at least twelve hours ahead to avoid the full appointment charge.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => ['level' => 2, 'title' => 'Health and comfort']],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Avelune wellness is restorative hospitality, not medical care. Practitioners can adapt position, pressure, temperature, sound, and lighting, and will recommend against a treatment when it is not appropriate.\n\nContact [wellness@avelune.example](mailto:wellness@avelune.example) before booking if you are recovering from surgery, receiving active cancer treatment, pregnant, or managing a cardiovascular condition.",
            ]],
        ], $guide );

        return $this;
    }


    /**
     * Creates the reservation page below the home page.
     */
    protected function addReserve( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Reserve',
            'title' => 'Reserve Your Stay | Avelune Retreat',
            'path' => 'reserve',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Tell us how you want to feel when you leave',
                'subtitle' => 'Reserve Avelune',
                'text' => 'Share your dates and what brought you here. A host will reply with room options and an itinerary that leaves room for the stay to breathe.',
                'files' => [['id' => $this->img( 'terrace' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Choose the conversation',
                'cards' => [
                    ['title' => 'A stay for one or two', 'text' => "Rooms, suites, wellness programmes, and private experiences.\n\n[stay@avelune.example](mailto:stay@avelune.example)"],
                    ['title' => 'A private retreat', 'text' => "Full-estate stays, leadership retreats, small celebrations, and creative residencies.\n\n[private@avelune.example](mailto:private@avelune.example)"],
                    ['title' => 'Wellness guidance', 'text' => "Treatment planning, practitioner questions, accessibility, and health considerations.\n\n[wellness@avelune.example](mailto:wellness@avelune.example)"],
                ],
            ]],
            ['id' => 'reservation-form', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Request your stay',
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Reservation details',
                'items' => [
                    ['title' => 'How quickly will you reply?', 'text' => 'A host replies within one working day. Requests for the next forty-eight hours are best made by telephone.'],
                    ['title' => 'What is the deposit?', 'text' => 'Flexible stays require one night at confirmation. Retreat programmes and the private house require thirty per cent.'],
                    ['title' => 'Can dates be changed?', 'text' => 'Flexible rates may be changed or cancelled up to seven days before arrival. Seasonal and retreat rates carry their own clearly stated terms.'],
                    ['title' => 'Are children welcome?', 'text' => 'Guests aged sixteen and over are welcome year-round. The full estate may be reserved for private family stays with younger children.'],
                    ['title' => 'Can you arrange an accessible stay?', 'text' => 'Two garden rooms, the restaurant, pool terrace, and wellness house have step-free access. Contact us so routes and treatments can be planned carefully.'],
                    ['title' => 'Can you hold dates while I decide?', 'text' => 'Available rooms can usually be held for forty-eight hours while you confirm travel and programme details. Peak-season dates may require an immediate deposit.'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the rooms and suites page below the home page.
     */
    protected function addStay( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Stay',
            'title' => 'Rooms and Suites | Avelune Retreat',
            'path' => 'stay',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Sleep with stone, linen, and open air',
                'subtitle' => 'Stay at Avelune',
                'text' => 'Twenty-eight rooms and suites set between the old house, garden, and olive terraces. Each has outdoor space and the quiet details needed for genuine rest.',
                'url' => '#rooms',
                'button' => 'Find your room',
                'url-alternative' => '/reserve',
                'button-alternative' => 'Request dates',
                'files' => [['id' => $this->img( 'suite' ), 'type' => 'file']],
            ]],
            ['id' => 'rooms', 'type' => 'pricing', 'group' => 'main', 'data' => [
                'title' => 'Choose your part of the estate',
                'text' => 'Rates are per night for two guests and include breakfast, daily movement, the bath house, pool, and return transfer for stays of four nights or longer.',
                'items' => [
                    ['name' => 'Garden Room', 'price' => '420€', 'unit' => '/night', 'text' => 'A calm ground-floor room with a planted terrace and step-free route to the pool.', 'features' => "- 34 m² interior\n- King or twin bed\n- Walk-in rain shower\n- Private garden terrace", 'file' => ['id' => $this->priceImg( 'terrace' ), 'type' => 'file'], 'url' => '/reserve', 'button' => 'Request a Garden Room'],
                    ['name' => 'Terrace Suite', 'price' => '610€', 'unit' => '/night', 'text' => 'A generous suite above the grove with a separate sitting room and sunset terrace.', 'features' => "- 52 m² interior\n- King bed\n- Bath and rain shower\n- West-facing terrace", 'file' => ['id' => $this->priceImg( 'suite' ), 'type' => 'file'], 'url' => '/reserve', 'button' => 'Request a Terrace Suite', 'highlight' => true, 'badge' => 'Signature stay'],
                    ['name' => 'The Limestone House', 'price' => '1.480€', 'unit' => '/night', 'text' => 'A private two-bedroom house with a plunge pool, kitchen, and dedicated host.', 'features' => "- 128 m² interior\n- Two king bedrooms\n- Private heated pool\n- Breakfast in the house", 'file' => ['id' => $this->priceImg( 'pool' ), 'type' => 'file'], 'url' => '/reserve', 'button' => 'Request the complete House'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'bath' ), 'type' => 'file'],
                'position' => 'end',
                'ratio' => '1-2',
                'text' => "## Rooms designed around sleep\n\nNatural linen, wool mattresses, blackout shutters, and silent climate control create a room that settles quickly at night. There are no televisions. A portable screen is available for guests who ask.\n\nThe bedside panel controls only light and shutters; phones charge inside the writing desk. Turndown brings filtered water, a seasonal infusion, and tomorrow's sunrise time.",
            ]],
            ['id' => Utils::uid(), 'type' => 'slideshow', 'group' => 'main', 'data' => [
                'title' => 'Inside your stay',
                'main' => true,
                'files' => [
                    ['id' => $this->slideImg( 'suite' ), 'type' => 'file'],
                    ['id' => $this->slideImg( 'terrace' ), 'type' => 'file'],
                    ['id' => $this->slideImg( 'bath' ), 'type' => 'file'],
                    ['id' => $this->slideImg( 'pool' ), 'type' => 'file'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'At a glance',
                'header' => 'row+col',
                'table' => [
                    ['Room', 'Sleeps', 'Outdoor space', 'Bath', 'Step-free'],
                    ['Garden Room', '2', 'Planted terrace', 'Rain shower', 'Two rooms'],
                    ['Terrace Suite', '2', 'Grove-view terrace', 'Bath and shower', 'No'],
                    ['Limestone House', '4', 'Courtyard and private pool', 'Two baths and showers', 'Ground floor only'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Room questions',
                'items' => [
                    ['title' => 'What is included in every rate?', 'text' => 'Breakfast, daily movement classes, bath house access, the outdoor pool, filtered water, and evening turndown.'],
                    ['title' => 'Can beds be separated?', 'text' => 'Garden Rooms can be prepared with twin beds. The suites and house have fixed king beds.'],
                    ['title' => 'Are rooms air-conditioned?', 'text' => 'Yes. Silent heating and cooling can be set in the room and switches off when doors remain open.'],
                    ['title' => 'May I bring a pet?', 'text' => 'Two Garden Rooms welcome one dog up to 20 kg. Dogs remain outside the restaurant, pool, and wellness house.'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the wellness page below the home page.
     */
    protected function addWellness( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Wellness',
            'title' => 'Wellness at Avelune | Spa and Restorative Retreats',
            'path' => 'wellness',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Return to your own rhythm',
                'subtitle' => 'The Avelune wellness house',
                'text' => 'Skilled touch, warm water, steady movement, and programmes built around what your body can use—not a crowded schedule.',
                'url' => '#rituals',
                'button' => 'Explore rituals',
                'url-alternative' => '/reserve',
                'button-alternative' => 'Plan a retreat',
                'files' => [['id' => $this->img( 'spa' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'massage' ), 'type' => 'file'],
                'position' => 'start',
                'ratio' => '1-2',
                'text' => "## Begin with a conversation\n\nEvery programme starts with time to understand how you sleep, move, work, eat, and recover. The aim is not to fill each hour. It is to choose a few practices that make a visible difference, then leave enough space for them to settle.\n\nOur team includes massage therapists, movement teachers, a sleep practitioner, nutritionist, and visiting physiotherapists. When clinical care is more appropriate, we say so plainly.",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Three foundations',
                'cards' => [
                    ['title' => 'Rest', 'text' => 'Sleep consultations, evening routines, quiet rooms, and schedules that respect recovery.'],
                    ['title' => 'Touch', 'text' => 'Slow bodywork, focused therapeutic massage, facials, and treatments using island botanicals.'],
                    ['title' => 'Movement', 'text' => 'Morning mobility, yoga, strength, swimming, and guided walks adapted to the body in front of us.'],
                ],
            ]],
            ['id' => 'rituals', 'type' => 'pricing', 'group' => 'main', 'data' => [
                'title' => 'Wellness programmes',
                'text' => 'Programmes are added to your room stay. Each is private and can be adapted for pregnancy, injury, lower mobility, or a preference for gentler work.',
                'items' => [
                    ['name' => 'Avelune Ritual', 'price' => '220€', 'unit' => '/2 hours', 'text' => 'A private bathing sequence followed by intuitive bodywork and a garden infusion.', 'features' => "- Wellness consultation\n- Private bath house\n- 75-minute treatment\n- Rest room tea", 'file' => ['id' => $this->priceImg( 'bath' ), 'type' => 'file'], 'url' => '/reserve', 'button' => 'Request ritual'],
                    ['name' => 'Three Nights of Rest', 'price' => '780€', 'unit' => '/stay', 'text' => 'A light structure for guests who arrive tired and want sleep to feel natural again.', 'features' => "- Sleep consultation\n- Two body treatments\n- Private movement session\n- Evening bath ritual", 'file' => ['id' => $this->priceImg( 'suite' ), 'type' => 'file'], 'url' => '/reserve', 'button' => 'Plan three nights', 'highlight' => true, 'badge' => 'Most requested'],
                    ['name' => 'Five Nights in Motion', 'price' => '1.260€', 'unit' => '/stay', 'text' => 'Restore strength and ease through individual movement, walking, treatment, and useful rest.', 'features' => "- Movement assessment\n- Three private sessions\n- Two treatments\n- Guided mountain walk", 'file' => ['id' => $this->priceImg( 'yoga' ), 'type' => 'file'], 'url' => '/reserve', 'button' => 'Plan five nights'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'A day at the wellness house',
                'header' => 'row',
                'table' => [
                    ['Time', 'Practice'],
                    ['07:30', 'Morning mobility in the courtyard'],
                    ['09:00', 'Private movement and treatment appointments begin'],
                    ['12:00', 'Breath and stretch in the pine pavilion'],
                    ['16:00', 'Herbal steam ritual'],
                    ['18:00', 'Restorative yoga or guided meditation'],
                    ['19:00', 'Quiet bath house hour'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'What guests carried home',
                'items' => [
                    ['name' => 'Amelie R.', 'role' => 'Paris', 'text' => 'Nothing felt like a test. The team listened, removed half the schedule I thought I needed, and gave me practices I still use on ordinary Tuesdays.'],
                    ['name' => 'Jonas K.', 'role' => 'Copenhagen', 'text' => 'The treatment was excellent, but the real shift came from morning light, walking, and eating earlier. It was practical enough to keep.'],
                    ['name' => 'Nadia M.', 'role' => 'Amsterdam', 'text' => 'I arrived with an old shoulder injury and never had to defend my limits. Every movement session was adapted without fuss.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Wellness questions',
                'items' => [
                    ['title' => 'Must I book a programme?', 'text' => 'No. The bath house and daily group movement are included with every stay; individual treatments may be booked separately.'],
                    ['title' => 'How far ahead should I reserve?', 'text' => 'Reserve programmes with the room. For individual treatments, two weeks is usually enough outside holidays.'],
                    ['title' => 'Is the bath house private?', 'text' => 'It is shared and quiet by default. Private early-morning and evening sessions are available.'],
                    ['title' => 'Can treatments be adapted?', 'text' => 'Yes. Tell us about pregnancy, injury, recent surgery, allergies, or heat sensitivity so the right practitioner and room can be prepared.'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates an article lead element with the file reference used by previews.
     *
     * @return array<string, mixed>
     */
    protected function article( string $title, string $text, string $fileId ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'article', 'group' => 'main', 'files' => [$fileId], 'data' => [
            'title' => $title,
            'file' => ['id' => $fileId, 'type' => 'file'],
            'text' => $text,
        ]];
    }


    /**
     * Creates a closing call to action for a journal article.
     *
     * @return array<string, mixed>
     */
    protected function articleHero( string $title, string $text ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
            'title' => $title,
            'subtitle' => 'Avelune Retreat',
            'text' => $text,
            'url' => '/reserve',
            'button' => 'Plan your stay',
            'url-alternative' => '/journal',
            'button-alternative' => 'Back to the journal',
        ]];
    }


    /**
     * Creates the shared Avelune footer and returns its ID.
     */
    protected function element() : string
    {
        if( !isset( $this->element ) )
        {
            $cards = [
                ['title' => 'Avelune', 'text' => "- [Stay](/stay)\n- [Wellness](/wellness)\n- [Table](/table)\n- [Experiences](/experiences)"],
                ['title' => 'Plan', 'text' => "- [Reserve](/reserve)\n- [Guest guide](/guest-guide)\n- [Arrival](/guest-guide/arrival)\n- [Wellness visits](/guest-guide/wellness)"],
                ['title' => 'Stories', 'text' => "- [Journal](/journal)\n- [The old grove at first light](/the-old-grove-at-first-light)\n- [A kitchen led by the island](/a-kitchen-led-by-the-island)"],
                ['title' => 'Contact', 'text' => "- [stay@avelune.example](mailto:stay@avelune.example)\n- [wellness@avelune.example](mailto:wellness@avelune.example)\n- +34 971 000 000\n- Mallorca, Spain"],
            ];

            $element = Element::forceCreate( [
                'lang' => 'en',
                'type' => 'cards',
                'name' => 'Avelune footer',
                'data' => ['type' => 'cards', 'data' => ['title' => 'Avelune Retreat', 'cards' => $cards]],
                'editor' => 'demo',
            ] );

            $version = $element->versions()->forceCreate( [
                'lang' => 'en',
                'data' => [
                    'lang' => 'en',
                    'type' => 'cards',
                    'name' => 'Avelune footer',
                    'data' => ['title' => 'Avelune Retreat', 'cards' => $cards],
                ],
                'published' => true,
                'editor' => 'demo',
            ] );

            $element->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $element->publish( $version );
            $this->element = (string) $element->refresh()->id;
        }

        return $this->element;
    }


    /**
     * Returns the ID of the primary Avelune image.
     */
    protected function file() : string
    {
        return $this->img( 'home' );
    }


    /**
     * Creates the Avelune home page and returns it.
     */
    protected function home( string $journalId ) : Page
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
                'title' => 'A quiet estate between mountain and sea',
                'subtitle' => 'Avelune Retreat — Mallorca',
                'text' => 'Twenty-eight rooms, a restorative bath house, and a kitchen rooted in the island. Come for clear air, deep sleep, and days with space around them.',
                'url' => '/stay',
                'button' => 'Discover the rooms',
                'url-alternative' => '/reserve',
                'button-alternative' => 'Plan your stay',
                'files' => [['id' => $this->img( 'home' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Avelune, at your own pace',
                'cards' => [
                    ['title' => 'Stay', 'text' => "Rooms and suites in the old house, garden, and olive terraces.\n\n[Explore the rooms](/stay)", 'file' => ['id' => $this->img( 'suite' ), 'type' => 'file']],
                    ['title' => 'Wellness', 'text' => "Bathing, skilled touch, movement, and private programmes shaped around genuine rest.\n\n[Visit the wellness house](/wellness)", 'file' => ['id' => $this->img( 'spa' ), 'type' => 'file']],
                    ['title' => 'Table', 'text' => "Island cooking led by the garden, small boats, old groves, and the morning market.\n\n[Meet Avelune Table](/table)", 'file' => ['id' => $this->img( 'plate' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'pool' ), 'type' => 'file'],
                'position' => 'start',
                'ratio' => '1-2',
                'text' => "## Luxury measured in attention\n\nThe house holds fewer rooms than it could. Breakfast runs late. The pool is long enough to swim, and there are places around the estate where no music reaches.\n\nA host learns your plans without filling the day for you. Practitioners have time between appointments. The kitchen buys close to home and changes course when the morning catch does. This is what refinement means at Avelune: care you can feel, with little need to announce itself.\n\n[See what lies beyond the estate](/experiences)",
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'Notes left by guests',
                'items' => [
                    ['name' => 'Clara W.', 'role' => 'Berlin', 'text' => 'The rare hotel that understands privacy without becoming distant. By the second morning, everyone knew what mattered and left the rest alone.'],
                    ['name' => 'Matthew and Elias', 'role' => 'Edinburgh', 'text' => 'We came for four nights and did less each day: one long walk, one exceptional treatment, lunch under the vines, then nowhere else to be.'],
                    ['name' => 'Rina K.', 'role' => 'Singapore', 'text' => 'The room was beautiful, but it was the silence, the late breakfast, and the thoughtful lighting that finally let me sleep.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'blog', 'group' => 'main', 'data' => [
                'title' => 'From the journal',
                'layout' => 'cards',
                'limit' => 2,
                'order' => '_lft',
                'parent-page' => ['value' => $journalId, 'label' => 'Journal'],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Before you arrive',
                'items' => [
                    ['title' => 'How far is Avelune from Palma?', 'text' => 'The retreat is about forty-five minutes from Palma airport. Private transfers can be added to any stay.'],
                    ['title' => 'Is wellness included?', 'text' => 'The bath house, pool, and daily group movement are included. Treatments and private programmes are additional.'],
                    ['title' => 'When is the retreat open?', 'text' => 'Avelune welcomes guests from early February through the first week of January, closing for four weeks in winter.'],
                    ['title' => 'May I visit only for the day?', 'text' => 'A small number of wellness days and dinner tables are available to non-residents outside peak months.'],
                ],
            ]],
            ['id' => 'home-contact', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Begin with a conversation',
            ]],
            ['id' => Utils::uid(), 'type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        $meta = [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => 'Avelune is a secluded Mallorca boutique retreat with twenty-eight rooms, a restorative spa, seasonal island dining, and private experiences.',
                'keywords' => 'Avelune Retreat, Mallorca boutique hotel, luxury wellness retreat, Mallorca spa, Tramuntana resort',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => 'Avelune Retreat | A Quiet Estate in Mallorca',
                'description' => 'Rooms, bathing, seasonal dining, and unhurried days between Mallorca mountains and sea.',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        $page = Page::forceCreate( [
            'lang' => 'en',
            'name' => 'Home',
            'title' => 'Avelune Retreat | Boutique Hotel and Wellness in Mallorca',
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
                'title' => 'Avelune Retreat | Boutique Hotel and Wellness in Mallorca',
                'path' => '',
                'tag' => 'root',
                'domain' => '',
                'theme' => $this->theme,
                'status' => 1,
                'cache' => 5,
            ],
            'aux' => ['config' => $config, 'meta' => $meta, 'content' => $content],
            'published' => true,
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
     * @return array<int, string>
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
     */
    protected function img( string $key ) : string
    {
        [$photo, $name, $desc] = self::PHOTOS[$key];
        return $this->image( $photo, $name, $desc );
    }


    /**
     * Creates the Avelune SVG logo and returns its file ID.
     */
    protected function logoFile() : string
    {
        if( !isset( $this->logoFile ) )
        {
            $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 430 92" role="img" aria-labelledby="title desc">
  <title id="title">Avelune Retreat logo</title>
  <desc id="desc">Avelune wordmark with a crescent above a fine horizon line</desc>
  <g fill="none" fill-rule="evenodd">
    <path d="M42 14c-13 7-19 23-13 37 6 15 23 22 38 16-8 9-21 13-33 9C17 70 8 51 14 34 18 23 28 16 42 14Z" fill="#7A5F32"/>
    <path d="M12 79h62" stroke="#7A5F32" stroke-width="1"/>
    <text x="94" y="57" fill="#1C1A16" font-family="Didot, 'Bodoni 72', 'Times New Roman', serif" font-size="43" letter-spacing="5">AVELUNE</text>
    <text x="98" y="78" fill="#7A5F32" font-family="Futura, 'Century Gothic', Arial, sans-serif" font-size="10" letter-spacing="7">RETREAT MALLORCA</text>
  </g>
</svg>
SVG;

            $disk = Storage::disk( config( 'cms.disk', 'public' ) );
            $path = rtrim( 'cms/' . $this->tenant, '/' ) . '/avelune-logo.svg';

            if( !$disk->put( $path, $svg ) ) {
                throw new \Aimeos\Cms\Exception( sprintf( 'Unable to store logo "%s"', $path ) );
            }

            $data = [
                'mime' => 'image/svg+xml',
                'lang' => 'en',
                'name' => 'Avelune Retreat logo',
                'path' => $path,
                'previews' => ['500' => $path],
                'description' => ['en' => 'Avelune wordmark with a gold crescent above a fine horizon line'],
            ];

            $file = File::forceCreate( $data + ['editor' => 'demo'] );
            $version = $file->versions()->forceCreate( [
                'lang' => 'en',
                'data' => $data,
                'published' => true,
                'editor' => 'demo',
            ] );

            $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $file->publish( $version );
            $this->logoFile = (string) $file->refresh()->id;
        }

        return $this->logoFile;
    }


    /**
     * Creates a Luxury demo page below the given parent and returns it.
     *
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $content
     * @param array<int, string> $fileIds
     * @param array<string, array<string, mixed>|object> $meta
     */
    protected function page( array $data, array $content, Page $parent, array $fileIds = [], array $meta = [] ) : Page
    {
        $elementId = $this->element();
        $fileId = $this->file();
        $description = self::DESCRIPTIONS[$data['path'] ?? ''] ?? $data['title'] ?? '';

        $meta = $data['meta'] ?? $meta ?: [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => $description,
                'keywords' => 'Avelune Retreat, Mallorca boutique hotel, luxury wellness retreat, spa, island dining',
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
            'published' => true,
            'editor' => 'demo',
        ] );

        $version->elements()->attach( $elementId );
        $version->files()->attach( array_unique( array_merge( [$fileId], $fileIds, $this->ids( $content ), $this->ids( $meta ) ) ) );

        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->publish( $version );

        return $page;
    }


    /**
     * Builds the Luxury boutique-retreat demo page tree.
     */
    protected function pages() : void
    {
        $journalId = (string) Str::uuid7();
        $home = $this->home( $journalId );

        $this->addStay( $home )
            ->addWellness( $home )
            ->addDining( $home )
            ->addExperiences( $home )
            ->addBlog( $home, $journalId )
            ->addGuide( $home )
            ->addReserve( $home );
    }


    /**
     * Creates a fixed 3:2 pricing image and returns its file ID.
     */
    protected function priceImg( string $key ) : string
    {
        if( !isset( $this->pricingImages[$key] ) )
        {
            [$photo, $name, $desc] = self::PHOTOS[$key];
            $base = 'https://images.unsplash.com/' . $photo;
            $url = fn( int $w, int $h ) => $base . '?w=' . $w . '&h=' . $h . '&q=80&fm=jpg&fit=crop';

            $data = [
                'mime' => 'image/jpeg',
                'lang' => 'en',
                'name' => $name,
                'path' => $url( 1500, 1000 ),
                'previews' => ['500' => $url( 500, 333 ), '1000' => $url( 1000, 667 )],
                'description' => ['en' => $desc],
            ];

            $file = File::forceCreate( $data + ['editor' => 'demo'] );
            $version = $file->versions()->forceCreate( [
                'lang' => 'en',
                'data' => $data,
                'published' => true,
                'editor' => 'demo',
            ] );

            $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $file->publish( $version );
            $this->pricingImages[$key] = (string) $file->refresh()->id;
        }

        return $this->pricingImages[$key];
    }


    /**
     * Creates a fixed 2:1 slideshow image and returns its file ID.
     */
    protected function slideImg( string $key ) : string
    {
        if( !isset( $this->slideImages[$key] ) )
        {
            [$photo, $name, $desc] = self::PHOTOS[$key];
            $base = 'https://images.unsplash.com/' . $photo;
            $url = fn( int $w, int $h ) => $base . '?w=' . $w . '&h=' . $h . '&q=80&fm=jpg&fit=crop';

            $data = [
                'mime' => 'image/jpeg',
                'lang' => 'en',
                'name' => $name,
                'path' => $url( 1500, 750 ),
                'previews' => ['500' => $url( 500, 250 ), '1000' => $url( 1000, 500 )],
                'description' => ['en' => $desc],
            ];

            $file = File::forceCreate( $data + ['editor' => 'demo'] );
            $version = $file->versions()->forceCreate( [
                'lang' => 'en',
                'data' => $data,
                'published' => true,
                'editor' => 'demo',
            ] );

            $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $file->publish( $version );
            $this->slideImages[$key] = (string) $file->refresh()->id;
        }

        return $this->slideImages[$key];
    }
}
