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
 * Volt theme demo for the fictional Kestrelwire Electrical company.
 */
class VoltDemo extends AbstractDemo
{
    /** @var array<string, string> Meta descriptions keyed by page path */
    protected const DESCRIPTIONS = [
        'about' => 'Meet Kestrelwire Electrical: NICEIC approved electricians in Bristol with their own vans, fixed prices and a 24/7 emergency line.',
        'bath-consumer-unit' => 'An old fuse box in a Bath townhouse replaced with a modern consumer unit, surge protection and RCBOs in a single day.',
        'clifton-rewire' => 'A full rewire of a Victorian house in Clifton with new circuits, LED lighting and smoke alarms, finished in twelve working days.',
        'consumer-units' => 'Consumer unit and fuse box upgrades in Bristol and Bath with RCBO protection, surge protection and an electrical installation certificate.',
        'contact' => 'Ask Kestrelwire for a fixed price on EV chargers, solar, rewiring, consumer units or EICR inspections in Bristol and Bath.',
        'eicr-inspections' => 'Electrical installation condition reports for landlords, buyers and homeowners in Bristol and Bath, with a clear report within 48 hours.',
        'emergency-call-outs' => 'Emergency electricians in Bristol and Bath, available 24 hours a day for power cuts, tripping circuits and burning smells.',
        'ev-chargers' => 'Home EV charger installation in Bristol and Bath by OZEV authorised electricians, including the load check and smart charging setup.',
        'jobs' => 'Rewires, solar systems, EV chargers and consumer unit upgrades Kestrelwire has completed across Bristol and Bath.',
        'legal' => 'Company details of Kestrelwire Electrical Ltd, Bristol.',
        'keynsham-solar' => 'Solar panels, a home battery and an EV charger for a family house in Keynsham, installed in four days.',
        'privacy' => 'Privacy policy of Kestrelwire Electrical Ltd, Bristol.',
        'rewiring' => 'Full and partial rewiring of houses and flats in Bristol and Bath with minimal mess and work guaranteed for six years.',
        'services' => 'EV chargers, solar and battery storage, consumer units, rewiring, EICR inspections and emergency call-outs from one Bristol electrician.',
        'solar-battery' => 'Solar panels and battery storage for homes in Bristol and Bath, sized from your real energy use and MCS certified.',
    ];

