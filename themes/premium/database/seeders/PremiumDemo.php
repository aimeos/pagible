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
 * Premium theme demo for the fictional Stillform design-technology company.
 */
class PremiumDemo extends AbstractDemo
{
    /** @var array<string, string> Meta descriptions keyed by page path */
    private const DESCRIPTIONS = [
        'collection' => 'Explore Stillform Beam One, Dial One, and Dock One: calm, repairable technology designed for focused rooms and long daily use.',
        'studio' => 'Meet the Stillform team and see how industrial design, electronics, materials, and repairability shape every product.',
        'journal' => 'Read Stillform Journal notes on physical interfaces, adaptive light, repairable electronics, and designing calmer technology.',
        'why-a-good-button-still-matters' => 'Why tactile controls still outperform touchscreens for frequent actions that should remain fast, precise, and easy to remember.',
        'designing-light-for-a-day-that-changes' => 'How Stillform designed Beam One around changing daylight, different tasks, and controls that never interrupt concentration.',
        'repairability-begins-before-the-first-prototype' => 'How replaceable modules, reversible fasteners, and a clear parts plan make repairability a design constraint from the first sketch.',
        'calm-technology-needs-clear-boundaries' => 'A practical approach to connected products that remain useful without accounts, subscriptions, notifications, or permanent cloud access.',
        'support' => 'Stillform product support for setup, compatibility, care, repairs, replacement parts, warranty service, and recycling.',
        'support/beam-one' => 'Set up Beam One, adjust light and presence behavior, save a scene, and resolve common power or pairing issues.',
        'support/care-and-repair' => 'Care for Stillform products, order replacement parts, prepare a repair, and understand the five-year product warranty.',
        'contact' => 'Contact Stillform for product guidance, trade projects, press requests, repairs, partnerships, or general questions.',
    ];

    /**
     * Curated Unsplash photos used across the Stillform demo.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const PHOTOS = [
        'beam' => ['photo-1507473885765-e6ed057f782c', 'Beam One task light', 'Minimal task light casting a warm pool of light across a quiet workspace'],
        'detail' => ['photo-1523275335684-37898b6baf30', 'Precision control detail', 'Close view of a precisely machined metal control and its tactile markings'],
        'dial' => ['photo-1765805914125-56fce216cd1b', 'Dial One product detail', 'Black desktop control with a large illuminated rotary dial and physical shortcut buttons'],
        'dock' => ['photo-1778854096628-0266421cac9e', 'Dock One product detail', 'Compact silver charging stand with magnetic device pads and USB-C ports on a bright desk'],
        'focus' => ['photo-1497366811353-6870744d04b2', 'Calm product workspace', 'Bright and uncluttered workspace prepared for concentrated product work'],
        'home' => ['photo-1494438639946-1ebd1d20bf85', 'Stillform products at home', 'Warm interior with considered lighting and technology integrated into the room'],
        'interface' => ['photo-1550745165-9bc0b252726f', 'Physical interface study', 'Collection of familiar physical controls used for an interaction study'],
        'light' => ['photo-1513506003901-1e6a229e2d15', 'Light study', 'Adjustable task light illuminating a desk during an evening work session'],
        'materials' => ['photo-1452860606245-08befc0ff44b', 'Materials and tools', 'Hand tools and durable materials arranged on a product development bench'],
        'packaging' => ['photo-1600508774634-4e11d34730e2', 'Stillform packaging study', 'Carefully arranged product and recyclable packaging materials'],
        'repair' => ['photo-1450101499163-c8848c66ca85', 'Repair documentation', 'Parts, drawings, and service notes arranged for a product repair'],
        'studio' => ['photo-1497366754035-f200968a6e72', 'Stillform studio', 'Bright design studio with shared worktables, prototypes, shelves, and plants'],
        'team' => ['photo-1531403009284-440f080d1e12', 'Stillform design review', 'Product team reviewing interface and manufacturing ideas on a studio wall'],
        'workshop' => ['photo-1503602642458-232111445657', 'Long-life product study', 'Carefully made objects lined up for inspection in a quiet workshop'],
    ];

    private string $element;
    private string $logoFile;
    /** @var array<string, string> File IDs for fixed-ratio slideshow images */
    private array $slideImages = [];


