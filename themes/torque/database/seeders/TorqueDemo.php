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
 * Torque theme demo for the fictional Kraft Autoservice garage in Stuttgart.
 */
class TorqueDemo extends AbstractDemo
{
    /** @var array<string, string> Meta descriptions keyed by page path */
    protected const DESCRIPTIONS = [
        'about' => 'Meet the team of Kraft Autoservice in Stuttgart: master mechanics, a high-voltage technician and the service desk, family-run since 1998.',
        'book' => 'Book your service, repair, tyre change or inspection at Kraft Autoservice in Stuttgart online. We confirm within two hours.',
        'brakes-tyres' => 'Brake pads, discs and brake fluid, tyre fitting, balancing, alignment and tyre storage in Stuttgart at fixed prices.',
        'careers' => 'Mechanic, high-voltage technician and apprentice jobs in Stuttgart: work at Kraft Autoservice with modern equipment and paid training.',
        'diagnostics' => 'Engine warning light on? Computer diagnostics, electrical fault finding and air conditioning service in Stuttgart.',
        'electric-hybrid' => 'Servicing and repairs for electric and hybrid cars in Stuttgart by certified high-voltage technicians, including battery health checks.',
        'imprint' => 'Legal notice of Kraft Autoservice GmbH, Stuttgart.',
        'inspection-checklist' => 'Pass your main inspection (HU) the first time: the ten checks you can do yourself and the faults inspectors find most often.',
        'offers' => 'Current service offers at Kraft Autoservice in Stuttgart: tyre change, air conditioning service, brake check and pre-inspection.',
        'prices' => 'Fixed prices for servicing, brakes, tyres, diagnostics and inspections at Kraft Autoservice in Stuttgart, including parts and labour.',
        'privacy' => 'Privacy policy of Kraft Autoservice GmbH, Stuttgart.',
        'servicing' => 'Car servicing to manufacturer specification in Stuttgart: oil service, inspection service and main inspection without losing your warranty.',
        'services' => 'Servicing, brakes and tyres, diagnostics, air conditioning and electric car repairs for all makes at Kraft Autoservice in Stuttgart.',
        'tips' => 'Car care tips from the Kraft Autoservice workshop: tyres, warning lights, inspections and keeping your car on the road for longer.',
        'used-cars' => 'Checked used cars from Kraft Autoservice in Stuttgart with a 110-point inspection, 12 month warranty, financing and trade-in.',
        'visit' => 'Visit Kraft Autoservice on Heilbronner Straße in Stuttgart-Feuerbach: opening hours, directions, key drop box and courtesy cars.',
        'warning-lights' => 'Dashboard warning lights explained: which ones mean stop now, which ones can wait a few days and what the repairs usually cost.',
        'winter-tyres' => 'When to change to winter tyres, how much tread you really need and why storing your tyres at the garage pays off.',
    ];

    /**
     * Curated Unsplash photos used by the garage demo.
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3?: string}>
     */
    protected const PHOTOS = [
        'alloy' => ['photo-1760836395760-9831facb4754', 'Tyre and alloy wheel', 'New tyre mounted on a multi-spoke alloy wheel'],
        'audi-wagon' => ['photo-1777284748695-437c22a7ee50', 'Silver estate car', 'Silver Audi estate car parked in daylight'],
        'brake-disc' => ['photo-1696494561430-de087dd0bd69', 'Brake disc', 'Brake disc and caliper on a wheel hub with the wheel removed'],
        'brake-drilled' => ['photo-1760317890322-364a810cd4da', 'Drilled brake disc', 'Close-up of a cross-drilled brake disc with a red caliper'],
        'cluster' => ['photo-1611099711902-1228419f7113', 'Instrument cluster', 'Illuminated car instrument cluster with speedometer and rev counter'],
        'dealer-row' => ['photo-1574023240744-64c47c8c0676', 'Used car lot', 'Row of polished cars lined up at a dealership'],
        'diagnostics' => ['photo-1727893380169-4dda123e19f7', 'Computer diagnostics', 'Mechanic reading fault codes on a diagnostic laptop next to a car'],
        'engine-bay' => ['photo-1764122623556-90a7d480df53', 'Engine bay', 'Clean engine bay of a modern car with the bonnet open'],
        'ev-plug' => ['photo-1704475336842-0ab3798abf0e', 'Charging an electric car', 'Hand plugging a charging cable into an electric car'],
        'ev-port' => ['photo-1593941707874-ef25b8b4a92b', 'Charging port', 'Charging cable connected to the port of an electric car'],
        'golf' => ['photo-1471444928139-48c5bf5173f8', 'Black compact car', 'Black Volkswagen Golf parked near the sea'],
        'golf-white' => ['photo-1714225317039-d685f0e1cd38', 'White compact car', 'White Volkswagen Golf in a bright studio'],
        'ipace' => ['photo-1788874620958-3a96ce22f5b4', 'White electric SUV', 'White Jaguar I-PACE electric SUV'],
        'key-audi' => ['photo-1671719367451-7bf05ae9549c', 'Car key', 'Hand holding a car key in front of a silver Audi'],
        'key-handover' => ['photo-1761014586544-53fe5e1f1e25', 'Key handover', 'Car key handed from one person to another'],
        'lift-mercedes' => ['photo-1791419542289-693087bd1044', 'Car on a lift', 'Silver Mercedes raised on a two-post lift in a workshop'],
        'lift-red' => ['photo-1786198984387-b63cdf520602', 'Red car on a lift', 'Red car on a lift with the bonnet open in a bright workshop'],
        'lift-underside' => ['photo-1723099971299-3789db53604c', 'Underbody check', 'Underside of a car raised on a lift with exhaust and suspension visible'],
        'mechanic' => ['photo-1625047509248-ec889cbff17f', 'Mechanic at work', 'Mechanic leaning into the engine bay of a car with a wrench'],
        'oil' => ['photo-1487754180451-c456f719a1fc', 'Oil service', 'Fresh engine oil being poured into an engine'],
        'repair-hall' => ['photo-1767681092416-bccf9410bda4', 'Repair hall', 'Several cars on lifts in a large repair hall'],
        'speedo' => ['photo-1498887960847-2a5e46312788', 'Speedometer', 'Close-up of a glowing speedometer needle'],
        'tiguan' => ['photo-1758228674135-ccb8865f1b02', 'Black SUV', 'Black Volkswagen Tiguan SUV parked outside'],
        'tool-wall' => ['photo-1671040690726-b78261eff126', 'Tool wall', 'Neatly arranged spanners and tools on a workshop wall'],
        'tyre-fitting' => ['photo-1645445522156-9ac06bc7a767', 'Tyre fitting', 'Mechanic in gloves fitting a wheel to a car'],
        'tyres' => ['photo-1578844251758-2f71da64c96f', 'Tyre storage', 'Stacks of tyres in a tyre storage rack'],
        'under-car' => ['photo-1702146713858-8e7d1cc29fe8', 'Under the car', 'Mechanic working under a raised car in a dim workshop'],
        'volvo' => ['photo-1766918070462-20206f5610f6', 'Silver estate car', 'Silver Volvo V50 estate car parked on a street'],
        'workshop' => ['photo-1727893119356-1702fe921cf9', 'Workshop', 'Bright, clean workshop with a car on a lift'],
        'wrenches' => ['photo-1637640125496-31852f042a60', 'Spanners', 'Set of chrome spanners in a row'],
        'team-1' => ['photo-1586447751596-6c2050a0d335', 'Markus Kraft', 'Portrait of a smiling man in a grey work shirt', 'faces'],
        'team-2' => ['photo-1687462970787-61d953508926', 'Sabine Kraft', 'Portrait of a smiling woman in a workshop', 'faces'],
        'team-3' => ['photo-1732395805034-e0bf859665e5', 'Deniz Yilmaz', 'Portrait of a smiling man in a blue uniform', 'faces'],
        'team-4' => ['photo-1778077128777-ccb864dc3ec3', 'Werner Haas', 'Portrait of an older man with glasses, smiling', 'faces'],
        'review-1' => ['photo-1504222490345-c075b6008014', 'Thomas', 'Man in a cap smiling', 'faces'],
        'review-2' => ['photo-1628577478162-d4d00467c627', 'Elena', 'Person smiling in the driver\'s seat of a car', 'faces'],
        'review-3' => ['photo-1640706277402-666588349146', 'Ralf', 'Man leaning on the bonnet of his car', 'faces'],
    ];