    /**
     * Curated Unsplash photos used by the electrician demo.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    protected const PHOTOS = [
        'board' => ['photo-1544724569-5f546fd6f2b5', 'Old distribution board', 'Crowded distribution board with rows of circuit breakers and coloured wiring'],
        'electrician' => ['photo-1621905251189-08b45d6a269e', 'Electrician at work', 'Electrician in a hard hat and gloves wiring a distribution board'],
        'ev' => ['photo-1593941707882-a5bba14938c7', 'EV charging plug', 'Charging cable plugged into the socket of an electric car'],
        'ev-garage' => ['photo-1617886322168-72b886573c35', 'Cars charging', 'Electric cars connected to wall chargers in a car park'],
        'kitchen' => ['photo-1556911220-bff31c812dba', 'New kitchen', 'Bright fitted kitchen with white units, a marble worktop and fresh produce'],
        'lighting' => ['photo-1524484485831-a92ffc0de03f', 'New lighting', 'Calm room with a white pendant light above a low shelf and a plant'],
        'living' => ['photo-1600607687939-ce8a6c25118c', 'Living area', 'Open-plan living area with a timber wall, sofa and kitchen beyond'],
        'plans' => ['photo-1581092160562-40aa08e78837', 'Circuit planning', 'Electrician drawing circuit plans on a desk next to a toolbox'],
        'portrait' => ['photo-1621905252507-b35492cc74b4', 'Our electrician', 'Smiling electrician in a hard hat and checked shirt next to a fuse box'],
        'roof' => ['photo-1632759145351-1d592919f522', 'Roof survey', 'Tradesman standing on the roof of a brick house next to a ladder'],
        'room' => ['photo-1513694203232-719a280e022f', 'Room before the rewire', 'Plain room with a sofa, a chest of drawers and a single floor lamp'],
        'solar' => ['photo-1509391366360-2e959784a276', 'Solar panels', 'Rows of solar panels on green grass under a blue sky'],
        'solar-roof' => ['photo-1613665813446-82a78c468a1d', 'Solar roof', 'Solar panels on a roof at sunset'],
        'tester' => ['photo-1621905251918-48416bd8575a', 'Testing a circuit', 'Electrician in gloves testing the wiring of an electricity meter'],
        'tools' => ['photo-1530124566582-a618bc2615dc', 'Electrician tools', 'Pliers, cutters and screwdrivers in a tool bag'],
    ];

    private string $element;
    private string $jobsId;
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
            'title' => 'About Kestrelwire | Bristol Electricians Since 2009',
            'path' => 'about',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Electricians who turn up',
                'subtitle' => 'About Kestrelwire',
                'text' => 'Twelve qualified electricians, eight vans and one rule: you know the price and the arrival time before we start.',
                'buttons' => [
                    ['label' => 'Get a fixed price', 'url' => '/contact'],
                    ['label' => 'See our jobs', 'url' => '/jobs'],
                ],
                'background' => ['id' => $this->img( 'portrait' ), 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'plans' ), 'type' => 'file'],
                'position' => 'grid-start',
                'ratio' => '1-1',
                'text' => "## Safe work, clear prices\n\nDaniel Okafor started Kestrelwire in 2009 after fifteen years on commercial sites. Today the team wires new kitchens, installs solar roofs and answers the emergency line every night of the year.\n\nEvery electrician is employed by us and qualified to the 18th Edition, and the NICEIC assesses our work every year. You get the electrical certificate by email the same day.",
            ]],
            $this->badges(),
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'What our customers say',
                'items' => $this->reviews(),
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
            'title' => 'Contact Kestrelwire | Fixed Price Electricians in Bristol',
            'path' => 'contact',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => 'quote', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Get a fixed price',
                'description' => 'Tell us what you need and add photos of your fuse box or the room if you can. We reply within four working hours. For emergencies, please call 0117 496 0999.',
                'inputs' => [
                    ['field' => 'name', 'required' => true, 'input' => 'text'],
                    ['field' => 'telephone', 'required' => true, 'input' => 'text'],
                    ['field' => 'email', 'required' => true, 'input' => 'text'],
                    ['field' => 'Job type', 'required' => true, 'input' => 'select', 'options' => "EV charger\nSolar and battery\nConsumer unit\nRewiring\nEICR inspection\nSomething else"],
                    ['field' => 'Postcode', 'required' => true, 'input' => 'text'],
                ],
                'attachments' => 3,
            ]],
            ['id' => Utils::uid(), 'type' => 'map', 'group' => 'main', 'data' => [
                'title' => 'Our workshop',
                'text' => "**Kestrelwire Electrical**\nUnit 7, Feeder Road Trade Park · Bristol BS2 0TQ\n\n**Call**\n0117 496 0457 · Monday to Friday 08:00–17:30, Saturday 09:00–13:00\n\n**Emergencies**\n0117 496 0999 · 24 hours, every day\n\n**Email**\nhello@kestrelwire.example\n\nWe work across Bristol, Bath, Keynsham, Portishead and Weston-super-Mare.",
                'location' => [
                    'latitude' => 51.4498,
                    'longitude' => -2.5685,
                    'zoom' => 15,
                ],
                'button' => 'Open in OpenStreetMap',
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the jobs page and its job pages below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addJobs( Page $home ) : static
    {
        $jobs = $this->jobs( $home );

        $this->job( $jobs, [
            'name' => 'Clifton rewire',
            'title' => 'Full Rewire of a Victorian House in Clifton',
            'path' => 'clifton-rewire',
        ], 'A safe house behind the original plaster',
            "The house still had rubber-insulated cables from the 1960s and only four sockets on the ground floor. We rewired all three floors, added a socket circuit for every room and replaced the lighting with dimmable LED fittings.\n\nThe cables were routed under the floorboards and through the existing ducts wherever possible, so the cornices and most of the plaster stayed untouched. The decorators could start on day thirteen.",
            'lighting', ['room', 'living'],
            [
                ['title' => '12', 'text' => 'Working days from first floorboard to certificate'],
                ['title' => '46', 'text' => 'New sockets and switches'],
                ['title' => '£9.6k', 'text' => 'Fixed price, no extras'],
            ],
            [
                ['label' => 'Day 1', 'title' => 'Setup and isolation', 'text' => 'Circuits mapped, old installation isolated and a temporary supply set up for the family.'],
                ['label' => 'Days 2–7', 'title' => 'First fix', 'text' => 'New cables, back boxes and ceiling roses on all three floors.'],
                ['label' => 'Days 8–10', 'title' => 'Second fix', 'text' => 'Sockets, switches, LED fittings and interlinked smoke alarms.'],
                ['label' => 'Day 11', 'title' => 'Testing', 'text' => 'Full test of every circuit and the new consumer unit.'],
                ['label' => 'Day 12', 'title' => 'Handover', 'text' => 'Certificate, Building Regulations registration and a walk-through with the owners.'],
            ],
            ['plans', 'electrician', 'tester', 'lighting'],
        );

        $this->job( $jobs, [
            'name' => 'Keynsham solar',
            'title' => 'Solar, Battery and EV Charger in Keynsham',
            'path' => 'keynsham-solar',
        ], 'Driving on sunshine',
            "The family wanted to charge their new electric car without doubling the electricity bill. We fitted 14 solar panels on the south roof, a 10 kWh battery in the garage and a smart EV charger that uses surplus solar first.\n\nThe system covered 71% of the household's electricity in its first summer, and the car is charged on cheap night tariff power in winter.",
            'solar-roof', ['roof', 'solar-roof'],
            [
                ['title' => '4', 'text' => 'Days from scaffold to switch-on'],
                ['title' => '6.1 kWp', 'text' => 'Solar system with a 10 kWh battery'],
                ['title' => '71%', 'text' => 'Of the summer electricity from the roof'],
            ],
            [
                ['label' => 'Day 1', 'title' => 'Scaffold and mounting', 'text' => 'Scaffolding, roof hooks and rails on the south facing roof.'],
                ['label' => 'Day 2', 'title' => 'Panels', 'text' => '14 panels mounted, cables routed through the loft to the garage.'],
                ['label' => 'Day 3', 'title' => 'Battery and inverter', 'text' => 'Hybrid inverter, battery and the smart EV charger in the garage.'],
                ['label' => 'Day 4', 'title' => 'Commissioning', 'text' => 'Inverter commissioning, app setup and the MCS certificate. The G99 approval from National Grid had been arranged before the scaffold went up.'],
            ],
            ['roof', 'solar-roof', 'solar', 'ev'],
        );

        $this->job( $jobs, [
            'name' => 'Bath consumer unit',
            'title' => 'Consumer Unit Upgrade in a Bath Townhouse',
            'path' => 'bath-consumer-unit',
        ], 'Goodbye to the old fuse box',
            "The rewireable fuses in the cellar were older than the owners and kept blowing when the oven and the kettle were on together. We replaced them with a metal consumer unit, an RCBO for every circuit and surge protection.\n\nBefore the swap, every circuit was tested, so two hidden faults were found and fixed the same day rather than after a trip in the middle of the night.",
            'tester', ['board', 'electrician'],
            [
                ['title' => '1', 'text' => 'Day with the power off for five hours'],
                ['title' => '12', 'text' => 'Circuits with their own RCBO'],
                ['title' => '£780', 'text' => 'Fixed price, no extras'],
            ],
            [
                ['label' => '08:00', 'title' => 'Testing', 'text' => 'Every circuit tested and labelled before anything is switched off.'],
                ['label' => '10:00', 'title' => 'Swap', 'text' => 'Old fuse box removed, new consumer unit and main bonding fitted.'],
                ['label' => '14:00', 'title' => 'Fault finding', 'text' => 'The two faulty junction boxes found in the morning tests replaced.'],
                ['label' => '15:30', 'title' => 'Certificate', 'text' => 'Power back on, final tests and the certificate sent by email.'],
            ],
            ['board', 'tools', 'electrician', 'kitchen'],
        );

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
            'title' => 'Legal Information | Kestrelwire Electrical',
            'path' => 'legal',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "# Legal information\n\n**Kestrelwire Electrical Ltd**\nUnit 7, Feeder Road Trade Park\nBristol BS2 0TQ\nUnited Kingdom\n\nTelephone: 0117 496 0457\nEmail: hello@kestrelwire.example\n\nRegistered in England and Wales, company number 00000000 (fictional)\nVAT number GB 000 0000 00 (fictional)\nDirector: Daniel Okafor\n\nThis is a demo website for the Volt theme. Kestrelwire is a fictional company.",
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
            'title' => 'Privacy Policy | Kestrelwire Electrical',
            'path' => 'privacy',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "# Privacy policy\n\n## Who is responsible\n\nKestrelwire Electrical Ltd, Unit 7, Feeder Road Trade Park, Bristol BS2 0TQ, hello@kestrelwire.example. We are the data controller under the UK GDPR and the Data Protection Act 2018.\n\n## Quote requests\n\nWhen you send the quote form, we use your name, phone number, email address, postcode, job type and the photos you attach only to price your job, plan the visit and contact you about it (Article 6(1)(b) UK GDPR). Photos of your fuse box or rooms are only seen by our office team and the electrician pricing the job. Requests that don't lead to a job are deleted after six months.\n\n## Customers and certificates\n\nFor jobs we carry out, we keep invoices for six years as required by tax law and electrical certificates for the life of the installation. Notifiable work is registered with Building Control through the NICEIC, which receives your address and the details of the work. We don't sell your data or pass it on for marketing.\n\n## This website\n\nThe website doesn't use tracking or advertising cookies. Our server stores technical access data such as the IP address for seven days to protect against attacks. The map is loaded from OpenStreetMap only after you open it.\n\n## Your rights\n\nYou have the right to access, rectification, erasure, restriction of processing, objection and data portability. Please write to hello@kestrelwire.example. If you are unhappy with our answer, you can complain to the Information Commissioner's Office (ICO) at ico.org.uk or on 0303 123 1113.\n\nThis is a demo website for the Volt theme. Kestrelwire is a fictional company.",
            ]],
        ], $home );

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
            'title' => 'Electrical Services in Bristol | Kestrelwire Electrical',
            'path' => 'services',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Everything electrical in your home',
                'subtitle' => 'Our services',
                'text' => 'From a single faulty socket to a complete solar system. Qualified electricians, fixed prices and certificates for every job.',
                'buttons' => [
                    ['label' => 'Get a fixed price', 'url' => '/contact'],
                ],
            ]],
            $this->services(),
            $this->prices(),
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Common questions',
                'items' => [
                    ['title' => 'Are your prices really fixed?', 'text' => 'Yes. After the survey or a look at your photos you get a written price. It only changes if you ask for more work, and every change is agreed before it is done.'],
                    ['title' => 'Do I get a certificate?', 'text' => 'Always. You get an electrical installation certificate or minor works certificate for every job. Notifiable work such as new circuits and consumer units is registered with Building Control through the NICEIC, and you receive the Building Regulations compliance certificate.'],
                    ['title' => 'How quickly can you come for an emergency?', 'text' => 'Within two hours in Bristol and Bath, day and night. We make the installation safe first and give you a price before any repair.'],
                    ['title' => 'Do I have to move out during a rewire?', 'text' => 'No. We rewire room by room and keep a temporary supply running, so you have light, a fridge and charging sockets every evening.'],
                    ['title' => 'Which electrical work has to be notified?', 'text' => 'New circuits, consumer unit replacements and work in bathrooms are notifiable under Part P of the Building Regulations. As NICEIC approved contractors, we register them with Building Control for you, so you don\'t pay a council fee.'],
                    ['title' => 'Do I need planning permission for solar panels?', 'text' => 'Usually not, because most roof systems are permitted development. In conservation areas such as Clifton and in central Bath, front roofs and listed buildings can need consent. We check this during the survey.'],
                    ['title' => 'Do you use subcontractors?', 'text' => 'No. The electricians who survey and price your job are employed by us and do the work themselves.'],
                    ['title' => 'Can I cancel after signing?', 'text' => 'Yes. If you agree the job at home, you can cancel within 14 days without a reason. If you want us to start earlier, you only pay for the work done until you cancel.'],
                    ['title' => 'Is the work guaranteed?', 'text' => 'Yes. Notifiable work is covered by the NICEIC Platinum Promise for six years, so it is put right even if we could no longer do it ourselves.'],
                ],
            ]],
        ], $home );

        foreach( $this->offers() as $offer ) {
            $this->service( $services, ...$offer );
        }

        return $this;
    }


    /**
     * Returns the certification badges element.
     *
     * @return array<string, mixed> Cards content element
     */
    protected function badges() : array
    {
        return ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
            'title' => 'Approved and certified',
            'layout' => 'badges',
            'cards' => [
                ['title' => 'NICEIC', 'text' => 'Approved contractor, inspected every year'],
                ['title' => 'MCS', 'text' => 'Certified solar and battery installer'],
                ['title' => 'OZEV', 'text' => 'Authorised EV charger installer'],
                ['title' => 'TrustMark', 'text' => 'Government endorsed quality scheme'],
                ['title' => '£5m insurance', 'text' => 'Public liability cover on every job'],
            ],
        ]];
    }


    /**
     * Creates the shared Kestrelwire footer and returns its ID.
     *
     * @return string Element ID
     */
    protected function element() : string
    {
        return $this->element ??= $this->saveElement( 'cards', 'Kestrelwire footer', ['columns' => '4', 'cards' => [
            ['title' => 'Kestrelwire', 'text' => "NICEIC approved electricians for homes and landlords in Bristol and Bath since 2009."],
            ['title' => 'Services', 'text' => "- [EV chargers](/ev-chargers)\n- [Solar and battery](/solar-battery)\n- [Rewiring](/rewiring)\n- [EICR inspections](/eicr-inspections)"],
            ['title' => 'Company', 'text' => "- [Our jobs](/jobs)\n- [About Kestrelwire](/about)\n- [Legal information](/legal)\n- [Privacy](/privacy)"],
            ['title' => 'Contact', 'text' => "Unit 7, Feeder Road Trade Park\nBristol BS2 0TQ\n\n0117 496 0457\nEmergencies 0117 496 0999\n[Get a fixed price](/contact)"],
        ]] );
    }


    /**
     * Returns the ID of the primary electrician image.
     *
     * @return string File ID
     */
    protected function file() : string
    {
        return $this->img( 'electrician' );
    }


    /**
     * Creates the Kestrelwire home page and returns it.
     *
     * @return Page Home page
     */
    protected function home() : Page
    {
        $elementId = $this->element();
        $fileId = $this->file();

        $config = [
            'website' => Validation::entry( 'website', ['title' => 'Kestrelwire Electrical'], 'config' ),
        ] + $this->logos( $this->logoFile() ) + [
            'volt::business' => [
                'type' => 'volt::business',
                'files' => [],
                'data' => [
                    'name' => 'Kestrelwire Electrical Ltd',
                    'business-type' => 'Electrician',
                    'street-address' => 'Unit 7, Feeder Road Trade Park',
                    'postal-code' => 'BS2 0TQ',
                    'locality' => 'Bristol',
                    'country' => 'GB',
                    'telephone' => '+44 117 496 0457',
                    'emergency-phone' => '+44 117 496 0999',
                    'emergency' => true,
                    'email' => 'hello@kestrelwire.example',
                    'area' => 'Bristol, Bath, Keynsham, Portishead, Weston-super-Mare',
                    'price-range' => '££',
                    'call-button' => true,
                    'hours' => [
                        ['id' => 'mon', 'day' => 'Monday', 'opens' => '08:00', 'closes' => '17:30'],
                        ['id' => 'tue', 'day' => 'Tuesday', 'opens' => '08:00', 'closes' => '17:30'],
                        ['id' => 'wed', 'day' => 'Wednesday', 'opens' => '08:00', 'closes' => '17:30'],
                        ['id' => 'thu', 'day' => 'Thursday', 'opens' => '08:00', 'closes' => '17:30'],
                        ['id' => 'fri', 'day' => 'Friday', 'opens' => '08:00', 'closes' => '17:30'],
                        ['id' => 'sat', 'day' => 'Saturday', 'opens' => '09:00', 'closes' => '13:00'],
                    ],
                ],
            ],
        ];

        $content = [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Power you can rely on',
                'subtitle' => 'NICEIC approved electricians in Bristol',
                'text' => 'EV chargers, solar, rewires and fuse box upgrades at a fixed price, plus a 24/7 emergency line when the lights go out.',
                'buttons' => [
                    ['label' => 'Get a fixed price', 'url' => '/contact'],
                    ['label' => 'Our services', 'url' => '/services'],
                ],
                'background' => ['id' => $fileId, 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'layout' => 'figures',
                'cards' => [
                    ['title' => '17', 'text' => 'Years wiring Bristol homes'],
                    ['title' => '4,800', 'text' => 'Certified jobs'],
                    ['title' => '2 h', 'text' => 'Emergency response time'],
                    ['title' => '4.9/5', 'text' => 'On Google from 386 reviews'],
                ],
            ]],
            $this->services(),
            $this->badges(),
            $this->prices(),
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'How a job runs',
                'layout' => 'horizontal',
                'items' => [
                    ['label' => 'Step 1', 'title' => 'Tell us', 'text' => 'Send a few photos or book a free survey for bigger jobs.'],
                    ['label' => 'Step 2', 'title' => 'Fixed price', 'text' => 'A written price and a start date, usually within two days.'],
                    ['label' => 'Step 3', 'title' => 'Safe work', 'text' => 'Dust sheets, a tidy site and an update before we leave each day.'],
                    ['label' => 'Step 4', 'title' => 'Certificate', 'text' => 'Every circuit tested and the certificate in your inbox.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'blog', 'group' => 'main', 'data' => [
                'title' => 'Recent jobs',
                'layout' => 'cards',
                'parent-page' => ['value' => $this->jobsId, 'label' => 'Jobs'],
                'order' => '_lft',
                'limit' => 3,
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'Trusted across Bristol',
                'items' => $this->reviews(),
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Power cut or burning smell?',
                'text' => 'Switch off at the main switch and call our emergency line. An electrician is with you within two hours, day or night. If your neighbours have lost power too, call the free power cut line 105.',
                'buttons' => [
                    ['label' => 'Call 0117 496 0999', 'url' => 'tel:+441174960999'],
                    ['label' => 'Emergency call-outs', 'url' => '/emergency-call-outs'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        $meta = [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => 'Kestrelwire installs EV chargers, solar and batteries, rewires homes and upgrades fuse boxes in Bristol and Bath, with a 24/7 emergency line.',
                'keywords' => 'electrician Bristol, EV charger installation, solar panels, consumer unit upgrade, rewiring, EICR, emergency electrician, Bath',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => 'Kestrelwire Electrical | Electricians in Bristol',
                'description' => 'NICEIC approved electricians with fixed prices and a 24/7 emergency line.',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        return $this->saveRoot( 'Kestrelwire Electrical | Electricians in Bristol and Bath', $config, $meta, $content, $elementId, $fileId );
    }


    /**
     * Creates a job page below the jobs page.
     *
     * @param Page $parent Jobs page
     * @param array<string, string> $data Page name, title and path
     * @param string $title Article headline
     * @param string $text Article text
     * @param string $cover PHOTOS key of the cover image
     * @param array{0: string, 1: string} $compare PHOTOS keys of the before and after images
     * @param array<int, array<string, string>> $facts Key facts as figure cards
     * @param array<int, array<string, string>> $steps Job steps
     * @param array<int, string> $photos PHOTOS keys of the slideshow images
     * @return Page Created page
     */
    protected function job( Page $parent, array $data, string $title, string $text, string $cover,
        array $compare, array $facts, array $steps, array $photos ) : Page
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
                'title' => 'Step by step',
                'layout' => 'vertical',
                'items' => $steps,
            ]],
            ['id' => Utils::uid(), 'type' => 'slideshow', 'group' => 'main', 'data' => [
                'title' => 'On the job',
                'files' => array_map( fn( $key ) => ['id' => $this->cropped( $key, 1500, 1000 ), 'type' => 'file'], $photos ),
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Need something similar?',
                'text' => 'Send us a few photos and you get a fixed price, usually within two working days.',
                'buttons' => [
                    ['label' => 'Get a fixed price', 'url' => '/contact'],
                ],
            ]],
        ], $parent );
    }


    /**
     * Creates the jobs overview page and returns it.
     *
     * @param Page $home Home page
     * @return Page Jobs page
     */
    protected function jobs( Page $home ) : Page
    {
        return $this->page( [
            'id' => $this->jobsId,
            'lang' => 'en',
            'name' => 'Jobs',
            'title' => 'Our Jobs | Kestrelwire Electrical',
            'path' => 'jobs',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Recent jobs',
                'subtitle' => 'Our work',
                'text' => 'Rewires, solar roofs, EV chargers and fuse box upgrades across Bristol and Bath, with the numbers behind each job.',
            ]],
            ['id' => 'job-list', 'type' => 'blog', 'group' => 'main', 'data' => [
                'layout' => 'cards',
                'parent-page' => ['value' => $this->jobsId, 'label' => 'Jobs'],
                'order' => '_lft',
                'limit' => 12,
            ]],
        ], $home );
    }


    /**
     * Creates the Kestrelwire SVG logo and returns its file ID.
     *
     * @return string File ID
     */
    protected function logoFile() : string
    {
        if( !isset( $this->logoFile ) )
        {
            $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 80" role="img" aria-labelledby="title desc">
  <title id="title">Kestrelwire logo</title>
  <desc id="desc">Blue rounded square with an amber lightning bolt beside the Kestrelwire wordmark</desc>
  <rect x="4" y="8" width="64" height="64" rx="12" fill="#1F6FEB"/>
  <path d="M41 16 22 44h13l-5 20 20-29H37z" fill="#FFB020"/>
  <text x="84" y="55" fill="#FFFFFF" font-family="system-ui, Segoe UI, Roboto, Arial, sans-serif" font-size="40" font-weight="800" letter-spacing="-1">Kestrelwire</text>
</svg>
SVG;

            $this->logoFile = $this->svgFile(
                $svg,
                'kestrelwire-logo.svg',
                'Kestrelwire logo',
                'Blue rounded square with an amber lightning bolt beside the Kestrelwire wordmark',
                true,
            );
        }

        return $this->logoFile;
    }


    /**
     * Returns the arguments for the service pages.
     *
     * @return array<int, array<int, mixed>> Page data, headline, hero and image keys, text and included items
     */
    protected function offers() : array
    {
        return [
            [['name' => 'EV chargers', 'title' => 'EV Charger Installation in Bristol | Kestrelwire', 'path' => 'ev-chargers'],
                'Charge at home while you sleep', 'ev-garage', 'ev',
                "## Smart chargers, fitted properly\n\nA home charger fills a typical electric car overnight for a fraction of public charging prices. We check your supply and fuse first, choose the right spot on the wall or drive and set up the app so the car charges on the cheapest tariff hours.\n\nMost installations take half a day and include the earthing, the dedicated circuit and the notification to your network operator.\n\nOwn a flat or rent with private parking? We apply for the government grant of up to £500 for you, available until 31 March 2027.",
                [
                    ['title' => 'Load check', 'text' => 'Supply, main fuse and earthing checked before we quote.'],
                    ['title' => 'Tidy installation', 'text' => 'Dedicated circuit, cable in conduit and the charger where you want it.'],
                    ['title' => 'Smart setup', 'text' => 'App, tariff schedule and solar surplus charging configured with you.'],
                ]],
            [['name' => 'Solar & battery', 'title' => 'Solar Panels and Battery Storage in Bristol | Kestrelwire', 'path' => 'solar-battery'],
                'Make your own electricity', 'solar-roof', 'solar',
                "## Sized from your real usage\n\nWe size every system from your last year of meter readings, not from a brochure. Panels cover your daytime use, the battery keeps the evening running on sunshine, and the inverter is ready for an EV charger or heat pump later.\n\nAll systems are MCS certified, so you can sell surplus power through the Smart Export Guarantee. Solar panels and batteries are VAT-free until 31 March 2027.",
                [
                    ['title' => 'Honest sizing', 'text' => 'Roof survey, shading check and a yield forecast from your usage.'],
                    ['title' => 'Two-day fitting', 'text' => 'Scaffolding, panels, inverter and battery in most homes within two days.'],
                    ['title' => 'Certified', 'text' => 'MCS certificate and the G98/G99 grid application for export payments.'],
                ]],
            [['name' => 'Consumer units', 'title' => 'Consumer Unit and Fuse Box Upgrades in Bristol | Kestrelwire', 'path' => 'consumer-units'],
                'Replace the old fuse box', 'board', 'electrician',
                "## Modern protection for every circuit\n\nOld fuse boxes don't protect you against electric shocks or fire caused by faulty appliances. A new consumer unit with RCBOs switches off only the faulty circuit in milliseconds, and surge protection guards your electronics.\n\nWe test every circuit before the swap, so faults are found while we are still there.",
                [
                    ['title' => 'Test first', 'text' => 'All circuits tested and labelled before the power goes off.'],
                    ['title' => 'One day', 'text' => 'Power off for four to six hours, back on before the evening.'],
                    ['title' => 'Certificate', 'text' => 'Installation certificate and Building Regulations notification.'],
                ]],
            [['name' => 'Rewiring', 'title' => 'House Rewiring in Bristol and Bath | Kestrelwire', 'path' => 'rewiring'],
                'New wiring, original character', 'lighting', 'plans',
                "## Full and partial rewires\n\nIf your house still has rubber or lead cables, too few sockets or a mix of old and new circuits, a rewire is the safe fix. We plan the cable routes with you so that cornices, floors and freshly decorated rooms stay untouched wherever possible.\n\nYou stay in your home during the work and have a temporary supply every evening.",
                [
                    ['title' => 'Room by room', 'text' => 'Planned in stages, with power and light in the evenings.'],
                    ['title' => 'Minimal mess', 'text' => 'Cables under floors and through ducts, dust sheets everywhere.'],
                    ['title' => 'Ready for later', 'text' => 'Spare capacity for an EV charger, heat pump or solar system.'],
                    ['title' => 'Six-year guarantee', 'text' => 'Work guaranteed by the NICEIC Platinum Promise for six years.'],
                ]],
            [['name' => 'EICR inspections', 'title' => 'EICR Inspections for Landlords and Homeowners | Kestrelwire', 'path' => 'eicr-inspections'],
                'Know your installation is safe', 'tester', 'tools',
                "## Electrical installation condition reports\n\nLandlords need an EICR every five years, buyers want one before they move in and homeowners should check every ten years. We test every circuit, inspect the visible wiring and explain the results in plain words.\n\nThe five-year rule now covers social housing as well: new tenancies since 1 November 2025 and existing ones since 1 May 2026. Landlords must give tenants a copy of the report within 28 days and finish any required repairs within 28 days.\n\nYou receive the report within 48 hours, with a fixed price for any work that is needed.",
                [
                    ['title' => 'Full testing', 'text' => 'Every circuit, RCD and earth connection tested and recorded.'],
                    ['title' => 'Clear report', 'text' => 'Results explained in plain words, delivered within 48 hours.'],
                    ['title' => 'Fixed repairs', 'text' => 'A written price for any remedial work, done on the same visit if you like.'],
                ]],
            [['name' => 'Emergency call-outs', 'title' => '24/7 Emergency Electrician in Bristol | Kestrelwire', 'path' => 'emergency-call-outs'],
                'Lights out? We are on our way', 'portrait', 'tools',
                "## Day and night, every day of the year\n\nPower cuts limited to your home, circuits that keep tripping, sparking sockets or a burning smell can't wait until Monday. Call our emergency line and an electrician is with you within two hours in Bristol and Bath.\n\nWe make the installation safe first and tell you the price of the repair before we start.\n\nIf your neighbours have lost power too, the fault is in the street. Call the free power cut line 105 instead.",
                [
                    ['title' => 'Two-hour response', 'text' => 'An electrician on the way within minutes of your call.'],
                    ['title' => 'Made safe', 'text' => 'Faults isolated so the rest of the house has power again.'],
                    ['title' => 'Clear call-out fee', 'text' => '£150 call-out including the first hour, repairs priced before we start.'],
                ]],
        ];
    }


    /**
     * Creates a Volt demo page below the given parent and returns it.
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

        return $this->savePage( $data, $content, $parent, $elementId, $fileId, $footer, 'Kestrelwire, electrician Bristol, EV charger, solar, rewiring, consumer unit, EICR' );
    }


    /**
     * Builds the Volt electrician demo page tree.
     */
    protected function pages() : void
    {
        $this->jobsId = (string) Str::uuid7();
        $home = $this->home();

        $this->addServices( $home )
            ->addJobs( $home )
            ->addAbout( $home )
            ->addContact( $home )
            ->addLegal( $home )
            ->addPrivacy( $home );
    }


    /**
     * Returns the fixed price list element.
     *
     * @return array<string, mixed> Pricing content element
     */
    protected function prices() : array
    {
        return ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
            'title' => 'Fixed prices',
            'text' => 'Prices for a typical home including VAT, materials and the certificate. You get a written price before we start.',
            'items' => [
                [
                    'name' => 'EICR inspection',
                    'prices' => [['id' => 'eicr', 'amount' => 180, 'label' => '£180']],
                    'text' => 'Full condition report for homes with up to three bedrooms.',
                    'features' => "- Every circuit tested\n- Report within 48 hours\n- Fixed price for any repairs",
                    'url' => '/eicr-inspections',
                    'button' => 'EICR inspections',
                ],
                [
                    'name' => 'EV charger',
                    'prices' => [['id' => 'ev', 'amount' => 995, 'label' => '£995']],
                    'text' => '7 kW smart charger on the wall or drive, ready the same day.',
                    'features' => "- Load and earthing check\n- Up to 10 m of cable\n- App and tariff setup",
                    'url' => '/ev-chargers',
                    'button' => 'EV chargers',
                    'highlight' => true,
                    'badge' => 'Most booked',
                ],
                [
                    'name' => 'Consumer unit',
                    'prices' => [['id' => 'cu', 'amount' => 650, 'label' => '£650']],
                    'text' => 'Metal unit with RCBO protection for up to ten circuits.',
                    'features' => "- All circuits tested first\n- Surge protection included\n- Done in one day",
                    'url' => '/consumer-units',
                    'button' => 'Consumer units',
                ],
            ],
        ]];
    }


    /**
     * Returns the customer reviews.
     *
     * @return array<int, array<string, string>> Testimonial items
     */
    protected function reviews() : array
    {
        return [
            ['name' => 'Helen and Rob T.', 'role' => 'Full rewire, Clifton', 'text' => 'Twelve working days, exactly as promised, and our cornices are still in one piece. Every evening the house had light and the kitchen worked.'],
            ['name' => 'Amir S.', 'role' => 'Solar, battery and EV charger, Keynsham', 'text' => 'They talked us out of a second battery we didn\'t need. The car now runs on the roof for most of the summer.'],
            ['name' => 'Claire W.', 'role' => 'Emergency call-out, Bath', 'text' => 'The power went at eleven at night with a baby in the house. The electrician was here in under an hour and found the fault in ten minutes.'],
        ];
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
                    ['label' => 'Get a fixed price', 'url' => '/contact'],
                    ['label' => 'See our jobs', 'url' => '/jobs'],
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
                'text' => 'Send us a few photos and you get a written price, usually within two working days.',
                'buttons' => [
                    ['label' => 'Get a fixed price', 'url' => '/contact'],
                    ['label' => 'Call 0117 496 0457', 'url' => 'tel:+441174960457'],
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
            'title' => 'What we do',
            'columns' => '3',
            'cards' => [
                ['title' => 'EV chargers', 'text' => 'Smart home chargers fitted in half a day by OZEV authorised electricians.', 'url' => '/ev-chargers', 'file' => ['id' => $this->img( 'ev' ), 'type' => 'file']],
                ['title' => 'Solar & battery', 'text' => 'Panels and storage sized from your real energy use, MCS certified.', 'url' => '/solar-battery', 'file' => ['id' => $this->img( 'solar-roof' ), 'type' => 'file']],
                ['title' => 'Consumer units', 'text' => 'Old fuse boxes replaced with RCBO protection in a single day.', 'url' => '/consumer-units', 'file' => ['id' => $this->img( 'board' ), 'type' => 'file']],
                ['title' => 'Rewiring', 'text' => 'Full and partial rewires that keep your plaster and cornices intact.', 'url' => '/rewiring', 'file' => ['id' => $this->img( 'lighting' ), 'type' => 'file']],
                ['title' => 'EICR inspections', 'text' => 'Condition reports for landlords, buyers and homeowners within 48 hours.', 'url' => '/eicr-inspections', 'file' => ['id' => $this->img( 'tester' ), 'type' => 'file']],
                ['title' => 'Emergency call-outs', 'text' => 'An electrician with you within two hours, day and night.', 'url' => '/emergency-call-outs', 'file' => ['id' => $this->img( 'portrait' ), 'type' => 'file']],
            ],
        ]];
    }
}