    /**
     * Creates the design journal and its articles below the home page.
     *
     * @param Page $home Home page
     * @param string $journalId Journal page ID referenced by listing elements
     * @return static Same object for fluent calls
     */
    protected function addBlog( Page $home, string $journalId ) : static
    {
        $journal = $this->page( [
            'id' => $journalId,
            'lang' => 'en',
            'name' => 'Journal',
            'title' => 'Stillform Journal',
            'path' => 'journal',
            'tag' => 'blog',
            'type' => 'blog',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Notes from the workbench',
                'subtitle' => 'Stillform Journal',
                'text' => 'Detailed notes on physical interfaces, useful light, repairable electronics, and the decisions behind products that stay in use.',
                'files' => [['id' => $this->img( 'materials' ), 'type' => 'file']],
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
            'name' => 'Why a good button still matters',
            'title' => 'Why a Good Button Still Matters',
            'path' => 'why-a-good-button-still-matters',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'Why a good button still matters',
                "The touchscreen is remarkable when an interface needs to change. It is less convincing when the same action happens fifty times a day.\n\nA physical control can be found without looking, understood through resistance, and used while attention stays on the work. That is why Dial One begins with a ring, a press, and four marks instead of a small display.",
                $this->img( 'detail' )
            ),
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Memory belongs in the hand',
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'interface' ), 'type' => 'file'],
                'position' => 'end',
                'ratio' => '1-2',
                'text' => "Frequent controls should become familiar enough to disappear. A detent says how far the dial moved. A stop says the range has ended. A deliberate press feels different from resting a hand on the surface.\n\nThose signals are small, but together they let the user act before a label has been read. The interface moves from short-term attention into muscle memory.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'What each signal communicates',
                'header' => 'row',
                'table' => [
                    ['Signal', 'Design decision', 'User benefit'],
                    ['Rotation', 'Twenty-four quiet detents', 'Fine adjustment without watching a screen'],
                    ['Press', 'Short travel with a firm break', 'A command that is difficult to trigger by accident'],
                    ['Surface', 'Cool metal ring, warmer centre', 'Orientation can be read through touch'],
                    ['Light', 'One low-output status point', 'Feedback without another glowing display'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Digital controls are not the enemy. They are the right place for setup, labels, and actions that change over time. The physical interface should keep the few commands that benefit from speed and repetition.\n\nThe important work is deciding where that boundary sits. We prototype the action first, then give it only as much hardware as it needs.",
            ]],
            $this->articleHero(
                'Meet the interface in person',
                'Dial One turns repeatable digital commands into a control your hand can remember.'
            ),
        ], $journal );

        $this->page( [
            'lang' => 'en',
            'name' => 'Designing light for a day that changes',
            'title' => 'Designing Light for a Day That Changes',
            'path' => 'designing-light-for-a-day-that-changes',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'Designing light for a day that changes',
                "A task light is usually photographed at night, perfectly positioned over an empty desk. Real rooms are less cooperative. Morning sun moves across the wall. Screens change brightness. Paper, fabric, and polished surfaces ask for different angles.\n\nBeam One was designed around those changes rather than a single ideal scene.",
                $this->img( 'light' )
            ),
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Three layers of useful light',
                'cards' => [
                    ['title' => 'Position', 'text' => 'A long reach and balanced joints put light on the work without crowding the desk.'],
                    ['title' => 'Quality', 'text' => 'A broad source softens shadows while high colour rendering keeps materials honest.'],
                    ['title' => 'Response', 'text' => 'The lamp adjusts gently as daylight changes, within limits chosen by the user.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'beam' ), 'type' => 'file'],
                'position' => 'start',
                'ratio' => '1-2',
                'text' => "### Automation with an edge\n\nBeam One can follow ambient light, but it never takes complete control. A touch on the stem sets a preferred level; a longer press defines how far automatic adjustment may move from it.\n\nThat boundary matters. Helpful automation should reduce repeated work without making the object unpredictable. If the lamp surprises you, the feature has failed.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Light modes',
                'header' => 'row',
                'table' => [
                    ['Mode', 'Colour temperature', 'Typical use'],
                    ['Focus', '4000 K', 'Drawing, detailed assembly, and colour comparison'],
                    ['Balanced', '3300 K', 'Mixed screen and paper work through the afternoon'],
                    ['Evening', '2700 K', 'Reading and quiet work after daylight fades'],
                    ['Manual', '2700–4000 K', 'A saved level and tone with sensing disabled'],
                ],
            ]],
            $this->articleHero(
                'Put better light on the work',
                'Beam One balances precise adjustment with automation that stays inside your limits.'
            ),
        ], $journal );

        $this->page( [
            'lang' => 'en',
            'name' => 'Repairability begins before the first prototype',
            'title' => 'Repairability Begins Before the First Prototype',
            'path' => 'repairability-begins-before-the-first-prototype',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'Repairability begins before the first prototype',
                "A product does not become repairable when a service manual is written. By then, the enclosure, fasteners, cable paths, adhesives, and supplier choices have already settled most of the answer.\n\nAt Stillform, the repair plan starts beside the first bill of materials. We decide which parts will wear, how they can be reached, and what a replacement should cost before the industrial design is fixed.",
                $this->img( 'repair' )
            ),
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'materials' ), 'type' => 'file'],
                'position' => 'end',
                'ratio' => '1-2',
                'text' => "### Design the route in and out\n\nEvery Stillform product opens with standard tools. Load-bearing threads sit in metal inserts. Cables disconnect at the board. Batteries, power supplies, and high-cycle controls remain separate from parts intended to last for decades.\n\nThat architecture adds work early. It also prevents a worn cable or failed power board from turning the whole object into waste.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'The service promise',
                'header' => 'row',
                'table' => [
                    ['Part', 'Expected service', 'Replacement approach'],
                    ['External cable', 'High wear', 'User-replaceable without opening the product'],
                    ['Power module', 'Electronic service item', 'One board held by screws and one connector'],
                    ['Control ring', 'High-cycle mechanical part', 'Front-access module replaced by a service partner'],
                    ['Aluminium body', 'Product lifetime', 'Refinished or reused during factory refurbishment'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'What must exist after launch',
                'cards' => [
                    ['title' => 'Named parts', 'text' => 'Exploded diagrams and stable identifiers make the repair understandable before a ticket is opened.'],
                    ['title' => 'A fair price', 'text' => 'Replacement modules are priced to make repair the obvious decision, not a gesture of loyalty.'],
                    ['title' => 'A return route', 'text' => 'Products that cannot be repaired locally can return to the factory for refurbishment or responsible recycling.'],
                ],
            ]],
            $this->articleHero(
                'Keep the object in service',
                'Stillform publishes care guidance, replacement paths, and warranty terms for every product.'
            ),
        ], $journal );

        $this->page( [
            'lang' => 'en',
            'name' => 'Calm technology needs clear boundaries',
            'title' => 'Calm Technology Needs Clear Boundaries',
            'path' => 'calm-technology-needs-clear-boundaries',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'Calm technology needs clear boundaries',
                "Connected products often begin with a useful remote control and end with an account, a subscription, a stream of notifications, and uncertainty about what happens when the service closes.\n\nStillform products use software, but their core purpose never depends on our servers. The light still turns on. The dial still controls a paired device. The dock still delivers power.",
                $this->img( 'focus' )
            ),
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Local first is a product decision',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Accounts are optional. Scenes and preferences are stored on the product. Bluetooth setup happens directly between nearby devices. Firmware files remain available for manual installation, with release notes that explain what changed.\n\nCloud access is useful for teams managing many rooms, but it is an additional layer rather than the foundation of ordinary use.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Stillform connectivity boundaries',
                'header' => 'row',
                'table' => [
                    ['Capability', 'Connection required', 'Where data stays'],
                    ['Daily controls', 'None', 'On the product'],
                    ['Nearby setup', 'Bluetooth', 'On the paired device and product'],
                    ['Firmware update', 'Internet or manual file', 'Update history on the product'],
                    ['Fleet management', 'Optional account', 'Encrypted regional service'],
                    ['Usage analytics', 'Off by default', 'Shared only after explicit consent'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Questions we use in review',
                'items' => [
                    ['title' => 'Does the product still work when our service is unavailable?', 'text' => 'Its primary function and physical controls must remain available without an account or internet connection.'],
                    ['title' => 'Can a user understand what is being shared?', 'text' => 'The setting must name the data, recipient, purpose, and retention period before consent is given.'],
                    ['title' => 'Can the product be updated without an account?', 'text' => 'Signed firmware files and release notes must remain available for direct download and manual installation.'],
                    ['title' => 'Can the feature be removed later?', 'text' => 'Connected additions should not create a permanent dependency that prevents future local operation or repair.'],
                ],
            ]],
            $this->articleHero(
                'Choose technology with an off switch',
                'Stillform products keep their essential functions local, legible, and available for the life of the object.'
            ),
        ], $journal );

        return $this;
    }


    /**
     * Creates the contact page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addContact( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Contact',
            'title' => 'Contact Stillform',
            'path' => 'contact',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Start with the right conversation',
                'subtitle' => 'Contact Stillform',
                'text' => 'Ask about a product, plan a trade project, request a repair, or tell us where our work could be better.',
                'files' => [['id' => $this->img( 'studio' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Choose a route',
                'cards' => [
                    ['title' => 'Product and orders', 'text' => "Compatibility, delivery, returns, and choosing the right product.\n\n[orders@stillform.example](mailto:orders@stillform.example)"],
                    ['title' => 'Trade and workplace', 'text' => "Samples, specifications, volume projects, and installation planning.\n\n[trade@stillform.example](mailto:trade@stillform.example)"],
                    ['title' => 'Press and partnerships', 'text' => "Images, product loans, interviews, and considered collaborations.\n\n[studio@stillform.example](mailto:studio@stillform.example)"],
                ],
            ]],
            ['id' => 'contact-form', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Write to the studio',
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Before you write',
                'items' => [
                    ['title' => 'Where can I find repair help?', 'text' => 'Start with the Support section for care instructions, troubleshooting, warranty terms, and replacement-part routes.'],
                    ['title' => 'Do you work with architects and interior designers?', 'text' => 'Yes. The trade team can provide samples, technical files, finish guidance, project pricing, and delivery planning.'],
                    ['title' => 'Can I visit the studio?', 'text' => 'Studio visits are limited to scheduled product reviews, press appointments, and trade project sessions.'],
                    ['title' => 'When will I hear back?', 'text' => 'The team replies within two working days. Safety-related product issues and active repair cases are prioritised.'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the product support pages below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addDocs( Page $home ) : static
    {
        $support = $this->page( [
            'lang' => 'en',
            'name' => 'Support',
            'title' => 'Stillform Support',
            'path' => 'support',
            'type' => 'docs',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'toc', 'group' => 'main', 'data' => [
                'title' => 'On this page',
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Start with the product',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Stillform products are designed to be understandable without a manual, but setup, compatibility, and service details should still be easy to find. Choose a guide below or contact the support team with the product name and serial number.\n\nThe serial number sits beneath the removable base plate. It identifies the finish, production run, electronics revision, and warranty date without exposing personal account data.",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Guides',
                'cards' => [
                    ['title' => 'Set up Beam One', 'text' => "Assembly, controls, light scenes, presence behavior, and pairing.\n\n[Open the Beam One guide](/support/beam-one)", 'file' => ['id' => $this->img( 'beam' ), 'type' => 'file']],
                    ['title' => 'Care and repair', 'text' => "Cleaning, replacement parts, warranty service, factory refurbishment, and recycling.\n\n[Open care and repair](/support/care-and-repair)", 'file' => ['id' => $this->img( 'repair' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Compatibility at a glance',
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Product connections',
                'header' => 'row+col',
                'table' => [
                    ['Product', 'Power', 'Local connection', 'Account required'],
                    ['Beam One', 'USB-C PD, 45 W', 'Bluetooth for optional scenes', 'No'],
                    ['Dial One', 'USB-C rechargeable', 'Bluetooth or USB-C', 'No'],
                    ['Dock One', '100–240 V input', 'USB-C and USB-A', 'No'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Warranty and service',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Every Stillform product carries a five-year warranty covering defects in materials and workmanship. Cables and user-replaceable wear parts carry two years. Damage outside warranty can still be assessed and repaired at a published parts-and-labour price.\n\nEmail [support@stillform.example](mailto:support@stillform.example) if a guide does not resolve the issue. We reply within two working days.",
            ]],
        ], $home );

        $this->page( [
            'lang' => 'en',
            'name' => 'Set up Beam One',
            'title' => 'Set Up Beam One | Stillform Support',
            'path' => 'support/beam-one',
            'type' => 'docs',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'toc', 'group' => 'main', 'data' => [
                'title' => 'On this page',
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Assemble and position the light',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Place the base on a stable surface, insert the lower arm until the alignment marks meet, and tighten the collar by hand. Connect the supplied 45 W USB-C power adapter before moving the light into its working position.\n\nKeep the head above eye level and slightly to the opposite side of your writing hand. The broad light source should sit 45–70 cm above the work surface without reflecting directly in a display.",
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'beam' ), 'type' => 'file'],
                'position' => 'end',
                'ratio' => '1-2',
                'text' => "### Use the stem controls\n\nTap the upper mark to switch the light on or off. Slide a finger along the stem to change brightness. Press and hold both marks for two seconds to move between Focus, Balanced, Evening, and Manual modes.\n\nBeam One remembers the last level for each mode on the product itself.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Status light',
                'header' => 'row',
                'table' => [
                    ['Signal', 'Meaning', 'Action'],
                    ['Soft white', 'Ready for setup', 'Open nearby Bluetooth settings if pairing is needed'],
                    ['Two white pulses', 'Scene saved', 'No action required'],
                    ['Amber pulse', 'Power adapter is below 45 W', 'Use the supplied adapter or a compatible USB-C PD supply'],
                    ['Red pulse', 'Joint or temperature protection active', 'Switch off, check free movement, and allow the lamp to cool'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Pair only if you need scenes',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Beam One works fully from its physical controls. Pairing adds named scenes, scheduling, and coordination with Dial One. Hold the lower stem mark for five seconds, then choose Beam One in the Stillform app.\n\nPairing data and scenes are stored locally. An account is required only for optional multi-room fleet management.",
            ]],
        ], $support );

        $this->page( [
            'lang' => 'en',
            'name' => 'Care and repair',
            'title' => 'Care and Repair | Stillform Support',
            'path' => 'support/care-and-repair',
            'type' => 'docs',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Clean without changing the finish',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Disconnect power before cleaning. Wipe anodised aluminium with a soft cloth lightly dampened with water, then dry it. Use a small amount of mild soap for marks that remain.\n\nDo not use alcohol, abrasive pads, furniture polish, or citrus cleaners. They can alter the sheen of the finish, soften elastomer feet, or remove printed control marks.",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Service options',
                'cards' => [
                    ['title' => 'Replacement part', 'text' => 'Cables, feet, adapters, and external fasteners can be ordered with the product serial number.'],
                    ['title' => 'Local service', 'text' => 'Approved partners can replace modular controls, power boards, batteries, and worn mechanical parts.'],
                    ['title' => 'Factory return', 'text' => 'Complex repairs return to Stillform for assessment, refurbishment, testing, and a new service warranty.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'repair' ), 'type' => 'file'],
                'position' => 'start',
                'ratio' => '1-2',
                'text' => "### Prepare a repair request\n\nSend the serial number, a short description, and two clear photographs to [support@stillform.example](mailto:support@stillform.example). Include any status-light pattern and the power supply being used.\n\nSupport will first check for a safe user-resolvable cause. If service is needed, you will receive a fixed estimate or warranty confirmation before shipping the product.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Service coverage',
                'header' => 'row',
                'table' => [
                    ['Service', 'Coverage', 'Return shipping'],
                    ['Warranty repair', 'Parts and labour', 'Covered in supported regions'],
                    ['Paid repair', 'Fixed estimate before work', 'Included in the estimate'],
                    ['Factory refurbishment', 'Inspection, replaced wear parts, finish care, full test', 'Included'],
                    ['Recycling return', 'Responsible material recovery', 'Customer pays inbound shipping'],
                ],
            ]],
        ], $support );

        return $this;
    }


    /**
     * Creates the product collection page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addProducts( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Collection',
            'title' => 'Stillform Collection',
            'path' => 'collection',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Three tools. One quieter workspace.',
                'subtitle' => 'The Stillform collection',
                'text' => 'Each product solves one repeated part of the working day, then gets out of the way. Built from serviceable modules and finished to sit comfortably in a room for years.',
                'url' => '#products',
                'button' => 'Choose a product',
                'url-alternative' => '/support',
                'button-alternative' => 'Check compatibility',
                'files' => [
                    ['id' => $this->img( 'beam' ), 'type' => 'file'],
                    ['id' => $this->img( 'dial' ), 'type' => 'file'],
                    ['id' => $this->img( 'dock' ), 'type' => 'file'],
                ],
            ]],
            ['id' => 'products', 'type' => 'pricing', 'group' => 'main', 'data' => [
                'title' => 'Choose your Stillform tool',
                'text' => 'Prices include a five-year product warranty, thirty-day returns, and carbon-neutral standard delivery in supported regions.',
                'items' => [
                    ['name' => 'Dial One', 'price' => '€169', 'unit' => '', 'text' => 'A tactile controller for repeated digital actions.', 'features' => "- Machined recycled aluminium\n- Bluetooth and USB-C\n- Four local profiles\n- Replaceable battery", 'file' => ['id' => $this->img( 'dial' ), 'type' => 'file'], 'url' => '/contact', 'button' => 'Ask about Dial One'],
                    ['name' => 'Beam One', 'price' => '€289', 'unit' => '', 'text' => 'Adaptive task light with direct physical controls.', 'features' => "- 2700–4000 K light\n- CRI 95+ colour rendering\n- Local ambient sensing\n- Replaceable power module", 'file' => ['id' => $this->img( 'beam' ), 'type' => 'file'], 'url' => '/contact', 'button' => 'Ask about Beam One', 'highlight' => true, 'badge' => 'Most requested'],
                    ['name' => 'Dock One', 'price' => '€219', 'unit' => '', 'text' => 'A stable 140 W charging hub for the working surface.', 'features' => "- Three USB-C ports\n- One service USB-A port\n- Braided two-metre cable\n- Replaceable power supply", 'file' => ['id' => $this->img( 'dock' ), 'type' => 'file'], 'url' => '/contact', 'button' => 'Ask about Dock One'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'beam' ), 'type' => 'file'],
                'position' => 'start',
                'ratio' => '1-2',
                'text' => "## Beam One\n\nA broad, low-glare task light that follows the room without taking control away from you. Balanced joints move with one hand and hold their position without visible springs.\n\nBeam One works entirely from the stem controls. Optional local pairing adds named scenes and coordination with Dial One.\n\n**Finish:** oxide, chalk, or burgundy  \n**Power:** USB-C PD, 45 W  \n**Warranty:** five years",
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'detail' ), 'type' => 'file'],
                'position' => 'end',
                'ratio' => '1-2',
                'text' => "## Dial One\n\nA weighted physical control for commands you repeat all day: volume, timeline movement, brush size, light scenes, mute, and any shortcut your software can expose.\n\nThe ring has quiet detents; the centre reads a deliberate press; four profiles live on the device. There is no required account and no background process after setup.\n\n**Finish:** oxide, natural, or burgundy  \n**Connection:** Bluetooth 5.3 and USB-C  \n**Battery:** user-replaceable",
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'dock' ), 'type' => 'file'],
                'position' => 'start',
                'ratio' => '1-2',
                'text' => "## Dock One\n\nA charging hub with enough mass to stay on the desk when a cable is removed. Three USB-C ports share 140 W intelligently, while the front port keeps a stable 100 W path for a primary computer.\n\nThe mains cable, power supply, and port board are separate service parts. Dock One delivers power without an app, firmware account, or status display.\n\n**Finish:** oxide or natural  \n**Input:** 100–240 V  \n**Warranty:** five years",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Compare the collection',
                'header' => 'row+col',
                'table' => [
                    ['Product', 'Primary material', 'Connection', 'Works offline', 'Repair path'],
                    ['Beam One', 'Recycled aluminium', 'Optional Bluetooth', 'Yes', 'Modular light and power boards'],
                    ['Dial One', 'Recycled aluminium', 'Bluetooth or USB-C', 'Yes', 'Replaceable battery and control module'],
                    ['Dock One', 'Recycled aluminium', 'USB-C and USB-A', 'Yes', 'Replaceable supply, cable, and port board'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Collection questions',
                'items' => [
                    ['title' => 'Do the products require a Stillform account?', 'text' => 'No. Core functions, preferences, and local pairing work without an account. Accounts are optional for multi-room management.'],
                    ['title' => 'Can I order finish samples?', 'text' => 'Trade customers can request a material set with aluminium, cable, and elastomer samples before specifying a project.'],
                    ['title' => 'Where do you ship?', 'text' => 'The demo shop serves the European Union, United Kingdom, Norway, Switzerland, United States, and Canada.'],
                    ['title' => 'Are replacement parts available?', 'text' => 'Yes. Cables, power supplies, feet, batteries, and service modules remain available for at least ten years after the final production run.'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the studio page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addStudio( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Studio',
            'title' => 'Stillform Studio',
            'path' => 'studio',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'We design the object and its afterlife',
                'subtitle' => 'Stillform Studio',
                'text' => 'Industrial designers, electronics engineers, and manufacturing partners working together from the first sketch to the final service part.',
                'files' => [['id' => $this->img( 'studio' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'team' ), 'type' => 'file'],
                'position' => 'end',
                'ratio' => '1-2',
                'text' => "## One table, all the constraints\n\nStillform began in 2022 with a simple frustration: well-made objects were becoming harder to understand, maintain, and keep. The team formed around the belief that technology can be advanced without becoming opaque.\n\nDesign, engineering, sourcing, firmware, packaging, and service review the same prototype. A beautiful enclosure does not get to hide a weak repair path; a clever feature does not get to impose a permanent account.",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'The studio standard',
                'cards' => [
                    ['title' => 'Clear in use', 'text' => 'The primary action must be understandable from the object, not recovered from a settings page.'],
                    ['title' => 'Quiet at rest', 'text' => 'No unnecessary status light, notification, movement, or network activity when attention belongs elsewhere.'],
                    ['title' => 'Possible to repair', 'text' => 'Wear parts, high-risk electronics, documentation, tools, and service pricing are reviewed before launch.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'slideshow', 'group' => 'main', 'data' => [
                'title' => 'From study to production',
                'main' => true,
                'files' => [
                    ['id' => $this->slideImg( 'materials' ), 'type' => 'file'],
                    ['id' => $this->slideImg( 'team' ), 'type' => 'file'],
                    ['id' => $this->slideImg( 'workshop' ), 'type' => 'file'],
                    ['id' => $this->slideImg( 'packaging' ), 'type' => 'file'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Material ledger',
                'header' => 'row+col',
                'table' => [
                    ['Material', 'Where it is used', 'Selection rule', 'End-of-life route'],
                    ['Recycled aluminium', 'Bodies, arms, control rings', 'Durable, refinishable, widely recycled', 'Separate and recycle by alloy'],
                    ['Steel', 'Fasteners, weights, pivots', 'High cycle life and standard tooling', 'Magnetic separation and recycling'],
                    ['Silicone', 'Feet and cable strain relief', 'Replaceable without adhesive', 'Return through service programme'],
                    ['Paper fibre', 'Packaging and printed guides', 'FSC-certified, no plastic lamination', 'Household paper recycling'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'What collaborators notice',
                'items' => [
                    ['name' => 'Elena Rohe', 'role' => 'Workplace architect, Voss & Field', 'text' => 'Stillform treats technology as part of the room. The products are precise enough for a specification and quiet enough not to dictate the interior.'],
                    ['name' => 'Marcus Imai', 'role' => 'Manufacturing engineer, Kito Works', 'text' => 'The repair brief arrived with the first tolerance review. That changed the joint design early, when changing it was still inexpensive.'],
                    ['name' => 'Leah Morgan', 'role' => 'Product director, Northbank Studio', 'text' => 'They remove features with the same care other teams add them. What remains feels complete rather than minimal for its own sake.'],
                ],
            ]],
            ['id' => 'studio-contact', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Bring a thoughtful project to the studio',
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates an article lead element with the file reference used by previews.
     *
     * @param string $title Article title
     * @param string $text Article introduction
     * @param string $fileId Cover file ID
     * @return array<string, mixed> Article content element
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
     * Creates a closing product call to action for a journal article.
     *
     * @param string $title Hero title
     * @param string $text Hero text
     * @return array<string, mixed> Hero content element
     */
    protected function articleHero( string $title, string $text ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
            'title' => $title,
            'subtitle' => 'Stillform',
            'text' => $text,
            'url' => '/collection',
            'button' => 'Explore the collection',
            'url-alternative' => '/journal',
            'button-alternative' => 'Back to the journal',
        ]];
    }


    /**
     * Creates the shared Stillform footer and returns its ID.
     *
     * @return string Element ID
     */
    protected function element() : string
    {
        if( !isset( $this->element ) )
        {
            $cards = [
                ['title' => 'Products', 'text' => "- [Collection](/collection)\n- [Beam One](/collection#products)\n- [Dial One](/collection#products)\n- [Dock One](/collection#products)"],
                ['title' => 'Company', 'text' => "- [Studio](/studio)\n- [Journal](/journal)\n- [Contact](/contact)"],
                ['title' => 'Support', 'text' => "- [Product support](/support)\n- [Set up Beam One](/support/beam-one)\n- [Care and repair](/support/care-and-repair)"],
                ['title' => 'Contact', 'text' => "- [General enquiries](/contact)\n- [orders@stillform.example](mailto:orders@stillform.example)\n- [trade@stillform.example](mailto:trade@stillform.example)\n- [support@stillform.example](mailto:support@stillform.example)"],
            ];

            $element = Element::forceCreate( [
                'lang' => 'en',
                'type' => 'cards',
                'name' => 'Stillform footer',
                'data' => ['type' => 'cards', 'data' => ['title' => 'Stillform', 'cards' => $cards]],
                'editor' => 'demo',
            ] );

            $version = $element->versions()->forceCreate( [
                'lang' => 'en',
                'data' => [
                    'lang' => 'en',
                    'type' => 'cards',
                    'name' => 'Stillform footer',
                    'data' => ['title' => 'Stillform', 'cards' => $cards],
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
     * Returns the ID of the primary Stillform image.
     *
     * @return string File ID
     */
    protected function file() : string
    {
        return $this->img( 'home' );
    }


    /**
     * Creates the Stillform home page and returns it.
     *
     * @param string $journalId Journal page ID referenced by listing elements
     * @return Page Home page
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
                'title' => 'Technology, made to belong',
                'subtitle' => 'Stillform — quiet tools for focused rooms',
                'text' => 'We design useful connected objects with precise physical controls, repairable construction, and no demand for more attention than the task deserves.',
                'url' => '/collection',
                'button' => 'Explore the collection',
                'url-alternative' => '/studio',
                'button-alternative' => 'Visit the studio',
                'files' => [
                    ['id' => $this->img( 'beam' ), 'type' => 'file'],
                    ['id' => $this->img( 'dial' ), 'type' => 'file'],
                    ['id' => $this->img( 'dock' ), 'type' => 'file'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'The Stillform collection',
                'cards' => [
                    ['title' => 'Beam One', 'text' => "Adaptive task light with direct controls and a replaceable light engine.\n\n[See Beam One](/collection#products)", 'file' => ['id' => $this->img( 'beam' ), 'type' => 'file']],
                    ['title' => 'Dial One', 'text' => "A tactile controller for the digital actions your hand repeats every day.\n\n[See Dial One](/collection#products)", 'file' => ['id' => $this->img( 'dial' ), 'type' => 'file']],
                    ['title' => 'Dock One', 'text' => "A stable 140 W charging hub with serviceable power and port modules.\n\n[See Dock One](/collection#products)", 'file' => ['id' => $this->img( 'dock' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'materials' ), 'type' => 'file'],
                'position' => 'start',
                'ratio' => '1-2',
                'text' => "## Quality is a system, not a finish\n\nA product feels resolved when its controls are clear, its materials age honestly, and its hidden construction is given the same attention as the visible surface.\n\nStillform products open with standard tools. High-wear parts remain separate modules. Core functions work without an account or permanent connection. Packaging, service documentation, and spare-part pricing are designed alongside the object—not after it.\n\n[See how the studio works](/studio)",
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'Made for daily work',
                'items' => [
                    ['name' => 'Sofia Lind', 'role' => 'Industrial designer, Malmö', 'text' => 'Beam One is adjustable without feeling mechanical. I move it constantly, but I rarely think about the lamp itself.'],
                    ['name' => 'Daniel Cho', 'role' => 'Film editor, London', 'text' => 'Dial One gives timeline movement a physical rhythm. After a week, reaching for it felt as natural as reaching for the keyboard.'],
                    ['name' => 'Mara Velasquez', 'role' => 'Architect, Madrid', 'text' => 'The products sit comfortably in a project because the material story, technical files, and repair plan are all equally considered.'],
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
                'title' => 'Good questions before buying',
                'items' => [
                    ['title' => 'Will the products work without an account?', 'text' => 'Yes. All primary functions and local preferences work without an account, subscription, or internet connection.'],
                    ['title' => 'What can be repaired?', 'text' => 'Cables, power supplies, batteries, controls, light engines, and port boards are designed as replaceable service parts.'],
                    ['title' => 'How long is the warranty?', 'text' => 'Products carry a five-year warranty. User-replaceable cables and wear parts carry two years. Paid repairs remain available after warranty.'],
                    ['title' => 'Do you support workplace projects?', 'text' => 'Yes. Trade support includes samples, specifications, finish guidance, project pricing, and coordinated delivery.'],
                ],
            ]],
            ['id' => 'contact', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Find the right object for the room',
            ]],
            ['type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        $meta = [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => 'Stillform designs calm, repairable technology for focused rooms: adaptive light, tactile controls, and considered desktop power.',
                'keywords' => 'Stillform, design technology, repairable electronics, task light, tactile controller, charging hub',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => 'Stillform | Technology, Made to Belong',
                'description' => 'Quiet, repairable tools with precise physical controls and no demand for unnecessary attention.',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        $page = Page::forceCreate( [
            'lang' => 'en',
            'name' => 'Home',
            'title' => 'Stillform | Technology, Made to Belong',
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
                'title' => 'Stillform | Technology, Made to Belong',
                'path' => '',
                'tag' => 'root',
                'domain' => '',
                'theme' => $this->theme,
                'status' => 1,
                'cache' => 5,
            ],
            'aux' => [
                'config' => $config,
                'meta' => $meta,
                'content' => $content,
            ],
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
     * Creates the Stillform SVG logo and returns its file ID.
     *
     * @return string File ID
     */
    protected function logoFile() : string
    {
        if( !isset( $this->logoFile ) )
        {
            $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 390 80" role="img" aria-labelledby="title desc">
  <title id="title">Stillform logo</title>
  <desc id="desc">Stillform wordmark with a balanced circular control symbol</desc>
  <g fill="none" fill-rule="evenodd">
    <rect x="5" y="5" width="70" height="70" rx="18" fill="#991B1B"/>
    <circle cx="40" cy="40" r="20" stroke="#FFFFFF" stroke-width="4"/>
    <path d="M40 20v40M20 40h40" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" opacity=".7"/>
    <circle cx="40" cy="40" r="6" fill="#FFFFFF"/>
    <path d="M40 10v7" stroke="#F8F9FA" stroke-width="3" stroke-linecap="round"/>
    <text x="96" y="51" fill="#0F172A" font-family="ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif" font-size="38" font-weight="600" letter-spacing="-1.3">STILLFORM</text>
    <path d="M98 62h54" stroke="#991B1B" stroke-width="3" stroke-linecap="round"/>
    <path d="M164 62h30" stroke="#4338CA" stroke-width="3" stroke-linecap="round"/>
  </g>
</svg>
SVG;

            $disk = Storage::disk( config( 'cms.disk', 'public' ) );
            $path = rtrim( 'cms/' . $this->tenant, '/' ) . '/stillform-logo.svg';

            if( !$disk->put( $path, $svg ) ) {
                throw new \Aimeos\Cms\Exception( sprintf( 'Unable to store logo "%s"', $path ) );
            }

            $data = [
                'mime' => 'image/svg+xml',
                'lang' => 'en',
                'name' => 'Stillform logo',
                'path' => $path,
                'previews' => ['500' => $path],
                'description' => ['en' => 'Stillform wordmark with a balanced circular control symbol'],
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
     * Creates a Premium demo page below the given parent and returns it.
     *
     * @param array<string, mixed> $data Page attributes
     * @param array<int, array<string, mixed>> $content Content elements
     * @param Page $parent Parent page
     * @param array<int, string> $fileIds Additional file IDs to attach
     * @param array<string, array<string, mixed>|object> $meta Meta entries keyed by type
     * @return Page Created page
     */
    protected function page( array $data, array $content, Page $parent, array $fileIds = [], array $meta = [] ) : Page
    {
        $elementId = $this->element();
        $fileId = $this->file();
        $description = self::DESCRIPTIONS[$data['path'] ?? ''] ?? $data['title'] ?? '';

        $meta = $data['meta'] ?? $meta ?: [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => $description,
                'keywords' => 'Stillform, design technology, repairable electronics, industrial design, calm technology',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => $data['title'] ?? '',
                'description' => $description,
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        $content[] = ['type' => 'reference', 'refid' => $elementId, 'group' => 'footer'];

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
     * Builds the Premium technology-product demo page tree.
     */
    protected function pages() : void
    {
        $journalId = (string) Str::uuid7();
        $home = $this->home( $journalId );

        $this->addProducts( $home )
            ->addStudio( $home )
            ->addBlog( $home, $journalId )
            ->addDocs( $home )
            ->addContact( $home );
    }


    /**
     * Creates a fixed 2:1 slideshow image and returns its file ID.
     *
     * @param string $key Photo key from self::PHOTOS
     * @return string File ID
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