    private string $element;
    private string $tipsId;
    /** @var array<string, string> */
    private array $icons = [];
    private string $logoFile;


    /**
     * Creates the about page and the careers page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addAbout( Page $home ) : static
    {
        $about = $this->page( [
            'lang' => 'en',
            'name' => 'About us',
            'title' => 'About Us: Family-Run Garage Since 1998 | Kraft Autoservice Stuttgart',
            'path' => 'about',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Mechanics who *explain*',
                'subtitle' => 'About us',
                'text' => 'Kraft Autoservice is a family business in the second generation. Twelve people, six lifts and one rule: you see the old part before we charge you for a new one.',
                'buttons' => [
                    ['label' => 'Book service', 'url' => '/book'],
                    ['label' => 'Open positions', 'url' => '/careers'],
                ],
                'files' => [['id' => $this->frame( 'workshop' ), 'type' => 'file']],
            ]],
            $this->team(),
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'Our story',
                'layout' => 'vertical',
                'items' => [
                    ['label' => '1998', 'title' => 'Two lifts in Feuerbach', 'text' => 'Werner Haas and Markus Kraft open a small workshop behind a petrol station.'],
                    ['label' => '2006', 'title' => 'Heilbronner Straße', 'text' => 'Six lifts, a tyre hotel for 1,200 sets and our own paint-free dent repair.'],
                    ['label' => '2015', 'title' => 'Used cars', 'text' => 'We start selling checked trade-ins with our own 12 month warranty.'],
                    ['label' => '2021', 'title' => 'High voltage', 'text' => 'Two technicians certified for electric and hybrid cars, plus a battery test bench.'],
                    ['label' => '2025', 'title' => 'Second generation', 'text' => 'Sabine Kraft takes over the management, Markus stays in the workshop.'],
                ],
            ]],
            $this->checks(),
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Want to *join the crew*?',
                'text' => 'We are looking for mechanics and apprentices who like to do things properly and talk to customers.',
                'buttons' => [
                    ['label' => 'Open positions', 'url' => '/careers'],
                    ['label' => 'Visit the workshop', 'url' => '/visit'],
                ],
            ]],
        ], $home );

        return $this->addCareers( $about );
    }


    /**
     * Creates the hidden booking page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addBook( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Book service',
            'title' => 'Book a Service or Repair | Kraft Autoservice Stuttgart',
            'path' => 'book',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Book your *slot*',
                'subtitle' => 'Book service',
                'text' => 'Tell us what your car needs and when you can bring it. We confirm your appointment and a fixed price within two hours during opening times.',
                'files' => [['id' => $this->frame( 'key-audi' ), 'type' => 'file']],
            ]],
            ['id' => 'booking-form', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Your appointment',
                'description' => 'Not sure what is wrong? Choose "Diagnosis" and describe what you hear, feel or see. We call you back before we start any work.',
                'inputs' => [
                    ['field' => 'name', 'required' => true, 'input' => 'text'],
                    ['field' => 'email', 'required' => true, 'input' => 'text'],
                    ['field' => 'telephone', 'required' => true, 'input' => 'text'],
                    ['field' => 'Make, model and year', 'required' => true, 'input' => 'text'],
                    ['field' => 'Licence plate', 'required' => false, 'input' => 'text'],
                    ['field' => 'Service', 'required' => true, 'input' => 'select', 'options' => "Servicing\nMain inspection (HU)\nBrakes\nTyre change\nDiagnosis\nAir conditioning\nElectric or hybrid\nOther repair"],
                    ['field' => 'Courtesy car', 'required' => false, 'input' => 'select', 'options' => "Not needed\nCourtesy car, €19 a day\nE-bike, free"],
                    ['field' => 'Preferred date', 'required' => true, 'input' => 'text'],
                    ['field' => 'What should we know?', 'required' => false, 'input' => 'textarea'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Booking questions',
                'items' => [
                    ['title' => 'Can I drop off my car early or late?', 'text' => 'Yes. Use the key drop box next to the entrance, it is open around the clock. Put your key in an envelope with your name and phone number.'],
                    ['title' => 'Will you call before extra work?', 'text' => 'Always. If we find something that is not part of your order, we send you photos and a price and only continue after you agree.'],
                    ['title' => 'How long will my car be in the workshop?', 'text' => 'A service or tyre change takes one to two hours, so you can wait in our lounge. Bigger repairs usually take one day.'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the brakes and tyres page below the services page.
     *
     * @param Page $parent Services page
     * @return static Same object for fluent calls
     */
    protected function addBrakes( Page $parent ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Brakes & tyres',
            'title' => 'Brakes, Tyre Fitting, Alignment and Tyre Storage in Stuttgart | Kraft',
            'path' => 'brakes-tyres',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Stop safely, *grip* in every season',
                'subtitle' => 'Brakes & tyres',
                'text' => 'Free brake check with every tyre change. We measure pads, discs and tread, show you the results on the lift and replace only what is worn.',
                'buttons' => [
                    ['label' => 'Book a tyre change', 'url' => '/book'],
                    ['label' => 'See all prices', 'url' => '/prices'],
                ],
                'files' => [['id' => $this->frame( 'tyre-fitting' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Brake & tyre services',
                'columns' => '3',
                'cards' => [
                    ['title' => 'Brake pads', 'text' => "**From €129** `60 min`\nFront or rear axle, quality pads and a test drive included.", 'file' => ['id' => $this->img( 'brake-disc' ), 'type' => 'file']],
                    ['title' => 'Discs & pads', 'text' => "**From €249** `90 min`\nNew discs and pads per axle, with cleaned and greased calipers.", 'file' => ['id' => $this->img( 'brake-drilled' ), 'type' => 'file']],
                    ['title' => 'Tyre change', 'text' => "**€39** `30 min`\nFour wheels swapped, balanced and torqued, tyre pressure set.", 'file' => ['id' => $this->img( 'alloy' ), 'type' => 'file']],
                    ['title' => 'Tyre storage', 'text' => "**€59** `per season`\nWashed, checked and stored in our tyre hotel, ready when you are.", 'file' => ['id' => $this->img( 'tyres' ), 'type' => 'file']],
                    ['title' => 'Wheel alignment', 'text' => "**€89** `45 min`\n3D alignment for even tyre wear and a car that drives straight.", 'file' => ['id' => $this->img( 'lift-underside' ), 'type' => 'file']],
                    ['title' => 'Brake fluid', 'text' => "**€69** `30 min`\nEvery two years, because old fluid absorbs water and boils sooner.", 'file' => ['id' => $this->img( 'tool-wall' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'When to replace',
                'header' => 'row+col',
                'table' => [
                    ['', 'Legal minimum', 'We recommend'],
                    ['Summer tyre tread', '1.6 mm', '3 mm'],
                    ['Winter tyre tread', '1.6 mm', '4 mm'],
                    ['Tyre age', '–', '8 years'],
                    ['Brake pad thickness', '–', '3 mm'],
                    ['Brake fluid', '–', 'Every 2 years'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Brake & tyre questions',
                'items' => [
                    ['title' => 'Why does my brake squeal?', 'text' => 'Often it is just dust or rust after rain. If it squeals on every stop or grinds, the pads are worn and should be checked soon.'],
                    ['title' => 'Do you sell tyres?', 'text' => 'Yes, from all major brands at online prices. We give you three options with price, label values and delivery date.'],
                    ['title' => 'Do I need to book a tyre change?', 'text' => 'In October and April, yes. The rest of the year you can usually come without an appointment.'],
                ],
            ]],
        ], $parent );

        return $this;
    }


    /**
     * Creates the careers page below the about page.
     *
     * @param Page $parent About page
     * @return static Same object for fluent calls
     */
    protected function addCareers( Page $parent ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Careers',
            'title' => 'Mechanic and Apprentice Jobs in Stuttgart | Kraft Autoservice',
            'path' => 'careers',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Good tools, *fair hours*',
                'subtitle' => 'Careers',
                'text' => 'No piecework, no Saturday afternoons and a lift for every mechanic. We pay above the collective agreement and train you on new technology.',
                'buttons' => [
                    ['label' => 'Send your application', 'url' => 'mailto:jobs@kraft-autoservice.example'],
                ],
                'files' => [['id' => $this->frame( 'under-car' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'tool-wall' ), 'type' => 'file'],
                'position' => 'grid-end',
                'ratio' => '1-1',
                'text' => "## What you get\n\n- A salary above the collective agreement and a yearly bonus\n- Your own lift and a complete set of quality tools\n- 30 days of holiday and a 38-hour week\n- High-voltage and manufacturer training, paid by us\n- Work clothes, washed every week",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Open positions',
                'columns' => '3',
                'cards' => [
                    ['title' => 'Automotive mechanic (m/f/d)', 'text' => "`Full time`\nTrained mechatronics technician with a few years of experience on all makes."],
                    ['title' => 'High-voltage technician (m/f/d)', 'text' => "`Full time`\nCertified for level 2 or ready to get certified with us."],
                    ['title' => 'Apprentice 2027 (m/f/d)', 'text' => "`3.5 years`\nYou like to know how things work and you are not afraid of dirty hands."],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Come by for a *coffee*',
                'text' => 'Send us a few lines about yourself. A CV is welcome but not required, and every application gets an answer within a week.',
                'buttons' => [
                    ['label' => 'Send your application', 'url' => 'mailto:jobs@kraft-autoservice.example'],
                    ['label' => 'Meet the team', 'url' => '/about'],
                ],
            ]],
        ], $parent );

        return $this;
    }


    /**
     * Creates the diagnostics page below the services page.
     *
     * @param Page $parent Services page
     * @return static Same object for fluent calls
     */
    protected function addDiagnostics( Page $parent ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Diagnostics',
            'title' => 'Engine Diagnostics, Electrical Faults and Air Conditioning in Stuttgart | Kraft',
            'path' => 'diagnostics',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Warning light on? *We find the cause*',
                'subtitle' => 'Diagnostics',
                'text' => 'Reading a fault code takes a minute. Finding out why it is there takes experience. We test, measure and only replace parts that are really broken.',
                'buttons' => [
                    ['label' => 'Book a diagnosis', 'url' => '/book'],
                    ['label' => 'Warning lights explained', 'url' => '/warning-lights'],
                ],
                'files' => [['id' => $this->frame( 'diagnostics' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'How we diagnose',
                'layout' => 'horizontal',
                'items' => [
                    ['label' => '10 min', 'title' => 'Listen', 'text' => 'When does it happen, what do you hear, smell or feel?'],
                    ['label' => '15 min', 'title' => 'Read', 'text' => 'Fault memory, live data and software updates for all makes.'],
                    ['label' => '30 min', 'title' => 'Measure', 'text' => 'Multimeter, smoke test and oscilloscope instead of guessing.'],
                    ['label' => 'Call', 'title' => 'Quote', 'text' => 'You get the cause, photos and a fixed price before we repair.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Diagnostic services',
                'columns' => '3',
                'cards' => [
                    ['title' => 'Fault diagnosis', 'text' => "**€59** `30 min`\nCredited against the repair if you have it done with us.", 'file' => ['id' => $this->img( 'cluster' ), 'type' => 'file']],
                    ['title' => 'Electrical faults', 'text' => "**€89** `per hour`\nBattery drain, windows, lights and sensors traced to the cable.", 'file' => ['id' => $this->img( 'engine-bay' ), 'type' => 'file']],
                    ['title' => 'Air conditioning', 'text' => "**€99** `60 min`\nRefrigerant, leak test, new filter and a disinfection of the system.", 'file' => ['id' => $this->img( 'speedo' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Diagnostic questions',
                'items' => [
                    ['title' => 'Can I keep driving with the engine light on?', 'text' => 'If it is yellow and the car drives normally, usually yes, but book a check within a few days. If it flashes or is red, stop and call our breakdown service.'],
                    ['title' => 'Do you have software for my car?', 'text' => 'We work with manufacturer-level testers for all European, Japanese and Korean makes and update control units online.'],
                    ['title' => 'Why is the diagnosis not free?', 'text' => 'Because a good diagnosis saves you money on parts you don\'t need. The fee is credited if we carry out the repair.'],
                ],
            ]],
        ], $parent );

        return $this;
    }


    /**
     * Creates the electric and hybrid page below the services page.
     *
     * @param Page $parent Services page
     * @return static Same object for fluent calls
     */
    protected function addElectric( Page $parent ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Electric & hybrid',
            'title' => 'Electric and Hybrid Car Servicing and Battery Checks in Stuttgart | Kraft',
            'path' => 'electric-hybrid',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'High voltage, *in safe hands*',
                'subtitle' => 'Electric & hybrid',
                'text' => 'Our certified high-voltage technicians service and repair electric and hybrid cars of all makes, without voiding your manufacturer warranty.',
                'buttons' => [
                    ['label' => 'Book an EV service', 'url' => '/book'],
                    ['label' => 'See all prices', 'url' => '/prices'],
                ],
                'files' => [['id' => $this->frame( 'ev-plug' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'layout' => 'figures',
                'cards' => [
                    ['title' => '2', 'text' => 'Certified high-voltage technicians'],
                    ['title' => '800 V', 'text' => 'Systems we can test and repair'],
                    ['title' => '45 min', 'text' => 'For a battery health report'],
                    ['title' => '-30 %', 'text' => 'Lower service costs than a combustion car'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'ev-port' ), 'type' => 'file'],
                'position' => 'grid-end',
                'ratio' => '1-1',
                'text' => "## Know your *battery*\n\nBuying or selling a used electric car? Our battery health report measures the usable capacity of every cell module and shows you the real state of health in percent.\n\n- **Battery health report:** €79, with a printed certificate\n- **Brake service:** because regenerative braking lets discs rust\n- **Cabin filter and coolant:** often forgotten, important for range\n\n[Book a battery check](/book)",
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'EV questions',
                'items' => [
                    ['title' => 'Does an electric car even need a service?', 'text' => 'Less than a petrol car, but yes: brakes, tyres, coolant, cabin filter, suspension and a check of the high-voltage system every one to two years.'],
                    ['title' => 'Will I lose my warranty?', 'text' => 'No. We service to manufacturer specification with approved parts and stamp your digital service book.'],
                    ['title' => 'Can you charge my car while it is with you?', 'text' => 'Yes, we have two 22 kW chargers. Your car leaves with at least 80 percent.'],
                ],
            ]],
        ], $parent );

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
            'title' => 'Imprint | Kraft Autoservice',
            'path' => 'imprint',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "# Imprint\n\n**Kraft Autoservice GmbH**\nHeilbronner Straße 312\n70469 Stuttgart\nGermany\n\nTelephone: +49 711 8862 140\nEmail: service@kraft-autoservice.example\n\nManaging director: Sabine Kraft\nCommercial register: Stuttgart Local Court, HRB 00000\nVAT ID: DE000000000\n\nMember of the Stuttgart guild of motor vehicle trades.\n\n## Consumer dispute resolution\n\nWe take part in dispute resolution proceedings before the arbitration board of the motor vehicle trade.\n\nThis is a demo website for the Torque theme. Kraft Autoservice is a fictional garage and the people named are not real.",
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the offers page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addOffers( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Offers',
            'title' => 'Service Offers: Tyre Change, A/C Service and Brake Check | Kraft Stuttgart',
            'path' => 'offers',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Seasonal *offers*',
                'subtitle' => 'Offers',
                'text' => 'Mention the code when you book online or show this page at the service desk. Offers can\'t be combined and are valid while slots last.',
                'buttons' => [
                    ['label' => 'Book service', 'url' => '/book'],
                ],
                'files' => [['id' => $this->frame( 'tyres' ), 'type' => 'file']],
            ]],
            $this->coupons( 'Valid until the end of the season' ),
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "All prices in euros including VAT, parts and labour for regular cars. Vans, SUVs over 2.5 tonnes and run-flat tyres may cost more, we tell you the price before we start.",
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the price list page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addPrices( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Prices',
            'title' => 'Fixed Prices for Servicing, Brakes, Tyres and Inspections | Kraft Stuttgart',
            'path' => 'prices',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Fixed prices, *no surprises*',
                'subtitle' => 'Price list',
                'text' => 'Prices for compact and mid-size cars, including parts, labour and VAT. You get a binding quote for your car before we start.',
                'buttons' => [
                    ['label' => 'Book service', 'url' => '/book'],
                    ['label' => 'Current offers', 'url' => '/offers'],
                ],
                'files' => [['id' => $this->frame( 'wrenches' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
                'title' => 'Workshop prices',
                'text' => 'Labour rate €109 per hour. Larger cars, vans and premium makes may differ, the quote for your car is binding.',
                'items' => [
                    [
                        'name' => 'Servicing',
                        'prices' => [['id' => 'servicing', 'label' => 'From €89']],
                        'text' => 'To manufacturer specification.',
                        'features' => "- Oil service `60 min` **€89**\n- Small inspection `90 min` **€189**\n- Large inspection `3 h` **€349**\n- Timing belt kit `4 h` **€590**\n- Main inspection (HU) `45 min` **€139**",
                    ],
                    [
                        'name' => 'Brakes & tyres',
                        'prices' => [['id' => 'brakes', 'label' => 'From €39']],
                        'text' => 'Free brake check included.',
                        'features' => "- Tyre change `30 min` **€39**\n- Tyre storage `per season` **€59**\n- Brake pads, per axle `60 min` **€129**\n- Discs & pads, per axle `90 min` **€249**\n- Wheel alignment `45 min` **€89**",
                        'highlight' => true,
                        'badge' => 'Most booked',
                    ],
                    [
                        'name' => 'Diagnostics & A/C',
                        'prices' => [['id' => 'diagnostics', 'label' => 'From €29']],
                        'text' => 'Credited against repairs.',
                        'features' => "- Battery test `15 min` **€29**\n- Fault diagnosis `30 min` **€59**\n- A/C service `60 min` **€99**\n- Battery health report (EV) `45 min` **€79**\n- Headlight restoration `60 min` **€69**",
                    ],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Oil service by car size',
                'header' => 'row+col',
                'table' => [
                    ['', 'Oil', 'Price incl. filter'],
                    ['Small car, e.g. VW Polo', '4.0 l', '€89'],
                    ['Compact car, e.g. VW Golf', '4.5 l', '€99'],
                    ['Mid-size, e.g. Audi A4', '5.5 l', '€119'],
                    ['SUV and van', '6.5 l', '€139'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Good to know',
                'items' => [
                    ['title' => 'Is the quote binding?', 'text' => 'Yes. If we find something during the repair that is not in the quote, we call you first and you decide.'],
                    ['title' => 'Do you use original parts?', 'text' => 'We use original parts or parts of matching quality from the same suppliers. You choose, and both keep your warranty.'],
                    ['title' => 'How can I pay?', 'text' => 'By card, phone or bank transfer. Repairs over €1,000 can be paid in up to twelve monthly instalments.'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the privacy page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addPrivacy( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Privacy',
            'title' => 'Privacy Policy | Kraft Autoservice',
            'path' => 'privacy',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "# Privacy policy\n\n## Who is responsible\n\nKraft Autoservice GmbH, Heilbronner Straße 312, 70469 Stuttgart, privacy@kraft-autoservice.example.\n\n## Appointments and repairs\n\nWhen you book a service or repair, we use your name, contact details, vehicle data and licence plate to plan and carry out the work and to issue the invoice (Art. 6 (1) (b) GDPR). Service records are stored in your customer file and, with your consent, entered in the manufacturer's digital service book.\n\n## Vehicle data\n\nDuring diagnosis we read the fault memory and service data of your car. We only use this data for the repair and delete diagnostic logs after twelve months.\n\n## This website\n\nThe website doesn't use tracking or advertising cookies. Our server stores technical access data such as the IP address for seven days to protect against attacks. The map is loaded from OpenStreetMap only after you open it.\n\n## Your rights\n\nYou have the right to access, rectification, erasure, restriction of processing and data portability, and you can lodge a complaint with the Baden-Württemberg Commissioner for Data Protection and Freedom of Information.\n\nThis is a demo website for the Torque theme. Kraft Autoservice is a fictional garage.",
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the servicing page below the services page.
     *
     * @param Page $parent Services page
     * @return static Same object for fluent calls
     */
    protected function addServicing( Page $parent ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Servicing',
            'title' => 'Car Servicing and Main Inspection (HU) for All Makes in Stuttgart | Kraft',
            'path' => 'servicing',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Dealer quality, *independent prices*',
                'subtitle' => 'Servicing',
                'text' => 'We service your car exactly to the manufacturer\'s plan, with approved oils and parts, and stamp your service book. Your warranty stays intact, your bill gets smaller.',
                'buttons' => [
                    ['label' => 'Book a service', 'url' => '/book'],
                    ['label' => 'See all prices', 'url' => '/prices'],
                ],
                'files' => [['id' => $this->frame( 'oil' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Service packages',
                'columns' => '3',
                'cards' => [
                    ['title' => 'Oil service', 'text' => "**From €89** `60 min`\nApproved oil, new filter, fluid levels and a 30-point safety check.", 'file' => ['id' => $this->img( 'oil' ), 'type' => 'file']],
                    ['title' => 'Inspection service', 'text' => "**From €189** `90 min`\nAll work in your service plan, filters, spark plugs and a road test.", 'file' => ['id' => $this->img( 'lift-mercedes' ), 'type' => 'file']],
                    ['title' => 'Main inspection (HU)', 'text' => "**€139** `45 min`\nWith an inspector in our workshop every weekday, emissions test included.", 'file' => ['id' => $this->img( 'lift-red' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'Your service day',
                'layout' => 'vertical',
                'items' => [
                    ['label' => '07:30', 'title' => 'Drop off', 'text' => 'Short talk at the service desk or key box before opening.'],
                    ['label' => '09:00', 'title' => 'Inspection', 'text' => 'Your car on the lift, every point of the service plan checked.'],
                    ['label' => '10:30', 'title' => 'Call with photos', 'text' => 'Only if we find something that is not in your order.'],
                    ['label' => '15:00', 'title' => 'Pick up', 'text' => 'Washed car, stamped service book and old parts in the boot.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Servicing questions',
                'items' => [
                    ['title' => 'Can a garage service a car under warranty?', 'text' => 'Yes. European law allows independent garages to service cars under warranty, as long as the manufacturer\'s plan and approved parts are used.'],
                    ['title' => 'How often does my car need a service?', 'text' => 'Usually every 15,000 to 30,000 km or once a year. Your car shows the next date in the dashboard, or we read it out for you.'],
                    ['title' => 'Can you combine the service and the HU?', 'text' => 'Yes, and it saves you a second trip. The inspector comes to our workshop every weekday morning.'],
                ],
            ]],
        ], $parent );

        return $this;
    }


    /**
     * Creates the services page and its sub-pages below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addServices( Page $home ) : static
    {
        $services = $this->page( [
            'lang' => 'en',
            'name' => 'Services',
            'title' => 'Car Repair and Servicing for All Makes in Stuttgart | Kraft Autoservice',
            'path' => 'services',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Everything your car needs, *under one roof*',
                'subtitle' => 'Services',
                'text' => 'Six lifts, manufacturer-level testers and mechanics who know all makes. From the oil change to the high-voltage battery.',
                'buttons' => [
                    ['label' => 'Book service', 'url' => '/book'],
                    ['label' => 'Price list', 'url' => '/prices'],
                ],
                'files' => [['id' => $this->frame( 'repair-hall' ), 'type' => 'file']],
            ]],
            $this->services(),
            $this->checks(),
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'How your repair works',
                'layout' => 'horizontal',
                'items' => [
                    ['label' => 'Book', 'title' => 'Online or by phone', 'text' => 'Choose a slot, tell us what your car needs.'],
                    ['label' => 'Check', 'title' => 'On the lift', 'text' => 'We inspect and find the cause, not just the symptom.'],
                    ['label' => 'Quote', 'title' => 'Fixed price', 'text' => 'Photos and a binding price, nothing starts without your go.'],
                    ['label' => 'Drive', 'title' => 'Pick up', 'text' => 'Old parts in the boot, 24 month warranty on the repair.'],
                ],
            ]],
            $this->cta(),
        ], $home );

        return $this->addServicing( $services )
            ->addBrakes( $services )
            ->addDiagnostics( $services )
            ->addElectric( $services );
    }


    /**
     * Creates the tips page and its posts below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addTips( Page $home ) : static
    {
        $tips = $this->page( [
            'id' => $this->tipsId,
            'lang' => 'en',
            'name' => 'Tips',
            'title' => 'Car Care Tips from the Workshop | Kraft Autoservice',
            'path' => 'tips',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Straight from *the workshop*',
                'subtitle' => 'Tips',
                'text' => 'What our mechanics tell their friends: how to save money, avoid breakdowns and know when a noise is worth a visit.',
                'files' => [['id' => $this->frame( 'engine-bay' ), 'type' => 'file']],
            ]],
            ['id' => 'tips-list', 'type' => 'blog', 'group' => 'main', 'data' => [
                'layout' => 'cards',
                'parent-page' => ['value' => $this->tipsId, 'label' => 'Tips'],
                'order' => '_lft',
                'limit' => 12,
            ]],
        ], $home );

        $this->post( $tips, [
            'name' => 'Winter tyres',
            'title' => 'Winter Tyres: When to Change and How Much Tread You Need',
            'path' => 'winter-tyres',
        ], 'Winter tyres: when to change and what to check',
            "The old rule says from October to Easter. The better rule is the temperature: below seven degrees, the rubber of summer tyres gets hard and your braking distance on a wet road grows by several metres.\n\nIn Germany, winter conditions require tyres with the alpine symbol. Here is what we check at every tyre change and how you can tell if your tyres will make it through another season.",
            'tyre-fitting',
            [
                ['title' => '7 °C', 'text' => 'When summer tyres lose grip'],
                ['title' => '4 mm', 'text' => 'Tread we recommend for winter'],
                ['title' => '8 yrs', 'text' => 'Maximum tyre age'],
            ],
            [
                ['label' => 'October', 'title' => 'Book early', 'text' => 'The first frost brings everyone at once, so book in September.'],
                ['label' => 'Check', 'title' => 'Tread and age', 'text' => 'The DOT code on the sidewall shows week and year of production.'],
                ['label' => 'Fit', 'title' => 'Balance and torque', 'text' => 'And tighten the wheel nuts again after 50 km.'],
                ['label' => 'April', 'title' => 'Store properly', 'text' => 'Cool, dark and dry, or in our tyre hotel.'],
            ],
            [
                ['title' => 'Are all-season tyres a good idea?', 'text' => 'For city drivers with less than 15,000 km a year, often yes. If you drive into the mountains, choose real winter tyres.'],
                ['title' => 'What happens if I drive with summer tyres in snow?', 'text' => 'You risk a fine and penalty points, and your insurance can reduce payments after an accident.'],
                ['title' => 'Do my tyre sensors need to be programmed?', 'text' => 'On many cars, yes. We programme the pressure sensors at every change, free of charge.'],
            ],
        );

        $this->post( $tips, [
            'name' => 'Warning lights',
            'title' => 'Dashboard Warning Lights: Stop Now or Drive On?',
            'path' => 'warning-lights',
        ], 'Dashboard warning lights explained',
            "Modern cars have more than forty warning lights, but you only need to know one rule: red means stop as soon as it is safe, yellow means book a check soon, and green or blue is just information.\n\nThese are the lights our service desk gets the most calls about, what they mean and what the repair usually costs.",
            'cluster',
            [
                ['title' => 'Red', 'text' => 'Stop safely and call for help'],
                ['title' => 'Yellow', 'text' => 'Book a check within days'],
                ['title' => '€59', 'text' => 'Fault diagnosis, credited on repair'],
            ],
            [
                ['label' => 'Red', 'title' => 'Oil pressure', 'text' => 'Switch off the engine immediately, it can be destroyed in minutes.'],
                ['label' => 'Red', 'title' => 'Coolant temperature', 'text' => 'Stop, let the engine cool down and never open a hot radiator.'],
                ['label' => 'Yellow', 'title' => 'Engine light', 'text' => 'Often a sensor or the ignition. If it flashes, drive slowly to us.'],
                ['label' => 'Yellow', 'title' => 'Tyre pressure', 'text' => 'Check all four tyres at the next petrol station.'],
            ],
            [
                ['title' => 'The light went off again. Is everything fine?', 'text' => 'Not necessarily. The fault stays in the memory, so a quick reading shows what happened.'],
                ['title' => 'Can I reset a warning light myself?', 'text' => 'Some service reminders, yes. Fault lights come back if the cause is still there.'],
                ['title' => 'What does the breakdown service cost?', 'text' => 'Towing within Stuttgart costs €129, free if we carry out the repair.'],
            ],
        );

        $this->post( $tips, [
            'name' => 'Inspection checklist',
            'title' => 'Pass Your Main Inspection (HU) the First Time',
            'path' => 'inspection-checklist',
        ], 'Pass your main inspection the first time',
            "Every fifth car fails its main inspection, and most of the faults are small: a broken bulb, worn wiper blades or a warning light. You can find many of them yourself in ten minutes on your driveway.\n\nThis is the checklist we give our customers before the inspector comes to our workshop.",
            'lift-underside',
            [
                ['title' => '1 in 5', 'text' => 'Cars fail the first time'],
                ['title' => '10 min', 'text' => 'For your own pre-check'],
                ['title' => '€39', 'text' => 'Our pre-inspection with free repairs of small faults'],
            ],
            [
                ['label' => 'Lights', 'title' => 'Walk around', 'text' => 'Headlights, brake lights, indicators and number plate light.'],
                ['label' => 'Glass', 'title' => 'Windscreen and wipers', 'text' => 'No cracks in the driver\'s view, wipers without streaks.'],
                ['label' => 'Tyres', 'title' => 'Tread and damage', 'text' => 'At least 1.6 mm and no bulges on the sidewalls.'],
                ['label' => 'Inside', 'title' => 'Warning lights', 'text' => 'No warning light may stay on after the engine starts.'],
            ],
            [
                ['title' => 'What happens if my car fails?', 'text' => 'You get a month to fix the faults. We repair them right away and the inspector checks again the same day.'],
                ['title' => 'Can I drive with an overdue inspection?', 'text' => 'Up to two months late costs you nothing more than the inspection, after that fines and a more expensive inspection apply.'],
                ['title' => 'Do I need my vehicle registration?', 'text' => 'Yes, please leave the registration certificate part I in the car.'],
            ],
        );

        return $this;
    }


    /**
     * Creates the used cars page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addUsedCars( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Used cars',
            'title' => 'Checked Used Cars with 12 Month Warranty in Stuttgart | Kraft Autoservice',
            'path' => 'used-cars',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Used cars we would *drive ourselves*',
                'subtitle' => 'Used cars',
                'text' => 'Every car on our lot has passed our 110-point inspection, has a full service history and comes with a 12 month warranty, new HU and fresh service.',
                'buttons' => [
                    ['label' => 'See the cars', 'url' => '#stock'],
                    ['label' => 'Value my car', 'url' => '/book'],
                ],
                'files' => [['id' => $this->frame( 'key-handover' ), 'type' => 'file']],
            ]],
            $this->vehicles( 'stock', 'Currently on our lot', true ),
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Every car comes with',
                'layout' => 'badges',
                'cards' => [
                    ['title' => '110-point check', 'text' => 'Engine, brakes, body and electronics', 'file' => $this->icon( 'check' )],
                    ['title' => '12 month warranty', 'text' => 'Extendable to 36 months', 'file' => $this->icon( 'shield' )],
                    ['title' => 'New HU', 'text' => 'Main inspection not older than 3 months', 'file' => $this->icon( 'gauge' )],
                    ['title' => 'Fresh service', 'text' => 'Oil, filters and brake fluid', 'file' => $this->icon( 'wrench' )],
                    ['title' => 'Fair trade-in', 'text' => 'Written offer for your old car', 'file' => $this->icon( 'key' )],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'dealer-row' ), 'type' => 'file'],
                'position' => 'grid-start',
                'ratio' => '1-1',
                'text' => "## Financing and *trade-in*\n\nSpread the price over 12 to 84 months with our banking partner, from 4.99 % APR. Bring your current car and we make you a written offer after a 20 minute test drive.\n\n- **No deposit needed** for customers with good credit\n- **Trade-in value** credited directly against the price\n- **Registration service** with new plates for €89\n\n[Ask for a financing quote](/book)",
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Used car questions',
                'items' => [
                    ['title' => 'Can I book a test drive?', 'text' => 'Yes, from Monday to Saturday. Bring your driving licence, a test drive takes about 30 minutes.'],
                    ['title' => 'Can I return a car?', 'text' => 'If a fault appears within 14 days that we should have found, we repair it free of charge or take the car back.'],
                    ['title' => 'Do you buy cars without selling one?', 'text' => 'Yes, we buy well-kept cars up to ten years old and pay by bank transfer on the same day.'],
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
            'name' => 'Contact',
            'title' => 'Contact: Opening Hours, Directions and Breakdown Service | Kraft Autoservice',
            'path' => 'visit',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Find us *in Feuerbach*',
                'subtitle' => 'Contact',
                'text' => 'Our workshop is on Heilbronner Straße, two minutes from the B10 and next to the Feuerbach train station. Drive into the yard, the service desk is on the left.',
                'buttons' => [
                    ['label' => 'Get directions', 'url' => 'https://www.openstreetmap.org/?mlat=48.8146&mlon=9.1689#map=17/48.8146/9.1689'],
                    ['label' => 'Call us', 'url' => 'tel:+497118862140'],
                ],
                'files' => [['id' => $this->frame( 'lift-mercedes' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Opening hours',
                'header' => 'row+col',
                'table' => [
                    ['', 'Workshop', 'Used cars'],
                    ['Monday – Friday', '07:30 – 18:00', '09:00 – 18:00'],
                    ['Saturday', '08:00 – 13:00', '09:00 – 14:00'],
                    ['Sunday', 'closed', 'closed'],
                    ['Breakdown service', '24/7', '–'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'While your car is with us',
                'columns' => '3',
                'cards' => [
                    ['title' => 'Customer lounge', 'text' => 'Coffee, Wi-Fi and a view into the workshop for services up to two hours.', 'file' => ['id' => $this->img( 'workshop' ), 'type' => 'file']],
                    ['title' => 'Courtesy cars', 'text' => 'Four small cars for €19 a day, or a free e-bike for the city.', 'file' => ['id' => $this->img( 'key-audi' ), 'type' => 'file']],
                    ['title' => 'Key drop box', 'text' => 'Drop off and pick up your car around the clock.', 'file' => ['id' => $this->img( 'key-handover' ), 'type' => 'file']],
                ],
            ]],
            $this->map( 'Find us' ),
            ['id' => Utils::uid(), 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Send us a message',
                'description' => 'For quotes, used car questions or anything else. We answer within one working day.',
                'inputs' => [
                    ['field' => 'name', 'required' => true, 'input' => 'text'],
                    ['field' => 'email', 'required' => true, 'input' => 'text'],
                    ['field' => 'telephone', 'required' => false, 'input' => 'text'],
                    ['field' => 'Message', 'required' => true, 'input' => 'textarea'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Returns the workshop promise badges element.
     *
     * @return array<string, mixed> Cards content element
     */
    protected function checks() : array
    {
        return ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
            'title' => 'The Kraft promise',
            'layout' => 'badges',
            'cards' => [
                ['title' => 'Fixed-price quotes', 'text' => 'Binding before we start', 'file' => $this->icon( 'check' )],
                ['title' => 'Warranty-safe', 'text' => 'Serviced to manufacturer plan', 'file' => $this->icon( 'shield' )],
                ['title' => 'Old parts back', 'text' => 'You see what we replaced', 'file' => $this->icon( 'wrench' )],
                ['title' => 'Courtesy car', 'text' => 'From €19 a day or a free e-bike', 'file' => $this->icon( 'key' )],
                ['title' => '24/7 breakdown', 'text' => 'Towing within Stuttgart', 'file' => $this->icon( 'gauge' )],
            ],
        ]];
    }


    /**
     * Returns the service coupons element.
     *
     * @param string $text Coupon intro text
     * @return array<string, mixed> Pricing content element
     */
    protected function coupons( string $text ) : array
    {
        return ['id' => 'coupons', 'type' => 'pricing', 'group' => 'main', 'data' => [
            'title' => 'Service coupons',
            'text' => $text,
            'items' => [
                [
                    'name' => 'Tyre change',
                    'prices' => [['id' => 'tyres', 'amount' => 29, 'label' => '€29']],
                    'text' => 'Instead of €39, code TYRE29',
                    'features' => "- Four wheels swapped and balanced\n- Tread and pressure check\n- Free brake check",
                    'url' => '/book',
                    'button' => 'Book tyre change',
                ],
                [
                    'name' => 'A/C service',
                    'prices' => [['id' => 'ac', 'amount' => 79, 'label' => '€79']],
                    'text' => 'Instead of €99, code COOL79',
                    'features' => "- Refrigerant refill and leak test\n- New cabin filter\n- Disinfection of the system",
                    'url' => '/book',
                    'button' => 'Book A/C service',
                    'badge' => 'Save €20',
                ],
                [
                    'name' => 'HU pre-check',
                    'prices' => [['id' => 'hu', 'amount' => 39, 'label' => '€39']],
                    'text' => 'Bulbs and wipers included',
                    'features' => "- 40-point check before the inspection\n- Bulbs and wiper blades replaced free\n- Inspection the same day",
                    'url' => '/book',
                    'button' => 'Book pre-check',
                ],
            ],
        ]];
    }


    /**
     * Returns the booking call to action element.
     *
     * @return array<string, mixed> CTA content element
     */
    protected function cta() : array
    {
        return ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
            'title' => 'Book in two minutes, *drive on today*',
            'text' => 'Choose a slot online or call the service desk. For breakdowns, our towing service answers around the clock.',
            'buttons' => [
                ['label' => 'Book service', 'url' => '/book'],
                ['label' => 'Call +49 711 8862 140', 'url' => 'tel:+497118862140'],
            ],
        ]];
    }


    /**
     * Creates the shared Kraft footer and returns its ID.
     *
     * @return string Element ID
     */
    protected function element() : string
    {
        return $this->element ??= $this->saveElement( 'cards', 'Kraft footer', ['columns' => '4', 'cards' => [
            ['title' => 'Services', 'text' => "- [Servicing](/servicing)\n- [Brakes & tyres](/brakes-tyres)\n- [Diagnostics](/diagnostics)\n- [Electric & hybrid](/electric-hybrid)"],
            ['title' => 'Customers', 'text' => "- [Price list](/prices)\n- [Offers](/offers)\n- [Used cars](/used-cars)\n- [Contact](/visit)"],
            ['title' => 'Kraft', 'text' => "- [About us](/about)\n- [Careers](/careers)\n- [Tips](/tips)\n- [Imprint](/imprint)\n- [Privacy](/privacy)"],
            ['title' => 'Service reminder', 'text' => "We remind you of your next service, HU and tyre change by email. No advertising, promised.\n\n[Sign up by email](mailto:service@kraft-autoservice.example?subject=Service%20reminder)"],
        ]] );
    }


    /**
     * Returns the ID of the primary workshop image.
     *
     * @return string File ID
     */
    protected function file() : string
    {
        return $this->img( 'workshop' );
    }


    /**
     * Creates (once) a 4:3 image for the slanted hero frames and returns its file ID.
     *
     * @param string $key PHOTOS key
     * @return string File ID
     */
    protected function frame( string $key ) : string
    {
        return $this->cropped( $key, 1200, 900, true, [600, 1200] );
    }


    /**
     * Returns the Kraft home page.
     *
     * @return Page Home page
     */
    protected function home() : Page
    {
        $elementId = $this->element();
        $fileId = $this->file();

        $config = [
            'website' => Validation::entry( 'website', ['title' => 'Kraft Autoservice'], 'config' ),
        ] + $this->logos( $this->logoFile() ) + [
            'torque::garage' => [
                'type' => 'torque::garage',
                'files' => [],
                'data' => [
                    'name' => 'Kraft Autoservice',
                    'business-type' => 'AutoRepair',
                    'street-address' => 'Heilbronner Straße 312',
                    'postal-code' => '70469',
                    'locality' => 'Stuttgart',
                    'country' => 'DE',
                    'telephone' => '+49 711 8862 140',
                    'breakdown' => '+49 711 8862 199',
                    'email' => 'service@kraft-autoservice.example',
                    'announcement' => 'Winter tyre season: book your tyre change now',
                    'booking' => '/book',
                    'prices' => '/prices',
                    'price-range' => '€€',
                    'notice' => 'All repairs come with a 24 month warranty on parts and labour.',
                    'action-bar' => true,
                    'hours' => [
                        ['id' => 'mon', 'day' => 'Monday', 'opens' => '07:30', 'closes' => '18:00'],
                        ['id' => 'tue', 'day' => 'Tuesday', 'opens' => '07:30', 'closes' => '18:00'],
                        ['id' => 'wed', 'day' => 'Wednesday', 'opens' => '07:30', 'closes' => '18:00'],
                        ['id' => 'thu', 'day' => 'Thursday', 'opens' => '07:30', 'closes' => '18:00'],
                        ['id' => 'fri', 'day' => 'Friday', 'opens' => '07:30', 'closes' => '18:00'],
                        ['id' => 'sat', 'day' => 'Saturday', 'opens' => '08:00', 'closes' => '13:00'],
                    ],
                ],
            ],
        ];

        $content = [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Honest repairs, *fixed prices*',
                'subtitle' => 'Independent garage in Stuttgart since 1998',
                'text' => 'Servicing, brakes, tyres, diagnostics and electric cars of all makes. You get a binding quote before we start and your old parts back when we are done.',
                'buttons' => [
                    ['label' => 'Book service', 'url' => '/book'],
                    ['label' => 'Price list', 'url' => '/prices'],
                ],
                'files' => [['id' => $this->frame( 'mechanic' ), 'type' => 'file']],
                'main' => true,
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'layout' => 'figures',
                'cards' => [
                    ['title' => '4.8', 'text' => 'Average rating from 1,240 reviews'],
                    ['title' => '1998', 'text' => 'Repairing cars in Feuerbach'],
                    ['title' => '6', 'text' => 'Lifts for all makes and EVs'],
                    ['title' => '24 mo', 'text' => 'Warranty on parts and labour'],
                ],
            ]],
            $this->services(),
            $this->checks(),
            $this->vehicles( 'used-cars', 'Checked used cars', false ),
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'ev-plug' ), 'type' => 'file'],
                'position' => 'grid-end',
                'ratio' => '1-1',
                'text' => "## Electric? *No problem.*\n\nTwo of our mechanics are certified high-voltage technicians. We service electric and hybrid cars of all makes, test the battery health and repair brakes that rust from too little use.\n\n- **Battery health report:** €79 with certificate\n- **EV service:** from €149, warranty-safe\n- **Charging:** your car leaves with 80 percent\n\n[Electric and hybrid servicing](/electric-hybrid)",
            ]],
            $this->coupons( 'Show this page or mention the code when you book.' ),
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'What drivers say',
                'items' => $this->reviews(),
            ]],
            ['id' => Utils::uid(), 'type' => 'blog', 'group' => 'main', 'data' => [
                'title' => 'Tips from the workshop',
                'layout' => 'cards',
                'parent-page' => ['value' => $this->tipsId, 'label' => 'Tips'],
                'order' => '_lft',
                'limit' => 3,
            ]],
            $this->map( 'Visit the workshop' ),
            $this->cta(),
            ['id' => Utils::uid(), 'type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        $meta = [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => 'Kraft Autoservice in Stuttgart-Feuerbach: servicing, main inspection, brakes, tyres, diagnostics and electric car repairs for all makes at fixed prices. Checked used cars with warranty.',
                'keywords' => 'car repair Stuttgart, Autowerkstatt Stuttgart, garage Feuerbach, car service Stuttgart, tyre change Stuttgart, used cars Stuttgart',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => 'Kraft Autoservice | Car Repair and Servicing in Stuttgart',
                'description' => 'Honest repairs, fixed prices.',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        return $this->saveRoot( 'Kraft Autoservice | Car Repair, Servicing and Used Cars in Stuttgart', $config, $meta, $content, $elementId, $fileId );
    }


    /**
     * Creates a white line icon once and returns its file reference.
     *
     * @param string $name Icon name: check, gauge, key, shield or wrench
     * @return array<string, string> File reference
     */
    protected function icon( string $name ) : array
    {
        $paths = [
            'check' => '<rect x="4" y="4" width="16" height="16" rx="1"/><path d="M8 12l3 3 5-6"/>',
            'gauge' => '<path d="M4 17a8 8 0 1 1 16 0"/><path d="M12 17l4-5"/><path d="M4 17h3M17 17h3"/>',
            'key' => '<circle cx="8" cy="12" r="4"/><path d="M12 12h9M18 12v3M21 12v2"/>',
            'shield' => '<path d="M12 3l7 3v5c0 4.5-3 8.2-7 10-4-1.8-7-5.5-7-10V6z"/><path d="M9 12l2 2 4-4"/>',
            'wrench' => '<path d="M14.5 6.5a4 4 0 0 0-5.3 5.1L4 16.8 7.2 20l5.2-5.2a4 4 0 0 0 5.1-5.3l-2.5 2.5-2.3-.7-.7-2.3z"/>',
        ];

        $this->icons[$name] ??= $this->svgFile(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' . $paths[$name] . '</svg>',
            'icon-' . $name . '.svg',
            ucfirst( $name ) . ' icon',
            'White line icon: ' . $name,
            true,
        );

        return ['id' => $this->icons[$name], 'type' => 'file'];
    }


    /**
     * Creates the Kraft SVG logo and returns its file ID.
     *
     * @return string File ID
     */
    protected function logoFile() : string
    {
        if( !isset( $this->logoFile ) )
        {
            $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 260 64" role="img" aria-labelledby="title desc">
  <title id="title">Kraft Autoservice logo</title>
  <desc id="desc">Red slanted parallelogram with a white K beside the bold uppercase KRAFT wordmark</desc>
  <path d="M14 8h40l-10 48H4z" fill="#C8102E"/>
  <path d="M22 18h7l-2.6 12.5L38 18h8.5L33.6 31.5 41 46h-8.3l-5.2-11-2.6 2.8L23 46h-7z" fill="#FFFFFF"/>
  <text x="64" y="38" fill="#16191D" font-family="Bahnschrift, 'DIN Alternate', 'Arial Narrow', Arial, sans-serif" font-size="34" font-weight="800" letter-spacing="1">KRAFT</text>
  <text x="65" y="54" fill="#C8102E" font-family="ui-monospace, Menlo, Consolas, monospace" font-size="10.5" letter-spacing="2.4">AUTOSERVICE</text>
</svg>
SVG;

            $this->logoFile = $this->svgFile(
                $svg,
                'kraft-autoservice-logo.svg',
                'Kraft Autoservice logo',
                'Red slanted parallelogram with a white K beside the bold uppercase KRAFT wordmark',
                true,
            );
        }

        return $this->logoFile;
    }


    /**
     * Returns the map element with address, opening hours and directions.
     *
     * @param string $title Map headline
     * @return array<string, mixed> Map content element
     */
    protected function map( string $title ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'map', 'group' => 'main', 'data' => [
            'title' => $title,
            'text' => "**Kraft Autoservice**\nHeilbronner Straße 312 · 70469 Stuttgart-Feuerbach\n\n**Opening hours**\nMonday to Friday 07:30–18:00\nSaturday 08:00–13:00\n\n**Service desk**\n+49 711 8862 140\n\n**Breakdown service, 24/7**\n+49 711 8862 199\n\n**Getting here**\nB10 exit Feuerbach, then 300 m on Heilbronner Straße. S4, S5 and S6 to Feuerbach station, five minutes on foot.",
            'location' => [
                'latitude' => 48.8146,
                'longitude' => 9.1689,
                'zoom' => 16,
            ],
            'button' => 'Open in OpenStreetMap',
        ]];
    }


    /**
     * Creates a Torque demo page below the given parent and returns it.
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

        return $this->savePage( $data, $content, $parent, $elementId, $fileId, $footer, 'Kraft Autoservice, car repair Stuttgart, Autowerkstatt Feuerbach, car service, tyre change, main inspection, used cars' );
    }


    /**
     * Builds the Torque demo page tree.
     */
    protected function pages() : void
    {
        $this->tipsId = (string) Str::uuid7();
        $home = $this->home();

        $this->addServices( $home )
            ->addPrices( $home )
            ->addUsedCars( $home )
            ->addOffers( $home )
            ->addAbout( $home )
            ->addTips( $home )
            ->addVisit( $home )
            ->addBook( $home )
            ->addImprint( $home )
            ->addPrivacy( $home );
    }


    /**
     * Creates a post below the tips page.
     *
     * @param Page $parent Tips page
     * @param array<string, string> $data Page name, title and path
     * @param string $title Article headline
     * @param string $text Article text
     * @param string $cover PHOTOS key of the cover image
     * @param array<int, array<string, string>> $facts Key facts as figure cards
     * @param array<int, array<string, string>> $steps Steps of the checklist
     * @param array<int, array<string, string>> $questions Frequently asked questions
     * @return Page Created page
     */
    protected function post( Page $parent, array $data, string $title, string $text, string $cover,
        array $facts, array $steps, array $questions ) : Page
    {
        return $this->page( $data + [
            'lang' => 'en',
            'type' => 'blog',
            'status' => 2,
        ], [
            $this->article( $title, $text, $this->img( $cover ) ),
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'layout' => 'figures',
                'columns' => '3',
                'cards' => $facts,
            ]],
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'Step by step',
                'layout' => 'vertical',
                'items' => $steps,
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Questions from our customers',
                'items' => $questions,
            ]],
            $this->cta(),
        ], $parent );
    }


    /**
     * Returns the customer reviews.
     *
     * @return array<int, array<string, mixed>> Testimonial items
     */
    protected function reviews() : array
    {
        return [
            ['name' => 'Thomas', 'role' => 'VW Golf, brake service', 'text' => 'Markus showed me the worn pads on the lift and the quote was exactly what I paid. Half of what the dealer wanted.', 'file' => ['id' => $this->cropped( 'review-1', 200, 200 ), 'type' => 'file']],
            ['name' => 'Elena', 'role' => 'Hyundai Kona Electric, battery check', 'text' => 'Finally a garage that knows electric cars. The battery report helped me sell my car for a fair price.', 'file' => ['id' => $this->cropped( 'review-2', 200, 200 ), 'type' => 'file']],
            ['name' => 'Ralf', 'role' => 'Audi A4 Avant, bought used', 'text' => 'Bought my Avant here two years ago. Not one problem since, and they still remind me of every service.', 'file' => ['id' => $this->cropped( 'review-3', 200, 200 ), 'type' => 'file']],
        ];
    }


    /**
     * Returns the service cards element.
     *
     * @return array<string, mixed> Cards content element
     */
    protected function services() : array
    {
        return ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
            'title' => 'Our services',
            'columns' => '3',
            'cards' => [
                ['title' => 'Servicing & HU', 'url' => '/servicing', 'text' => "**From €89** `60 min`\nTo manufacturer plan, warranty-safe, with the inspector on site.", 'file' => ['id' => $this->img( 'oil' ), 'type' => 'file']],
                ['title' => 'Brakes', 'url' => '/brakes-tyres', 'text' => "**From €129** `60 min`\nPads, discs and fluid, measured and shown to you on the lift.", 'file' => ['id' => $this->img( 'brake-disc' ), 'type' => 'file']],
                ['title' => 'Tyres', 'url' => '/brakes-tyres', 'text' => "**From €39** `30 min`\nChange, balancing, alignment and storage in our tyre hotel.", 'file' => ['id' => $this->img( 'tyre-fitting' ), 'type' => 'file']],
                ['title' => 'Diagnostics', 'url' => '/diagnostics', 'text' => "**From €59** `30 min`\nWarning lights, electrical faults and software updates for all makes.", 'file' => ['id' => $this->img( 'diagnostics' ), 'type' => 'file']],
                ['title' => 'Air conditioning', 'url' => '/diagnostics', 'text' => "**From €99** `60 min`\nRefill, leak test and disinfection for clean, cool air.", 'file' => ['id' => $this->img( 'engine-bay' ), 'type' => 'file']],
                ['title' => 'Electric & hybrid', 'url' => '/electric-hybrid', 'text' => "**From €149** `90 min`\nCertified high-voltage technicians and battery health reports.", 'file' => ['id' => $this->img( 'ev-port' ), 'type' => 'file']],
            ],
        ]];
    }


    /**
     * Returns the mechanic card element.
     *
     * @return array<string, mixed> Cards content element
     */
    protected function team() : array
    {
        $people = [
            ['Markus Kraft', 'team-1', "**Master mechanic & co-founder**\nThirty years on all makes. Still the first one at the lift in the morning."],
            ['Sabine Kraft', 'team-2', "**Managing director**\nRuns the service desk and makes sure every quote is clear and binding."],
            ['Deniz Yilmaz', 'team-3', "**High-voltage technician**\nElectric and hybrid cars, battery diagnostics and complex electrical faults."],
            ['Werner Haas', 'team-4', "**Co-founder & used cars**\nChecks every car on our lot himself before it gets a price tag."],
        ];

        return ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
            'title' => 'Meet the crew',
            'columns' => '4',
            'cards' => array_map( fn( $person ) => [
                'title' => $person[0],
                'text' => $person[2],
                'file' => ['id' => $this->cropped( $person[1], 800, 1000 ), 'type' => 'file'],
            ], $people ),
        ]];
    }


    /**
     * Returns the used car listing element.
     *
     * @param string $id Element ID used as anchor
     * @param string $title Section headline
     * @param bool $all Whether to list all cars or only the first three
     * @return array<string, mixed> Pricing content element
     */
    protected function vehicles( string $id, string $title, bool $all ) : array
    {
        $cars = [
            ['VW Golf 1.5 TSI Life', 'golf', 18490, '€219 a month', ['2021', '48,000 km', 'Petrol', 'Manual', '110 kW', '1st owner'], 'Certified'],
            ['Audi A4 Avant 40 TDI', 'audi-wagon', 27900, '€329 a month', ['2020', '72,500 km', 'Diesel', 'Automatic', '150 kW', 'Full history'], null],
            ['Jaguar I-PACE EV400', 'ipace', 34800, '€409 a month', ['2021', '39,000 km', 'Electric', '90 kWh', '294 kW', 'Battery 94 %'], 'Price drop'],
            ['VW Tiguan 2.0 TDI', 'tiguan', 26450, '€309 a month', ['2020', '61,000 km', 'Diesel', 'DSG', '110 kW', 'Tow bar'], null],
            ['Volvo V50 2.0', 'volvo', 6990, '€89 a month', ['2011', '134,000 km', 'Petrol', 'Manual', '107 kW', 'New HU'], 'First car'],
            ['VW Golf 1.0 eTSI', 'golf-white', 21990, '€259 a month', ['2022', '23,000 km', 'Mild hybrid', 'DSG', '81 kW', 'Warranty'], null],
        ];

        $items = array_map( fn( $car ) => array_filter( [
            'name' => $car[0],
            'file' => ['id' => $this->img( $car[1] ), 'type' => 'file'],
            'prices' => [['id' => $car[1], 'amount' => $car[2], 'label' => '€' . number_format( $car[2] )]],
            'text' => 'or ' . $car[3] . ' with financing',
            'features' => implode( "\n", array_map( fn( $spec ) => '- `' . $spec . '`', $car[4] ) ),
            'url' => '/book',
            'button' => 'Book a test drive',
            'badge' => $car[5],
            'highlight' => $car[5] === 'Price drop' ?: null,
        ] ), $all ? $cars : array_slice( $cars, 0, 3 ) );

        return ['id' => $id, 'type' => 'pricing', 'group' => 'main', 'data' => [
            'title' => $title,
            'text' => $all
                ? 'Prices include VAT, a new HU, a fresh service and a 12 month warranty. Monthly rates for 48 months with 20 percent deposit at 4.99 % APR.'
                : 'Every car with a 110-point check, a new HU and a 12 month warranty. Monthly rates for 48 months with 20 percent deposit.',
            'items' => $items,
        ]];
    }
}
