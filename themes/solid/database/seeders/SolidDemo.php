<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Database\Seeders;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Validation;
use Illuminate\Support\Str;


/**
 * Solid theme demo for the fictional Brickline Build & Renovation company.
 */
class SolidDemo extends AbstractDemo
{
    /** @var array<string, string> Meta descriptions keyed by page path */
    protected const DESCRIPTIONS = [
        'about' => 'Meet the Brickline team: a Leeds builder with its own carpenters, bricklayers and site managers, fixed quotes and a ten-year workmanship guarantee.',
        'contact' => 'Ask Brickline for a free site visit and a fixed quote for your extension, loft conversion, kitchen or bathroom in Leeds and West Yorkshire.',
        'extensions' => 'Single and double storey house extensions in Leeds and West Yorkshire, from drawings and building control to the finished room.',
        'imprint' => 'Legal notice of Brickline Build & Renovation Ltd, Leeds.',
        'kitchens-bathrooms' => 'Kitchen and bathroom renovations by one team: stripping out, plumbing, electrics, tiling and fitting, with a fixed quote and schedule.',
        'loft-conversions' => 'Dormer and Velux loft conversions in Leeds that add a bedroom and bathroom without giving up your garden.',
        'projects' => 'Extensions, loft conversions, kitchens and bathrooms Brickline has built across Leeds and West Yorkshire, with before and after photos.',
        'roundhay-extension' => 'A single storey rear extension in Roundhay with a new open-plan kitchen, roof lanterns and bi-fold doors, built in fourteen weeks.',
        'headingley-loft' => 'A dormer loft conversion in Headingley that added a main bedroom with en suite bathroom to a Victorian terrace.',
        'chapel-allerton-bathroom' => 'A dated family bathroom in Chapel Allerton turned into a walk-in shower room with underfloor heating in three weeks.',
        'services' => 'House extensions, loft conversions and kitchen and bathroom renovations from one Leeds building team with fixed quotes.',
    ];

