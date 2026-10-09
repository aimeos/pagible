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
 * Solid theme demo for the fictional Courseline Build & Renovation company.
 */
class SolidDemo extends AbstractDemo
{
    /** @var array<string, string> Meta descriptions keyed by page path */
    protected const DESCRIPTIONS = [
        'about' => 'Meet the Courseline team: a Leeds builder with its own carpenters, bricklayers and site managers, fixed quotes and a ten-year workmanship guarantee.',
        'contact' => 'Ask Courseline for a free site visit and a fixed quote for your extension, loft conversion, kitchen or bathroom in Leeds, West Yorkshire, Harrogate and York.',
        'extensions' => 'Single and double storey house extensions in Leeds, West Yorkshire, Harrogate and York, from drawings and building control to the finished room.',
        'legal' => 'Company information of Courseline Build & Renovation Ltd, registered in England and Wales, Leeds.',
        'kitchens-bathrooms' => 'Kitchen and bathroom renovations coordinated by one site manager: stripping out, plumbing, electrics, tiling and fitting, with a fixed quote and schedule.',
        'loft-conversions' => 'Dormer and Velux loft conversions in Leeds that add a bedroom and bathroom without giving up your garden.',
        'privacy' => 'Privacy policy of Courseline Build & Renovation Ltd, Leeds.',
        'projects' => 'Extensions, loft conversions, kitchens and bathrooms Courseline has built across Leeds, West Yorkshire, Harrogate and York, with before and after photos.',
        'roundhay-extension' => 'A 5 metre single storey rear extension in Roundhay with a new open-plan kitchen, roof lanterns and bi-fold doors, built in fourteen weeks.',
        'headingley-loft' => 'A dormer loft conversion in Headingley that added a main bedroom with en suite bathroom to a Victorian terrace.',
        'chapel-allerton-bathroom' => 'A dated family bathroom in Chapel Allerton turned into a walk-in shower room with underfloor heating in three weeks.',
        'services' => 'House extensions, loft conversions, garage conversions, knock-throughs and kitchen and bathroom renovations from one Leeds building team with fixed quotes.',
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
            'title' => 'About Courseline | Leeds Builders Since 1998',
            'path' => 'about',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Builders who answer the phone',
                'subtitle' => 'About Courseline',
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
                'text' => "## One team from first sketch to handover\n\nTom Hartley started Courseline with a van and a cement mixer. More than twenty-five years later, the company employs 28 tradespeople, but the rules are the same: turn up when we say we will, keep the site tidy, and put the price in writing before the first brick is laid.\n\nWe don't sell your job on to subcontractors. Our own crews handle groundworks, brickwork, carpentry, plastering and finishing, while trusted, long-standing partners cover electrics, gas and glazing.\n\nOur architect and structural engineer partners draw the plans and calculate the steels, so you never have to find them yourself.",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Checked and certified',
                'layout' => 'badges',
                'cards' => [
                    ['title' => 'FMB member', 'text' => 'Federation of Master Builders, vetted and inspected, membership no. 00000 (fictional)'],
                    ['title' => 'TrustMark', 'text' => 'Government endorsed quality scheme, licence no. 0000000 (fictional)'],
                    ['title' => 'Certified electrics', 'text' => 'All electrical work by NICEIC registered partners, with Part P certificates'],
                    ['title' => 'Safe gas work', 'text' => 'All gas work by Gas Safe registered engineers'],
                    ['title' => 'Building control', 'text' => 'Completion certificate for every notifiable job'],
                    ['title' => '£10m insurance', 'text' => 'Public liability cover on every site'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'What our clients say',
                'items' => [
                    ['name' => 'Sarah and James P.', 'role' => 'Rear extension, Roundhay', 'text' => 'The quote was the bill. No surprises, no extras sneaking in, and the site was swept every evening while we were still living in the house.'],
                    ['name' => 'Priya N.', 'role' => 'Loft conversion, Headingley', 'text' => 'Tom suggested moving the stairs instead of a second dormer and saved us £6,000. The finished room feels like it was always part of the house.'],
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
            'title' => 'Contact Courseline | Free Site Visit and Fixed Quote',
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
                'text' => "**Courseline Build & Renovation**\nUnit 4, Cross Green Industrial Estate · Leeds LS9 0SG\n\n**Call**\n0113 496 0123 · Monday to Friday 07:30–17:00, Saturday 08:00–12:00\n\n**Email**\ninfo@courseline.example\n\nWe work across Leeds and West Yorkshire as well as Harrogate and York in North Yorkshire.",
                'location' => [
                    'latitude' => 53.7856,
                    'longitude' => -1.5120,
                    'zoom' => 15,
                ],
                'button' => 'Open in OpenStreetMap',
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the legal information page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addLegal( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Legal information',
            'title' => 'Legal Information | Courseline Build & Renovation',
            'path' => 'legal',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "# Legal information\n\n**Courseline Build & Renovation Ltd**\nPrivate limited company registered in England and Wales\nCompany number: 00000000 (fictional)\n\n**Registered office**\nUnit 4, Cross Green Industrial Estate\nLeeds LS9 0SG\nUnited Kingdom\n\nTelephone: 0113 496 0123\nEmail: info@courseline.example\n\nVAT number: GB 000 0000 00 (fictional)\nDirector: Tom Hartley\n\nMember of the Federation of Master Builders (membership no. 00000 (fictional)) and TrustMark registered (licence no. 0000000 (fictional)).\n\nThis is a demo website for the Solid theme. Courseline is a fictional company.",
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the privacy policy page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addPrivacy( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Privacy',
            'title' => 'Privacy Policy | Courseline Build & Renovation',
            'path' => 'privacy',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "# Privacy policy\n\n## Who is responsible\n\nCourseline Build & Renovation Ltd, Unit 4, Cross Green Industrial Estate, Leeds LS9 0SG, info@courseline.example, is the controller for your personal data under the UK GDPR and the Data Protection Act 2018.\n\n## Quote requests\n\nWhen you send the quote form, we use your name, phone number, email address, postcode, project type and any photos or drawings you attach only to answer your request, arrange the site visit and prepare your quote (Art. 6 (1) (b) UK GDPR). Requests that don't lead to a contract are deleted after twelve months.\n\n## Customers\n\nFor building work we keep contracts, invoices, plans and certificates for six years after the end of the tax year, as required by tax law, and longer where needed for guarantee claims. We share data with our architect, engineer and trade partners, building control and the guarantee insurer only as far as necessary for your project.\n\n## This website\n\nThe website doesn't use tracking or advertising cookies. Our server stores technical access data such as the IP address for seven days to protect against attacks. The map is loaded from OpenStreetMap only after you open it.\n\n## Your rights\n\nYou have the right to access, rectification, erasure, restriction of processing, data portability and to object to processing. If you are unhappy with how we handle your data, please contact us first. You can also complain to the Information Commissioner's Office (ICO), Wycliffe House, Water Lane, Wilmslow SK9 5AF, ico.org.uk.\n\nThis is a demo website for the Solid theme. Courseline is a fictional company.",
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
            "Sarah and James had a narrow galley kitchen and a dining room nobody used. We knocked through, added a 5 metre single storey extension, approved under the larger home extension scheme, and gave the house one big room that opens onto the garden.\n\nThe steel went in over a weekend so the family could stay at home throughout. Two roof lanterns bring daylight into the middle of the plan and the bi-fold doors run the full width of the back wall.",
            'house', ['kitchen-old', 'living'],
            [
                ['title' => '14', 'text' => 'Weeks from groundworks to handover'],
                ['title' => '32 m²', 'text' => 'Additional floor space'],
                ['title' => '£96k', 'text' => 'Fixed price incl. VAT, no extras'],
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
            "The Victorian terrace had three bedrooms and a growing family. A rear dormer turned the dusty loft into a main bedroom with an en suite shower room and built-in storage under the eaves.\n\nWe worked from scaffolding at the back of the house, so the stairs were only opened up in the final two weeks. As the street lies outside the conservation area, planning permission wasn't needed; we handled the building regulations, the party wall notices and the completion certificate.",
            'attic', ['loft-old', 'attic'],
            [
                ['title' => '9', 'text' => 'Weeks from scaffold up to scaffold down'],
                ['title' => '+1', 'text' => 'Bedroom with en suite'],
                ['title' => '£54k', 'text' => 'Fixed price incl. VAT, no extras'],
            ],
            [
                ['label' => 'Week 1', 'title' => 'Scaffolding and steels', 'text' => 'Scaffold and roof access at the rear, new floor joists and steel beams.'],
                ['label' => 'Weeks 2–4', 'title' => 'Dormer', 'text' => 'Dormer framing, roof covering and windows, the loft is weathertight.'],
                ['label' => 'Weeks 5–7', 'title' => 'Insulation and services', 'text' => 'Insulation to current regulations, electrics, plumbing for the en suite.'],
                ['label' => 'Weeks 8–9', 'title' => 'Staircase and finishing', 'text' => 'New staircase, fire doors, tiling, decoration and the building control completion certificate.'],
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
                ['title' => '£14k', 'text' => 'Fixed price incl. VAT, no extras'],
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
            'title' => 'Building Services | Courseline Build & Renovation',
            'path' => 'services',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'What we build',
                'subtitle' => 'Our services',
                'text' => 'Extensions, loft conversions, kitchens and bathrooms, plus garage conversions and knock-throughs. One team, one contract and one fixed price for the whole job.',
                'buttons' => [
                    ['label' => 'Get a free quote', 'url' => '/contact'],
                ],
            ]],
            $this->services(),
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Common questions',
                'items' => [
                    ['title' => 'Do you handle planning permission?', 'text' => 'Yes. Our architect partners prepare the drawings and we submit the planning application, prior approval or lawful development certificate, plus the building regulations plans, for you.'],
                    ['title' => 'Do I need an architect or structural engineer?', 'text' => 'For extensions, loft conversions and knock-throughs, yes. You don\'t have to find them yourself: our architect and structural engineer partners are part of the quote, and the engineer\'s calculations go to building control with the plans.'],
                    ['title' => 'Is the quote really fixed?', 'text' => 'Yes. After the site visit you get an itemised quote including VAT at 20%. The price only changes if you ask for changes, and every change is agreed in writing before the work is done.'],
                    ['title' => 'How do payments work?', 'text' => 'We don\'t ask for a large deposit. You pay in stages as each phase is finished and checked with you, for example after the foundations, the watertight shell and the first fix, with the last payment after snagging.'],
                    ['title' => 'How long does a project take?', 'text' => 'A single storey extension usually takes 10 to 14 weeks on site, a dormer loft conversion 8 to 10 weeks and a bathroom about three weeks. If planning permission is needed, allow 8 to 13 weeks for the council decision before we start.'],
                    ['title' => 'Do I need a party wall agreement?', 'text' => 'If we build on or near the boundary, cut into a shared wall or dig foundations within three metres of your neighbour\'s, the Party Wall Act applies. We serve the notices at least two months before work starts, and most neighbours simply agree in writing.'],
                    ['title' => 'Can we stay at home during the work?', 'text' => 'Almost always. We plan noisy and dusty work in blocks, seal off the work area and keep a working kitchen or bathroom available wherever possible.'],
                    ['title' => 'Can I pay in instalments?', 'text' => 'Yes. Besides the stage payments, you can spread the cost of projects over £5,000 across 2 to 10 years through our finance partner, subject to status. We are a credit broker, not a lender.'],
                    ['title' => 'What guarantee do I get?', 'text' => 'Ten years on our workmanship, plus an insurance-backed guarantee that covers structural defects for ten years, even if we stop trading, and the manufacturer guarantees for all fitted products. You also get the building control completion certificate at handover.'],
                ],
            ]],
        ], $home );

        $this->service( $services, [
            'name' => 'Extensions',
            'title' => 'House Extensions in Leeds | Courseline Build & Renovation',
            'path' => 'extensions',
        ], 'More room without moving house', 'house', 'site',
            "## Single storey, double storey, wrap-around\n\nAn extension is usually the quickest way to the kitchen-diner or extra bedroom you need. We take care of the whole process: measured survey, drawings, planning or permitted development, building control, party wall notices and the build itself.\n\nOur own crews dig the foundations, lay the bricks, fit the steels and finish the room, so there is no waiting for the next trade to turn up.\n\nA finished single storey extension costs £2,000 to £3,000 per m² including VAT, depending on the roof, glazing and kitchen. Most of our extensions of 20 to 40 m² come to £55,000 to £110,000.\n\nThe same crews also convert garages into living space and take out load-bearing walls for open-plan rooms, with the steel calculated by our structural engineer.",
            [
                ['title' => 'Drawings and approvals', 'text' => 'Planning, permitted development, building regulations and party wall notices.'],
                ['title' => 'Groundworks and shell', 'text' => 'Foundations, drainage, brickwork, steels and a watertight roof.'],
                ['title' => 'Ready to use', 'text' => 'Electrics, heating, plastering, flooring and decoration included.'],
            ],
        );

        $this->service( $services, [
            'name' => 'Loft conversions',
            'title' => 'Loft Conversions in Leeds | Courseline Build & Renovation',
            'path' => 'loft-conversions',
        ], 'Your next bedroom is already upstairs', 'attic', 'roof',
            "## Dormer, hip-to-gable and Velux conversions\n\nMost loft conversions don't need planning permission and leave your garden untouched. We check the head height, design the stairs and plan the conversion around the room you want: a main bedroom with en suite, a children's room or a quiet office.\n\nA Velux conversion starts at about £30,000, a dormer with en suite usually costs £45,000 to £65,000 and a hip-to-gable conversion £55,000 to £75,000, all including VAT.\n\nThe work is done from scaffolding outside, so the house stays liveable until the new staircase goes in.",
            [
                ['title' => 'Survey and design', 'text' => 'Head height check, structural calculations and stair layout.'],
                ['title' => 'Structure and roof', 'text' => 'Floor joists, steels, dormer or roof windows and insulation.'],
                ['title' => 'Finished room', 'text' => 'Staircase, fire safety, en suite, storage and decoration.'],
            ],
        );

        $this->service( $services, [
            'name' => 'Kitchens & bathrooms',
            'title' => 'Kitchen and Bathroom Renovation in Leeds | Courseline',
            'path' => 'kitchens-bathrooms',
        ], 'Rooms that work as hard as you do', 'kitchen', 'bath-new',
            "## Strip-out to final clean\n\nKitchens and bathrooms involve more trades than any other room. We coordinate all of them: removal, plumbing, electrics, plastering, tiling and fitting, in a schedule you get before we start.\n\nA complete bathroom renovation usually costs £10,000 to £18,000 and a kitchen £20,000 to £45,000 including VAT and fitting, plus the units and appliances you choose.\n\nBring your own design or work with our partner showrooms in Leeds. We fit what you choose and stand behind the work for ten years.",
            [
                ['title' => 'Plan and schedule', 'text' => 'Layout, measurements and a day-by-day schedule before work starts.'],
                ['title' => 'All trades included', 'text' => 'Plumbing, electrics, plastering and tiling, coordinated by one site manager.'],
                ['title' => 'Clean handover', 'text' => 'Fitted, tested, cleaned and checked together with you.'],
            ],
        );

        return $this;
    }


    /**
     * Creates the shared Courseline footer and returns its ID.
     *
     * @return string Element ID
     */
    protected function element() : string
    {
        return $this->element ??= $this->saveElement( 'cards', 'Courseline footer', ['columns' => '4', 'cards' => [
            ['title' => 'Courseline', 'text' => "Builders for extensions, loft conversions and renovations in Leeds, West Yorkshire, Harrogate and York since 1998."],
            ['title' => 'Services', 'text' => "- [House extensions](/extensions)\n- [Loft conversions](/loft-conversions)\n- [Kitchens and bathrooms](/kitchens-bathrooms)"],
            ['title' => 'Company', 'text' => "- [Our projects](/projects)\n- [About Courseline](/about)\n- [Legal information](/legal)\n- [Privacy](/privacy)"],
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
     * Creates the Courseline home page and returns it.
     *
     * @return Page Home page
     */
    protected function home() : Page
    {
        $elementId = $this->element();
        $fileId = $this->file();

        $config = [
            'website' => Validation::entry( 'website', ['title' => 'Courseline Build & Renovation'], 'config' ),
        ] + $this->logos( $this->logoFile() ) + [
            'solid::business' => [
                'type' => 'solid::business',
                'files' => [],
                'data' => [
                    'name' => 'Courseline Build & Renovation Ltd',
                    'business-type' => 'GeneralContractor',
                    'street-address' => 'Unit 4, Cross Green Industrial Estate',
                    'postal-code' => 'LS9 0SG',
                    'locality' => 'Leeds',
                    'country' => 'GB',
                    'telephone' => '+44 113 496 0123',
                    'email' => 'info@courseline.example',
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
                'title' => 'Extensions, lofts and renovations in Leeds',
                'subtitle' => 'Leeds builders since 1998',
                'text' => 'Built by our own crews on a fixed quote and a clear schedule, with a ten-year workmanship guarantee and staged payments as the work is finished.',
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
                    ['title' => '4.9/5', 'text' => 'From 212 Google reviews'],
                ],
            ]],
            $this->services(),
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Checked and certified',
                'layout' => 'badges',
                'cards' => [
                    ['title' => 'FMB member', 'text' => 'Vetted and inspected by the Federation of Master Builders'],
                    ['title' => 'TrustMark', 'text' => 'Government endorsed quality scheme'],
                    ['title' => 'Building control', 'text' => 'Completion certificate for every notifiable job'],
                    ['title' => 'Insurance-backed', 'text' => 'Ten-year structural guarantee, even if we stop trading'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'How we work',
                'layout' => 'horizontal',
                'items' => [
                    ['label' => 'Within a week', 'title' => 'Free site visit', 'text' => 'We look at the house, listen to your plans and check what is possible.'],
                    ['label' => '10 working days', 'title' => 'Fixed quote', 'text' => 'An itemised price including VAT and a week-by-week schedule.'],
                    ['label' => '8–13 weeks', 'title' => 'Drawings and approvals', 'text' => 'Plans, planning or permitted development, building control and party wall notices.'],
                    ['label' => '3–14 weeks', 'title' => 'Build and handover', 'text' => 'One site manager, our own crews, a photo update every evening and a snagging walk-through at the end.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'blog', 'group' => 'main', 'data' => [
                'title' => 'Recent projects',
                'layout' => 'cards',
                'parent-page' => ['value' => $this->projectsId, 'label' => 'Projects'],
                'order' => '_lft',
                'limit' => 3,
            ]],
            $this->prices(),
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'Trusted by homeowners',
                'items' => [
                    ['name' => 'Sarah and James P.', 'role' => 'Rear extension, Roundhay', 'text' => 'The quote was the bill. No surprises, and the site was swept every evening while we were still living in the house.'],
                    ['name' => 'Priya N.', 'role' => 'Loft conversion, Headingley', 'text' => 'Moving the stairs instead of adding a second dormer saved us £6,000. The new room feels like it was always there.'],
                    ['name' => 'Mark D.', 'role' => 'Bathroom, Chapel Allerton', 'text' => 'Three weeks, exactly as planned, with a photo update at the end of every day.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Planning a project?',
                'text' => 'Book a free site visit in Leeds, Harrogate, Wetherby, York or anywhere in West Yorkshire. You get honest advice and a fixed quote, with no obligation.',
                'buttons' => [
                    ['label' => 'Get a free quote', 'url' => '/contact'],
                    ['label' => 'Call 0113 496 0123', 'url' => 'tel:+441134960123'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        $meta = [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => 'Courseline builds house extensions, loft conversions, kitchens and bathrooms in Leeds, West Yorkshire, Harrogate and York with fixed quotes and a ten-year guarantee.',
                'keywords' => 'builder Leeds, house extension, loft conversion, kitchen renovation, bathroom renovation, general contractor, West Yorkshire',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => 'Courseline Build & Renovation | Leeds Builders',
                'description' => 'Extensions, loft conversions and renovations by our own crews, with a fixed quote and a ten-year guarantee.',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        return $this->saveRoot( 'Courseline Build & Renovation | Builders in Leeds', $config, $meta, $content, $elementId, $fileId );
    }


    /**
     * Creates the Courseline SVG logo and returns its file ID.
     *
     * @return string File ID
     */
    protected function logoFile() : string
    {
        if( !isset( $this->logoFile ) )
        {
            $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 80" role="img" aria-labelledby="title desc">
  <title id="title">Courseline logo</title>
  <desc id="desc">Yellow square with a roof outline beside the Courseline wordmark</desc>
  <rect x="4" y="8" width="64" height="64" fill="#F2B705"/>
  <path d="M16 46 36 26l20 20" fill="none" stroke="#1F2328" stroke-width="7" stroke-linecap="square"/>
  <path d="M24 52h24v10H24z" fill="#1F2328"/>
  <text x="84" y="58" fill="#FFFFFF" font-family="Arial Narrow, Roboto Condensed, Arial, sans-serif" font-size="46" font-weight="800" letter-spacing="2">COURSELINE</text>
</svg>
SVG;

            $this->logoFile = $this->svgFile(
                $svg,
                'courseline-logo.svg',
                'Courseline logo',
                'Yellow square with a roof outline beside the Courseline wordmark',
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

        return $this->savePage( $data, $content, $parent, $elementId, $fileId, $footer, 'Courseline, builder Leeds, extension, loft conversion, renovation, general contractor' );
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
            ->addLegal( $home )
            ->addPrivacy( $home );
    }


    /**
     * Returns the price guide element.
     *
     * @return array<string, mixed> Pricing element
     */
    protected function prices() : array
    {
        return ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
            'title' => 'What projects cost',
            'text' => 'Typical prices in Leeds including VAT, drawings and approvals. Your site visit ends with a fixed, itemised quote.',
            'items' => [
                [
                    'name' => 'Single storey extension',
                    'prices' => [['id' => 'extension', 'amount' => 55000, 'label' => '£55k–110k']],
                    'text' => '20 to 40 m², about £2,000 to £3,000 per m².',
                    'features' => "- Foundations to decoration\n- Steels and roof lanterns\n- 10 to 14 weeks on site",
                    'url' => '/extensions',
                    'button' => 'House extensions',
                ],
                [
                    'name' => 'Dormer loft conversion',
                    'prices' => [['id' => 'loft', 'amount' => 45000, 'label' => '£45k–65k']],
                    'text' => 'Bedroom with en suite and a new staircase.',
                    'features' => "- No planning in most cases\n- Built from scaffolding\n- 8 to 10 weeks on site",
                    'url' => '/loft-conversions',
                    'button' => 'Loft conversions',
                    'highlight' => true,
                    'badge' => 'Most requested',
                ],
                [
                    'name' => 'Bathroom renovation',
                    'prices' => [['id' => 'bathroom', 'amount' => 10000, 'label' => '£10k–18k']],
                    'text' => 'Strip-out, all trades and fitting, plus your suite.',
                    'features' => "- Tanking and tiling\n- Underfloor heating\n- About 3 weeks",
                    'url' => '/kitchens-bathrooms',
                    'button' => 'Kitchens and bathrooms',
                ],
            ],
        ]];
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
            'title' => 'Our Projects | Courseline Build & Renovation',
            'path' => 'projects',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Work we are proud of',
                'subtitle' => 'Our projects',
                'text' => 'Extensions, lofts, kitchens and bathrooms across Leeds, West Yorkshire, Harrogate and York, with the numbers behind each job. All prices include VAT.',
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
                'text' => 'Book a free site visit and receive an itemised quote including VAT within ten working days.',
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