    /**
     * Curated Unsplash photos used by the builder demo.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    protected const PHOTOS = [
        'attic' => ['photo-1590725140246-20acdee442be', 'Converted loft', 'Bright converted loft with exposed timber beams, a fitted kitchen and roof windows'],
        'bath-new' => ['photo-1584622650111-993a426fbf0a', 'Renovated bathroom', 'Modern bathroom with a glass walk-in shower, wide vanity and large mirror'],
        'bath-old' => ['photo-1552321554-5fefe8c9ef14', 'Bathroom before renovation', 'Small dated bathroom with a plain basin and bare white walls'],
        'carpenter' => ['photo-1589939705384-5185137a7f0f', 'Carpenter on site', 'Carpenter in safety gear measuring timber beams on a workbench at a building site'],
        'crew' => ['photo-1541888946425-d81bb19240f5', 'Site team', 'Building team in high-visibility vests standing on a prepared construction site'],
        'electrician' => ['photo-1621905251189-08b45d6a269e', 'Electrician at work', 'Electrician in a hard hat and gloves wiring a distribution board'],
        'house' => ['photo-1600566753190-17f0baa2a6c3', 'Finished extension', 'Modern house extension with timber cladding, large glazing and a gravel drive'],
        'house-dusk' => ['photo-1600585154340-be6161a56a0c', 'Extension at dusk', 'Contemporary home extension with lit floor-to-ceiling windows at dusk'],
        'insulation' => ['photo-1607400201889-565b1ee75f8e', 'Insulating the walls', 'Builder fitting mineral wool insulation between timber studs'],
        'kitchen' => ['photo-1556911220-bff31c812dba', 'New kitchen', 'Bright fitted kitchen with white units, a marble worktop and fresh produce'],
        'kitchen-old' => ['photo-1717331822162-74e5eaf4d038', 'Kitchen before the extension', 'Narrow dated galley kitchen with green units, an old cooker and a small window'],
        'living' => ['photo-1600607687939-ce8a6c25118c', 'Open-plan living area', 'Open-plan living area with a timber wall, sofa and kitchen beyond'],
        'loft-old' => ['photo-1553969536-7abe08c6132d', 'Loft before conversion', 'Bare unconverted loft with exposed rafters and loose floorboards'],
        'painters' => ['photo-1574359411659-15573a27fd0c', 'Exterior painting', 'Decorators on ladders painting the timber facade of a house'],
        'plans' => ['photo-1503387762-592deb58ef4e', 'Planning the build', 'Site manager drawing on building plans with a pencil and a rolled drawing'],
        'roof' => ['photo-1632759145351-1d592919f522', 'Roof work', 'Roofer working on the pitched roof of a brick house with a ladder'],
        'site' => ['photo-1504307651254-35680f356dfd', 'Foundations', 'Builders placing steel reinforcement for a concrete slab'],
    ];

    private string $element;
    private string $projectsId;
    private string $logoFile;


    /**
     * Creates the about page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addAbout( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'About',
            'title' => 'About Brickline | Leeds Builders Since 1998',
            'path' => 'about',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Builders who answer the phone',
                'subtitle' => 'About Brickline',
                'text' => 'Family run since 1998, with our own carpenters, bricklayers and site managers. The person who quotes your job is the person who runs it.',
                'buttons' => [
                    ['label' => 'Book a site visit', 'url' => '/contact'],
                    ['label' => 'See our work', 'url' => '/projects'],
                ],
                'background' => ['id' => $this->img( 'crew' ), 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'plans' ), 'type' => 'file'],
                'position' => 'grid-start',
                'ratio' => '1-1',
                'text' => "## One team from first sketch to handover\n\nTom Hartley started Brickline with a van and a cement mixer. Twenty-five years later, the company employs 28 tradespeople, but the rules are the same: turn up when we say we will, keep the site tidy, and put the price in writing before the first brick is laid.\n\nWe don't sell your job on to subcontractors. Our own crews handle groundworks, brickwork, carpentry, plastering and finishing, while trusted, long-standing partners cover electrics, gas and glazing.",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Checked and certified',
                'layout' => 'badges',
                'cards' => [
                    ['title' => 'FMB member', 'text' => 'Federation of Master Builders, vetted and inspected'],
                    ['title' => 'TrustMark', 'text' => 'Government endorsed quality scheme'],
                    ['title' => 'NICEIC', 'text' => 'Approved electrical contractor partner'],
                    ['title' => 'Gas Safe', 'text' => 'Registered engineers for all gas work'],
                    ['title' => '£10m insurance', 'text' => 'Public liability cover on every site'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'What our clients say',
                'items' => [
                    ['name' => 'Sarah and James P.', 'role' => 'Rear extension, Roundhay', 'text' => 'The quote was the bill. No surprises, no extras sneaking in, and the site was swept every evening while we were still living in the house.'],
                    ['name' => 'Priya N.', 'role' => 'Loft conversion, Headingley', 'text' => 'Tom talked us out of a dormer we did not need and saved us £6,000. The finished room feels like it was always part of the house.'],
                    ['name' => 'Mark D.', 'role' => 'Bathroom, Chapel Allerton', 'text' => 'Three weeks, exactly as planned. The tiler was a perfectionist, and we got a WhatsApp photo update at the end of every day.'],
                ],
            ]],
        ], $home );

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
            'title' => 'Contact Brickline | Free Site Visit and Fixed Quote',
            'path' => 'contact',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => 'quote', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Get a free quote',
                'description' => 'Tell us about your project and add photos or drawings if you have them. We reply within one working day and arrange a free site visit.',
                'inputs' => [
                    ['field' => 'name', 'required' => true, 'input' => 'text'],
                    ['field' => 'telephone', 'required' => true, 'input' => 'text'],
                    ['field' => 'email', 'required' => true, 'input' => 'text'],
                    ['field' => 'Project type', 'required' => true, 'input' => 'select', 'options' => "House extension\nLoft conversion\nKitchen\nBathroom\nSomething else"],
                    ['field' => 'Postcode', 'required' => true, 'input' => 'text'],
                ],
                'attachments' => 3,
            ]],
            ['id' => Utils::uid(), 'type' => 'map', 'group' => 'main', 'data' => [
                'title' => 'Our yard',
                'text' => "**Brickline Build & Renovation**\nUnit 4, Cross Green Industrial Estate · Leeds LS9 0SG\n\n**Call**\n0113 496 0123 · Monday to Friday 07:30–17:00, Saturday 08:00–12:00\n\n**Email**\ninfo@brickline.example\n\nWe work across Leeds, Bradford, Harrogate, Wakefield and York.",
                'location' => [
                    'latitude' => 53.7887,
                    'longitude' => -1.5101,
                    'zoom' => 15,
                ],
                'button' => 'Open in OpenStreetMap',
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the imprint page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addImprint( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Imprint',
            'title' => 'Imprint | Brickline Build & Renovation',
            'path' => 'imprint',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "# Imprint\n\n**Brickline Build & Renovation Ltd**\nUnit 4, Cross Green Industrial Estate\nLeeds LS9 0SG\nUnited Kingdom\n\nTelephone: 0113 496 0123\nEmail: info@brickline.example\n\nRegistered in England and Wales, company number 01234567\nVAT number GB 123 4567 89\nDirector: Tom Hartley\n\nThis is a demo website for the Solid theme. Brickline is a fictional company.",
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the projects page and its project pages below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addProjects( Page $home ) : static
    {
        $projects = $this->projects( $home );

        $this->project( $projects, [
            'name' => 'Roundhay extension',
            'title' => 'Rear Extension in Roundhay',
            'path' => 'roundhay-extension',
        ], 'A kitchen that finally fits the family',
            "Sarah and James had a narrow galley kitchen and a dining room nobody used. We knocked through, added a 5 metre single storey extension and gave the house one big room that opens onto the garden.\n\nThe steel went in over a weekend so the family could stay at home throughout. Two roof lanterns bring daylight into the middle of the plan and the bi-fold doors run the full width of the back wall.",
            'house', ['kitchen-old', 'living'],
            [
                ['title' => '14', 'text' => 'Weeks from groundworks to handover'],
                ['title' => '32 m²', 'text' => 'Additional floor space'],
                ['title' => '£68k', 'text' => 'Fixed price, no extras'],
            ],
            [
                ['label' => 'Weeks 1–2', 'title' => 'Groundworks', 'text' => 'Excavation, drainage diversion and concrete foundations, signed off by building control.'],
                ['label' => 'Weeks 3–6', 'title' => 'Shell and roof', 'text' => 'Blockwork, steel beam, flat roof with two lanterns and a watertight shell.'],
                ['label' => 'Weeks 7–10', 'title' => 'First fix and plaster', 'text' => 'Underfloor heating, electrics and plumbing, then plastering and screed.'],
                ['label' => 'Weeks 11–14', 'title' => 'Kitchen and handover', 'text' => 'Kitchen fitting, decoration, snagging and a walk-through with the owners.'],
            ],
            ['site', 'insulation', 'kitchen', 'house-dusk'],
        );

        $this->project( $projects, [
            'name' => 'Headingley loft',
            'title' => 'Dormer Loft Conversion in Headingley',
            'path' => 'headingley-loft',
        ], 'A main bedroom under the roof',
            "The Victorian terrace had three bedrooms and a growing family. A rear dormer turned the dusty loft into a main bedroom with an en suite shower room and built-in storage under the eaves.\n\nWe worked from scaffolding at the back of the house, so the stairs were only opened up in the final two weeks. Planning permission wasn't needed; we handled the building regulations and the party wall notices.",
            'attic', ['loft-old', 'attic'],
            [
                ['title' => '9', 'text' => 'Weeks from scaffold up to scaffold down'],
                ['title' => '+1', 'text' => 'Bedroom with en suite'],
                ['title' => '£54k', 'text' => 'Fixed price, no extras'],
            ],
            [
                ['label' => 'Week 1', 'title' => 'Scaffolding and steels', 'text' => 'Scaffold and roof access at the rear, new floor joists and steel beams.'],
                ['label' => 'Weeks 2–4', 'title' => 'Dormer', 'text' => 'Dormer framing, roof covering and windows, the loft is weathertight.'],
                ['label' => 'Weeks 5–7', 'title' => 'Insulation and services', 'text' => 'Insulation to current regulations, electrics, plumbing for the en suite.'],
                ['label' => 'Weeks 8–9', 'title' => 'Staircase and finishing', 'text' => 'New staircase, fire doors, tiling, decoration and building control sign-off.'],
            ],
            ['roof', 'insulation', 'attic', 'electrician'],
        );

        $this->project( $projects, [
            'name' => 'Chapel Allerton bathroom',
            'title' => 'Bathroom Renovation in Chapel Allerton',
            'path' => 'chapel-allerton-bathroom',
        ], 'From dated bathroom to walk-in shower',
            "The old bathroom had a bath nobody used and a shower that leaked into the kitchen ceiling. We stripped the room back to the joists, moved the waste pipes and built a level walk-in shower with a glass screen.\n\nUnderfloor heating, a wall-hung vanity and large format tiles make the small room feel twice its size.",
            'bath-new', ['bath-old', 'bath-new'],
            [
                ['title' => '3', 'text' => 'Weeks from strip-out to handover'],
                ['title' => '6 m²', 'text' => 'Fully retiled room'],
                ['title' => '£14k', 'text' => 'Fixed price, no extras'],
            ],
            [
                ['label' => 'Days 1–3', 'title' => 'Strip-out', 'text' => 'Old suite, tiles and flooring removed, joists checked and repaired.'],
                ['label' => 'Days 4–8', 'title' => 'Plumbing and electrics', 'text' => 'New waste routes, shower valve, underfloor heating and lighting.'],
                ['label' => 'Days 9–13', 'title' => 'Tanking and tiling', 'text' => 'Waterproof tanking, then large format tiles on walls and floor.'],
                ['label' => 'Days 14–15', 'title' => 'Fitting', 'text' => 'Vanity, toilet, glass screen and fittings, followed by a deep clean.'],
            ],
            ['bath-old', 'bath-new'],
        );

        return $this;
    }


    /**
     * Creates the services page and the service pages below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addServices( Page $home ) : static
    {
        $services = $this->page( [
            'lang' => 'en',
            'name' => 'Services',
            'title' => 'Building Services | Brickline Build & Renovation',
            'path' => 'services',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'What we build',
                'subtitle' => 'Our services',
                'text' => 'Extensions, loft conversions, kitchens and bathrooms. One team, one contract and one fixed price for the whole job.',
                'buttons' => [
                    ['label' => 'Get a free quote', 'url' => '/contact'],
                ],
            ]],
            $this->services(),
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Common questions',
                'items' => [
                    ['title' => 'Do you handle planning permission?', 'text' => 'Yes. Our architect partners prepare the drawings and we submit the planning or permitted development application and the building regulations plans for you.'],
                    ['title' => 'Is the quote really fixed?', 'text' => 'Yes. After the site visit you get an itemised quote. The price only changes if you ask for changes, and every change is agreed in writing before the work is done.'],
                    ['title' => 'Can we stay at home during the work?', 'text' => 'Almost always. We plan noisy and dusty work in blocks, seal off the work area and keep a working kitchen or bathroom available wherever possible.'],
                    ['title' => 'What guarantee do I get?', 'text' => 'Ten years on our workmanship, backed by an insurance-backed warranty, plus the manufacturer guarantees for all fitted products.'],
                ],
            ]],
        ], $home );

        $this->service( $services, [
            'name' => 'Extensions',
            'title' => 'House Extensions in Leeds | Brickline Build & Renovation',
            'path' => 'extensions',
        ], 'More room without moving house', 'house', 'site',
            "## Single storey, double storey, wrap-around\n\nAn extension is usually the quickest way to the kitchen-diner or extra bedroom you need. We take care of the whole process: measured survey, drawings, planning or permitted development, building control, party wall notices and the build itself.\n\nOur own crews dig the foundations, lay the bricks, fit the steels and finish the room, so there is no waiting for the next trade to turn up.",
            [
                ['title' => 'Drawings and approvals', 'text' => 'Planning, permitted development, building regulations and party wall notices.'],
                ['title' => 'Groundworks and shell', 'text' => 'Foundations, drainage, brickwork, steels and a watertight roof.'],
                ['title' => 'Ready to use', 'text' => 'Electrics, heating, plastering, flooring and decoration included.'],
            ],
        );

        $this->service( $services, [
            'name' => 'Loft conversions',
            'title' => 'Loft Conversions in Leeds | Brickline Build & Renovation',
            'path' => 'loft-conversions',
        ], 'Your next bedroom is already upstairs', 'attic', 'roof',
            "## Dormer, hip-to-gable and Velux conversions\n\nMost loft conversions don't need planning permission and leave your garden untouched. We check the head height, design the stairs and plan the conversion around the room you want: a main bedroom with en suite, a children's room or a quiet office.\n\nThe work is done from scaffolding outside, so the house stays liveable until the new staircase goes in.",
            [
                ['title' => 'Survey and design', 'text' => 'Head height check, structural calculations and stair layout.'],
                ['title' => 'Structure and roof', 'text' => 'Floor joists, steels, dormer or roof windows and insulation.'],
                ['title' => 'Finished room', 'text' => 'Staircase, fire safety, en suite, storage and decoration.'],
            ],
        );

        $this->service( $services, [
            'name' => 'Kitchens & bathrooms',
            'title' => 'Kitchen and Bathroom Renovation in Leeds | Brickline',
            'path' => 'kitchens-bathrooms',
        ], 'Rooms that work as hard as you do', 'kitchen', 'bath-new',
            "## Strip-out to final clean\n\nKitchens and bathrooms involve more trades than any other room. We coordinate all of them: removal, plumbing, electrics, plastering, tiling and fitting, in a schedule you get before we start.\n\nBring your own design or work with our partner showrooms in Leeds. We fit what you choose and stand behind the work for ten years.",
            [
                ['title' => 'Plan and schedule', 'text' => 'Layout, measurements and a day-by-day schedule before work starts.'],
                ['title' => 'All trades included', 'text' => 'Plumbing, electrics, plastering and tiling by one team.'],
                ['title' => 'Clean handover', 'text' => 'Fitted, tested, cleaned and checked together with you.'],
            ],
        );

        return $this;
    }


    /**
     * Creates the shared Brickline footer and returns its ID.
     *
     * @return string Element ID
     */
    protected function element() : string
    {
        return $this->element ??= $this->saveElement( 'cards', 'Brickline footer', ['columns' => '4', 'cards' => [
            ['title' => 'Brickline', 'text' => "Builders for extensions, loft conversions and renovations in Leeds and West Yorkshire since 1998."],
            ['title' => 'Services', 'text' => "- [House extensions](/extensions)\n- [Loft conversions](/loft-conversions)\n- [Kitchens and bathrooms](/kitchens-bathrooms)"],
            ['title' => 'Company', 'text' => "- [Our projects](/projects)\n- [About Brickline](/about)\n- [Imprint](/imprint)"],
            ['title' => 'Contact', 'text' => "Unit 4, Cross Green Industrial Estate\nLeeds LS9 0SG\n\n0113 496 0123\n[Get a free quote](/contact)"],
        ]] );
    }


    /**
     * Returns the ID of the primary builder image.
     *
     * @return string File ID
     */
    protected function file() : string
    {
        return $this->img( 'carpenter' );
    }


    /**
     * Creates the Brickline home page and returns it.
     *
     * @return Page Home page
     */
    protected function home() : Page
    {
        $elementId = $this->element();
        $fileId = $this->file();

        $config = [
            'website' => Validation::entry( 'website', ['title' => 'Brickline Build & Renovation'], 'config' ),
        ] + $this->logos( $this->logoFile() ) + [
            'solid::business' => [
                'type' => 'solid::business',
                'files' => [],
                'data' => [
                    'name' => 'Brickline Build & Renovation Ltd',
                    'business-type' => 'GeneralContractor',
                    'street-address' => 'Unit 4, Cross Green Industrial Estate',
                    'postal-code' => 'LS9 0SG',
                    'locality' => 'Leeds',
                    'country' => 'GB',
                    'telephone' => '+44 113 496 0123',
                    'email' => 'info@brickline.example',
                    'area' => 'Leeds, Bradford, Harrogate, Wakefield, York',
                    'price-range' => '£££',
                    'call-button' => true,
                    'hours' => [
                        ['id' => 'mon', 'day' => 'Monday', 'opens' => '07:30', 'closes' => '17:00'],
                        ['id' => 'tue', 'day' => 'Tuesday', 'opens' => '07:30', 'closes' => '17:00'],
                        ['id' => 'wed', 'day' => 'Wednesday', 'opens' => '07:30', 'closes' => '17:00'],
                        ['id' => 'thu', 'day' => 'Thursday', 'opens' => '07:30', 'closes' => '17:00'],
                        ['id' => 'fri', 'day' => 'Friday', 'opens' => '07:30', 'closes' => '17:00'],
                        ['id' => 'sat', 'day' => 'Saturday', 'opens' => '08:00', 'closes' => '12:00'],
                    ],
                ],
            ],
        ];

        $content = [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Built right. Built on time.',
                'subtitle' => 'Leeds builders since 1998',
                'text' => 'Extensions, loft conversions and renovations by our own crews, with a fixed quote, a clear schedule and a ten-year workmanship guarantee.',
                'buttons' => [
                    ['label' => 'Get a free quote', 'url' => '/contact'],
                    ['label' => 'See our projects', 'url' => '/projects'],
                ],
                'background' => ['id' => $fileId, 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'layout' => 'figures',
                'cards' => [
                    ['title' => '25+', 'text' => 'Years building in Leeds'],
                    ['title' => '640', 'text' => 'Finished projects'],
                    ['title' => '10', 'text' => 'Years workmanship guarantee'],
                    ['title' => '4.9/5', 'text' => 'From 212 client reviews'],
                ],
            ]],
            $this->services(),
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'How we work',
                'layout' => 'horizontal',
                'items' => [
                    ['label' => 'Step 1', 'title' => 'Free site visit', 'text' => 'We look at the house, listen to your plans and check what is possible.'],
                    ['label' => 'Step 2', 'title' => 'Fixed quote', 'text' => 'An itemised price and schedule within ten working days.'],
                    ['label' => 'Step 3', 'title' => 'Build', 'text' => 'One site manager, our own crews and a photo update every evening.'],
                    ['label' => 'Step 4', 'title' => 'Handover', 'text' => 'Snagging walk-through, certificates and your ten-year guarantee.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'blog', 'group' => 'main', 'data' => [
                'title' => 'Recent projects',
                'layout' => 'cards',
                'parent-page' => ['value' => $this->projectsId, 'label' => 'Projects'],
                'order' => '_lft',
                'limit' => 3,
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'Trusted by homeowners',
                'items' => [
                    ['name' => 'Sarah and James P.', 'role' => 'Rear extension, Roundhay', 'text' => 'The quote was the bill. No surprises, and the site was swept every evening while we were still living in the house.'],
                    ['name' => 'Priya N.', 'role' => 'Loft conversion, Headingley', 'text' => 'They talked us out of a dormer we did not need and saved us £6,000. The new room feels like it was always there.'],
                    ['name' => 'Mark D.', 'role' => 'Bathroom, Chapel Allerton', 'text' => 'Three weeks, exactly as planned, with a photo update at the end of every day.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Planning a project?',
                'text' => 'Book a free site visit. You get honest advice and a fixed quote, with no obligation.',
                'buttons' => [
                    ['label' => 'Get a free quote', 'url' => '/contact'],
                    ['label' => 'Call 0113 496 0123', 'url' => 'tel:+441134960123'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        $meta = [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => 'Brickline builds house extensions, loft conversions, kitchens and bathrooms in Leeds and West Yorkshire with fixed quotes and a ten-year guarantee.',
                'keywords' => 'builder Leeds, house extension, loft conversion, kitchen renovation, bathroom renovation, general contractor, West Yorkshire',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => 'Brickline Build & Renovation | Leeds Builders',
                'description' => 'Extensions, loft conversions and renovations by our own crews, with a fixed quote and a ten-year guarantee.',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        return $this->saveRoot( 'Brickline Build & Renovation | Builders in Leeds', $config, $meta, $content, $elementId, $fileId );
    }


    /**
     * Creates the Brickline SVG logo and returns its file ID.
     *
     * @return string File ID
     */
    protected function logoFile() : string
    {
        if( !isset( $this->logoFile ) )
        {
            $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 380 80" role="img" aria-labelledby="title desc">
  <title id="title">Brickline logo</title>
  <desc id="desc">Yellow square with a roof outline beside the Brickline wordmark</desc>
  <rect x="4" y="8" width="64" height="64" fill="#F2B705"/>
  <path d="M16 46 36 26l20 20" fill="none" stroke="#1F2328" stroke-width="7" stroke-linecap="square"/>
  <path d="M24 52h24v10H24z" fill="#1F2328"/>
  <text x="84" y="58" fill="#FFFFFF" font-family="Arial Narrow, Roboto Condensed, Arial, sans-serif" font-size="46" font-weight="800" letter-spacing="2">BRICKLINE</text>
</svg>
SVG;

            $this->logoFile = $this->svgFile(
                $svg,
                'brickline-logo.svg',
                'Brickline logo',
                'Yellow square with a roof outline beside the Brickline wordmark',
                true,
            );
        }

        return $this->logoFile;
    }


    /**
     * Creates a Solid demo page below the given parent and returns it.
     *
     * @param array<string, mixed> $data Page attributes
     * @param array<int, array<string, mixed>> $content Content elements
     * @param Page $parent Parent page
     * @return Page Created page
     */
    protected function page( array $data, array $content, Page $parent ) : Page
    {
        $elementId = $this->element();
        $fileId = $this->ids( $content )[0] ?? $this->file();

        $footer = [
            ['id' => Utils::uid(), 'type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        return $this->savePage( $data, $content, $parent, $elementId, $fileId, $footer, 'Brickline, builder Leeds, extension, loft conversion, renovation, general contractor' );
    }


    /**
     * Builds the Solid builder demo page tree.
     */
    protected function pages() : void
    {
        $this->projectsId = (string) Str::uuid7();
        $home = $this->home();

        $this->addServices( $home )
            ->addProjects( $home )
            ->addAbout( $home )
            ->addContact( $home )
            ->addImprint( $home );
    }


    /**
     * Creates a project page below the projects page.
     *
     * @param Page $parent Projects page
     * @param array<string, string> $data Page name, title and path
     * @param string $title Article headline
     * @param string $text Article text
     * @param string $cover PHOTOS key of the cover image
     * @param array{0: string, 1: string} $compare PHOTOS keys of the before and after images
     * @param array<int, array<string, string>> $facts Key facts as figure cards
     * @param array<int, array<string, string>> $phases Build phases
     * @param array<int, string> $photos PHOTOS keys of the slideshow images
     * @return Page Created page
     */
    protected function project( Page $parent, array $data, string $title, string $text, string $cover,
        array $compare, array $facts, array $phases, array $photos ) : Page
    {
        return $this->page( $data + [
            'lang' => 'en',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article( $title, $text, $this->img( $cover ) ),
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'layout' => 'figures',
                'columns' => '3',
                'cards' => $facts,
            ]],
            ['id' => Utils::uid(), 'type' => 'before-after', 'group' => 'main', 'data' => [
                'title' => 'Before and after',
                'before' => ['id' => $this->cropped( $compare[0], 1500, 1000 ), 'type' => 'file'],
                'after' => ['id' => $this->cropped( $compare[1], 1500, 1000 ), 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'Build phases',
                'layout' => 'vertical',
                'items' => $phases,
            ]],
            ['id' => Utils::uid(), 'type' => 'slideshow', 'group' => 'main', 'data' => [
                'title' => 'On site',
                'files' => array_map( fn( $key ) => ['id' => $this->cropped( $key, 1500, 1000 ), 'type' => 'file'], $photos ),
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Thinking about something similar?',
                'text' => 'Tell us about your house and we will arrange a free site visit.',
                'buttons' => [
                    ['label' => 'Get a free quote', 'url' => '/contact'],
                ],
            ]],
        ], $parent );
    }


    /**
     * Creates the projects overview page and returns it.
     *
     * @param Page $home Home page
     * @return Page Projects page
     */
    protected function projects( Page $home ) : Page
    {
        return $this->page( [
            'id' => $this->projectsId,
            'lang' => 'en',
            'name' => 'Projects',
            'title' => 'Our Projects | Brickline Build & Renovation',
            'path' => 'projects',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Work we are proud of',
                'subtitle' => 'Our projects',
                'text' => 'Extensions, lofts, kitchens and bathrooms across Leeds and West Yorkshire, with the numbers behind each job.',
            ]],
            ['id' => 'project-list', 'type' => 'blog', 'group' => 'main', 'data' => [
                'layout' => 'cards',
                'parent-page' => ['value' => $this->projectsId, 'label' => 'Projects'],
                'order' => '_lft',
                'limit' => 12,
            ]],
        ], $home );
    }


    /**
     * Creates a service page below the services page.
     *
     * @param Page $parent Services page
     * @param array<string, string> $data Page name, title and path
     * @param string $title Hero headline
     * @param string $hero PHOTOS key of the hero image
     * @param string $image PHOTOS key of the text image
     * @param string $text Service description
     * @param array<int, array<string, string>> $steps What is included
     * @return Page Created page
     */
    protected function service( Page $parent, array $data, string $title, string $hero, string $image,
        string $text, array $steps ) : Page
    {
        return $this->page( $data + [
            'lang' => 'en',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => $title,
                'subtitle' => $data['name'],
                'buttons' => [
                    ['label' => 'Get a free quote', 'url' => '/contact'],
                    ['label' => 'See our projects', 'url' => '/projects'],
                ],
                'background' => ['id' => $this->img( $hero ), 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( $image ), 'type' => 'file'],
                'position' => 'grid-end',
                'ratio' => '1-1',
                'text' => $text,
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'What is included',
                'cards' => $steps,
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Get a fixed price',
                'text' => 'Book a free site visit and receive an itemised quote within ten working days.',
                'buttons' => [
                    ['label' => 'Get a free quote', 'url' => '/contact'],
                    ['label' => 'Call 0113 496 0123', 'url' => 'tel:+441134960123'],
                ],
            ]],
        ], $parent );
    }


    /**
     * Returns the services card element.
     *
     * @return array<string, mixed> Cards content element
     */
    protected function services() : array
    {
        return ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
            'title' => 'What we build',
            'cards' => [
                ['title' => 'House extensions', 'text' => 'Single and double storey extensions with drawings, approvals and the complete build.', 'url' => '/extensions', 'file' => ['id' => $this->img( 'house' ), 'type' => 'file']],
                ['title' => 'Loft conversions', 'text' => 'Dormer and Velux conversions that add a bedroom and bathroom under your roof.', 'url' => '/loft-conversions', 'file' => ['id' => $this->img( 'attic' ), 'type' => 'file']],
                ['title' => 'Kitchens & bathrooms', 'text' => 'Complete renovations by one team, from strip-out to the final clean.', 'url' => '/kitchens-bathrooms', 'file' => ['id' => $this->img( 'kitchen' ), 'type' => 'file']],
            ],
        ]];
    }
}
