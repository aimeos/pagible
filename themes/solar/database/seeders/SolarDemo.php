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
 * Solar theme demo for the fictional Sonnreich solar, battery and heat pump company.
 */
class SolarDemo extends AbstractDemo
{
    /** @var array<string, string> Meta descriptions keyed by page path */
    protected const DESCRIPTIONS = [
        'about' => 'Meet Sonnreich Energie: engineers and installers in Regensburg who have planned and built more than 1,400 solar, battery and heat pump systems since 2011.',
        'battery-storage' => 'Home battery storage in Regensburg: sized to your consumption, retrofitted to existing solar systems and ready for backup power.',
        'contact' => 'Book a free energy consultation with Sonnreich Energie for solar systems, battery storage, heat pumps and wallboxes in Regensburg.',
        'donaustauf-solar-battery' => 'A family home in Donaustauf with a 12.4 kWp solar system and a 10 kWh battery now covers 71% of its electricity from its own roof.',
        'energy-consulting' => 'Independent energy consulting in Regensburg: consumption analysis, yield forecast, subsidy check and a clear plan for your home.',
        'heat-pumps' => 'Heat pumps for existing homes in Regensburg, combined with solar power, planned room by room and installed with subsidy support.',
        'imprint' => 'Legal notice of Sonnreich Energie GmbH, Regensburg.',
        'privacy' => 'Privacy policy of Sonnreich Energie GmbH, Regensburg.',
        'kelheim-heat-pump' => 'A 1980s house in Kelheim replaced its oil boiler with an air source heat pump that runs largely on solar power from April to September.',
        'neutraubling-bakery' => 'A bakery in Neutraubling covers its ovens and cold rooms with a 99 kWp rooftop solar system and pays for it in under seven years.',
        'projects' => 'Solar systems, battery storage and heat pumps Sonnreich Energie has installed in Regensburg and Eastern Bavaria.',
        'service-maintenance' => 'Maintenance, monitoring and repairs for solar systems, batteries and heat pumps in Regensburg, including systems we did not install.',
        'services' => 'Solar systems, battery storage, heat pumps, wallboxes, energy consulting and maintenance from one Regensburg company.',
        'solar-systems' => 'Rooftop solar systems for homes and businesses in Regensburg, planned with a yield forecast and installed by our own teams.',
        'wallboxes' => 'Wallboxes in Regensburg that charge your electric car with surplus solar power, installed and registered with the grid operator.',
    ];

    /**
     * Curated Unsplash photos used by the solar, battery and heat pump demo.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    protected const PHOTOS = [
        'battery' => ['photo-1780445392417-68b9dccc45f2', 'Battery storage', 'Wall-mounted inverters and battery modules in a utility room'],
        'battery-module' => ['photo-1605191737662-98ba90cb953e', 'Battery module', 'Hand pulling a battery module out of a storage cabinet'],
        'boiler' => ['photo-1594233078955-e1f73a02ebb2', 'Old boiler', 'Old copper coloured boiler mounted on a green wall'],
        'cells' => ['photo-1724041875334-0a6397111c7e', 'Solar modules', 'Close view of black solar modules on a roof'],
        'commercial' => ['photo-1713544123580-12096cc9eb12', 'Commercial solar roof', 'Flat roof of a hall covered with rows of solar modules'],
        'consulting' => ['photo-1581092160562-40aa08e78837', 'Energy consulting', 'Engineer drawing system plans on a desk next to a toolbox'],
        'drill' => ['photo-1668097613572-40b7c11c8727', 'Fixing a module', 'Installer in gloves fixing a solar module with a drill'],
        'ev' => ['photo-1593941707882-a5bba14938c7', 'Charging an electric car', 'Charging cable plugged into the socket of an electric car'],
        'field' => ['photo-1509391366360-2e959784a276', 'Solar modules', 'Rows of solar modules on green grass under a blue sky'],
        'hall-roof' => ['photo-1780342599399-1043179ef39a', 'Hall roof before the installation', 'Bare corrugated metal roof of a hall under a clear sky'],
        'heat-pump' => ['photo-1776860150305-108ed577d7d4', 'Heat pump', 'Outdoor heat pump unit on a lawn next to a brick house'],
        'heat-pump-house' => ['photo-1776860155275-eee24bfb1dee', 'House with heat pump', 'Modern white house with a heat pump unit beside the driveway'],
        'heat-pump-wood' => ['photo-1710829558487-53baf9e26003', 'Heat pump outside', 'Heat pump outdoor unit in front of a wooden facade'],
        'heating-room' => ['photo-1650551182991-b07558247564', 'Boiler room', 'Heating pipes with valves, a circulation pump and pressure gauges'],
        'helmet' => ['photo-1648135327756-b606e2eb8caa', 'Safety first', 'White hard hat lying on blue solar modules'],
        'house' => ['photo-1655300256335-beef51a914fe', 'House with solar roof', 'Family house with solar modules on its tiled roof'],
        'installer' => ['photo-1660330589257-813305a4a383', 'Installer on the roof', 'Installer in a safety harness fitting solar modules on a roof'],
        'installers' => ['photo-1660330589505-9a433a742a7b', 'Installation team', 'Two installers in harnesses mounting a solar module'],
        'inverters' => ['photo-1713544123641-5328f51964e4', 'Inverters', 'Inverters mounted below a ground solar system in the snow'],
        'portrait' => ['photo-1719559519300-e9d2c2bf6de1', 'Our installer', 'Installer in a hard hat carrying a solar module past a new house'],
        'roof' => ['photo-1632759145351-1d592919f522', 'Roof survey', 'Surveyor standing on the roof of a brick house next to a ladder'],
        'roofs' => ['photo-1630608354129-6a7704150401', 'Solar roofs', 'Two houses with solar modules on their gabled roofs'],
        'thermostat' => ['photo-1663602692362-80e4564384c0', 'Room thermostat', 'Hands holding a digital room thermostat showing 19 degrees'],
        'wallbox' => ['photo-1766507680004-1c71007aefe5', 'Wallbox', 'Wallbox with charging cables mounted on a wooden wall'],
        'wallbox-brick' => ['photo-1766507679659-30076abc8c95', 'Wallbox on a house', 'Wallbox with two charging cables on a brick wall'],
    ];

    private string $element;
    private string $logoFile;
    private string $projectsId;


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
            'title' => 'About Sonnreich Energie | Solar and Heat Pumps in Regensburg Since 2011',
            'path' => 'about',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Engineers who climb roofs',
                'subtitle' => 'About Sonnreich Energie',
                'text' => 'Twenty-six people who plan, install and look after energy systems for homes and businesses in Eastern Bavaria.',
                'buttons' => [
                    ['label' => 'Book a free consultation', 'url' => '/contact'],
                    ['label' => 'See our projects', 'url' => '/projects'],
                ],
                'background' => ['id' => $this->img( 'portrait' ), 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'installers' ), 'type' => 'file'],
                'position' => 'grid-start',
                'ratio' => '1-1',
                'text' => "## From one roof to 1,400 systems\n\nLena Hofbauer and Tobias Gruber founded Sonnreich in 2011 after their electrical engineering degrees in Regensburg. They wanted a company that calculates first and sells second. Today our engineers plan every system, and our own electricians and roofers install it, without subcontractors.\n\nWe are a master electrical business, certified heat pump installer and listed with the grid operators of the region. Every system comes with a yield forecast, and we compare it with the real yield after the first year.",
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
            'title' => 'Contact Sonnreich Energie | Solar, Batteries and Heat Pumps in Regensburg',
            'path' => 'contact',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => 'consultation', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Book a free consultation',
                'description' => 'Tell us about your home and add photos of your roof, meter cabinet or boiler room if you can. Your annual electricity consumption from the last bill helps us prepare. We reply within one working day.',
                'inputs' => [
                    ['field' => 'name', 'required' => true, 'input' => 'text'],
                    ['field' => 'telephone', 'required' => true, 'input' => 'text'],
                    ['field' => 'email', 'required' => true, 'input' => 'text'],
                    ['field' => 'Project type', 'required' => true, 'input' => 'select', 'options' => "Solar system\nSolar and battery\nBattery for my solar system\nHeat pump\nWallbox\nService or repair"],
                    ['field' => 'Postcode', 'required' => true, 'input' => 'text'],
                    ['field' => 'Annual consumption in kWh', 'required' => false, 'input' => 'text'],
                ],
                'attachments' => 3,
            ]],
            ['id' => Utils::uid(), 'type' => 'map', 'group' => 'main', 'data' => [
                'title' => 'Our office',
                'text' => "**Sonnreich Energie**\nSonnenstraße 12 · 93053 Regensburg\n\n**Call**\n0941 5893 410 · Monday to Thursday 08:00–17:00, Friday 08:00–13:00\n\n**Email**\ninfo@sonnreich.example\n\nWe work in Regensburg, Neutraubling, Regenstauf, Donaustauf, Kelheim and Straubing.",
                'location' => [
                    'latitude' => 49.0134,
                    'longitude' => 12.1016,
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
            'title' => 'Imprint | Sonnreich Energie',
            'path' => 'imprint',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "# Imprint\n\n**Sonnreich Energie GmbH**\nSonnenstraße 12\n93053 Regensburg\nGermany\n\nTelephone: 0941 5893 410\nEmail: info@sonnreich.example\n\nManaging directors: Lena Hofbauer, Tobias Gruber\nRegister court: Amtsgericht Regensburg, HRB 765432\nVAT ID: DE 987 654 321\n\nProfessional title: Elektrotechnikermeister (awarded in the Federal Republic of Germany)\nCompetent chamber: Handwerkskammer Niederbayern-Oberpfalz, entered in the register of craftsmen\nProfessional regulations: Handwerksordnung (HwO), available at www.gesetze-im-internet.de/hwo\n\nWe are neither willing nor obliged to take part in dispute resolution proceedings before a consumer arbitration board.\n\nThis is a demo website for the Solar theme. Sonnreich Energie is a fictional company.",
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
            'title' => 'Privacy Policy | Sonnreich Energie',
            'path' => 'privacy',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "# Privacy policy\n\n## Who is responsible\n\nSonnreich Energie GmbH, Sonnenstraße 12, 93053 Regensburg, info@sonnreich.example.\n\n## Consultation requests\n\nWhen you send the consultation form, we use your name, phone number, email address, postcode, consumption and the photos you attach only to answer your request and prepare an offer (Art. 6 (1) (b) GDPR). Requests that don't lead to an order are deleted after six months.\n\n## Customers and systems\n\nFor the systems we install, we pass the required data to the grid operator, the market master data register and, if you apply for a subsidy, the KfW. Invoices and contracts are kept for the periods required by tax and commercial law. If you book online monitoring, we receive the operating data of your system to detect faults.\n\n## This website\n\nThe website doesn't use tracking or advertising cookies. Our server stores technical access data such as the IP address for seven days to protect against attacks. The map is loaded from OpenStreetMap only after you open it.\n\n## Your rights\n\nYou have the right to access, rectification, erasure, restriction of processing and data portability, and you can lodge a complaint with the Bavarian State Office for Data Protection Supervision.\n\nThis is a demo website for the Solar theme. Sonnreich Energie is a fictional company.",
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
            'name' => 'Donaustauf solar and battery',
            'title' => 'Solar and Battery for a Family Home in Donaustauf',
            'path' => 'donaustauf-solar-battery',
        ], 'Electricity from the own roof, day and night',
            "The family of four used 5,800 kWh a year and paid more every year. Their east-west roof wasn't ideal for a classic south system, but our yield forecast showed that modules on both sides deliver power from early morning to evening, exactly when the family needs it.\n\nThe 12.4 kWp system and a 10 kWh battery now cover 71% of their consumption. The surplus goes into the grid or charges the electric car, and the system pays for itself in about ten years.",
            'house', ['roof', 'house'],
            [
                ['title' => '12.4 kWp', 'text' => 'Solar modules on the east and west roof'],
                ['title' => '71%', 'text' => 'Of the electricity now comes from the own roof'],
                ['title' => '2 days', 'text' => 'On the roof, from the scaffold to the finished system'],
            ],
            [
                ['label' => 'Week 1', 'title' => 'Consultation and survey', 'text' => 'Consumption analysis, roof survey and a yield forecast for both roof sides.'],
                ['label' => 'Week 3', 'title' => 'Registration', 'text' => 'System registered with the grid operator, who approved the grid connection.'],
                ['label' => 'Week 6', 'title' => 'Installation', 'text' => 'Scaffold, mounting rails and 31 modules in one day, inverter and battery on the next.'],
                ['label' => 'Week 7', 'title' => 'Commissioning', 'text' => 'Meter exchange by the grid operator, entry in the market master data register and a walk through the system.'],
            ],
            ['installer', 'drill', 'battery', 'roofs'],
        );

        $this->project( $projects, [
            'name' => 'Kelheim heat pump',
            'title' => 'From Oil Boiler to Solar-Powered Heat Pump in Kelheim',
            'path' => 'kelheim-heat-pump',
        ], 'No more oil deliveries',
            "The 1980s house used 2,600 litres of heating oil a year, and the boiler was 27 years old. Because the roof already had a solar system, the owners wanted a heat pump that uses as much of their own power as possible.\n\nWe calculated the heat load room by room, replaced two radiators and set the heat pump to heat the hot water cylinder at midday. From April to September, the sun covers most of the heating and hot water, and the energy costs fell by about 40%.",
            'heat-pump-wood', ['boiler', 'heat-pump-wood'],
            [
                ['title' => '−41%', 'text' => 'Energy costs compared with the oil boiler'],
                ['title' => '€14.8k', 'text' => 'Federal KfW grant, approved in 2025'],
                ['title' => '3.6', 'text' => 'Seasonal performance factor in the first year'],
            ],
            [
                ['label' => 'Day 1', 'title' => 'Removal', 'text' => 'Oil boiler and tanks removed, cellar cleaned and space prepared.'],
                ['label' => 'Days 2–3', 'title' => 'Heat pump', 'text' => 'Outdoor unit on its base, hot water cylinder and buffer in the cellar.'],
                ['label' => 'Day 4', 'title' => 'Radiators and balancing', 'text' => 'Two larger radiators fitted and the whole system hydraulically balanced.'],
                ['label' => 'Day 5', 'title' => 'Solar control', 'text' => 'Heat pump connected to the energy manager to use the solar surplus first.'],
            ],
            ['heating-room', 'thermostat', 'heat-pump', 'heat-pump-house'],
        );

        $this->project( $projects, [
            'name' => 'Neutraubling bakery',
            'title' => 'A 99 kWp Solar Roof for a Bakery in Neutraubling',
            'path' => 'neutraubling-bakery',
        ], 'Baking with the sun',
            "Ovens, proofers and cold rooms made electricity the second largest cost of the bakery. Most of it is used in the early morning and during the day, so a solar system on the production hall fits the load profile well.\n\nWe installed 99 kWp on the metal roof without drilling through it, using clamps on the standing seams. The bakery uses 82% of the solar power itself and saves about €24,000 a year, so the system pays for itself in under seven years.",
            'commercial', ['hall-roof', 'commercial'],
            [
                ['title' => '99 kWp', 'text' => 'On the roof of the production hall'],
                ['title' => '82%', 'text' => 'Of the solar power used by the bakery itself'],
                ['title' => '< 7 years', 'text' => 'Until the system has paid for itself'],
            ],
            [
                ['label' => 'Month 1', 'title' => 'Load profile', 'text' => 'Fifteen-minute meter data analysed and the system sized to the bakery.'],
                ['label' => 'Month 2', 'title' => 'Structural check', 'text' => 'Roof load verified by a structural engineer, grid connection approved.'],
                ['label' => 'Month 3', 'title' => 'Installation', 'text' => 'Modules mounted on weekends, so production never stopped.'],
                ['label' => 'Month 4', 'title' => 'Monitoring', 'text' => 'Online monitoring with alerts and a yield report every month.'],
            ],
            ['helmet', 'cells', 'inverters', 'field'],
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
            'title' => 'Solar, Battery and Heat Pump Services in Regensburg | Sonnreich Energie',
            'path' => 'services',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Your energy system from one company',
                'subtitle' => 'Our services',
                'text' => 'From the first consultation to the yearly service. Our engineers plan every system, our own teams install it.',
                'buttons' => [
                    ['label' => 'Book a free consultation', 'url' => '/contact'],
                ],
            ]],
            $this->services(),
            $this->prices(),
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Common questions',
                'items' => [
                    ['title' => 'Is my roof suitable for solar?', 'text' => 'Most roofs facing south, east or west are. We check the orientation, shading and the roof structure on site and calculate the expected yield before you decide.'],
                    ['title' => 'Does a battery pay off?', 'text' => 'It depends on when you use electricity. With high evening consumption, a heat pump or an electric car, a battery usually raises your self-sufficiency from about 35% to 70% or more.'],
                    ['title' => 'Do I pay VAT on a solar system?', 'text' => 'No. Solar systems up to 30 kWp on or near homes, including the battery and installation, are sold with 0% VAT in Germany.'],
                    ['title' => 'Do I need a smart meter?', 'text' => 'New solar systems without a smart meter may only feed 60% of their peak power into the grid. With good self-consumption you lose very little, and we order the smart meter with your registration, so the limit is lifted as soon as it is installed.'],
                    ['title' => 'Do you help with subsidies?', 'text' => 'Yes. We prepare all documents for the federal heat pump subsidy and check regional programmes for batteries and wallboxes before you order.'],
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
            'title' => 'Qualified and certified',
            'layout' => 'badges',
            'cards' => [
                ['title' => 'Electrical contractor', 'text' => "Master craftsman's business, registered with the regional grid operators"],
                ['title' => 'Heat pump expert', 'text' => 'Certified installers for air and ground source systems'],
                ['title' => 'Own teams', 'text' => 'Electricians and roofers employed by us, no subcontractors'],
                ['title' => 'Yield guarantee', 'text' => 'We compare forecast and real yield after the first year'],
                ['title' => '10-year warranty', 'text' => 'On all our installation work'],
            ],
        ]];
    }


    /**
     * Creates the shared Sonnreich footer and returns its ID.
     *
     * @return string Element ID
     */
    protected function element() : string
    {
        return $this->element ??= $this->saveElement( 'cards', 'Sonnreich footer', ['columns' => '4', 'cards' => [
            ['title' => 'Sonnreich Energie', 'text' => "Solar systems, battery storage and heat pumps for homes and businesses in Regensburg and Eastern Bavaria since 2011."],
            ['title' => 'Services', 'text' => "- [Solar systems](/solar-systems)\n- [Battery storage](/battery-storage)\n- [Heat pumps](/heat-pumps)\n- [Wallboxes](/wallboxes)"],
            ['title' => 'Company', 'text' => "- [Our projects](/projects)\n- [About us](/about)\n- [Imprint](/imprint)\n- [Privacy](/privacy)"],
            ['title' => 'Contact', 'text' => "Sonnenstraße 12\n93053 Regensburg\n\n0941 5893 410\n[Book a free consultation](/contact)"],
        ]] );
    }


    /**
     * Returns the ID of the primary solar image.
     *
     * @return string File ID
     */
    protected function file() : string
    {
        return $this->img( 'installer' );
    }


    /**
     * Creates the Sonnreich home page and returns it.
     *
     * @return Page Home page
     */
    protected function home() : Page
    {
        $elementId = $this->element();
        $fileId = $this->file();

        $config = [
            'website' => Validation::entry( 'website', ['title' => 'Sonnreich Energie'], 'config' ),
        ] + $this->logos( $this->logoFile() ) + [
            'solar::business' => [
                'type' => 'solar::business',
                'files' => [],
                'data' => [
                    'name' => 'Sonnreich Energie GmbH',
                    'business-type' => 'HomeAndConstructionBusiness',
                    'street-address' => 'Sonnenstraße 12',
                    'postal-code' => '93053',
                    'locality' => 'Regensburg',
                    'country' => 'DE',
                    'telephone' => '+49 941 5893 410',
                    'email' => 'info@sonnreich.example',
                    'area' => 'Regensburg, Neutraubling, Regenstauf, Donaustauf, Kelheim, Straubing',
                    'price-range' => '€€',
                    'call-button' => true,
                    'hours' => [
                        ['id' => 'mon', 'day' => 'Monday', 'opens' => '08:00', 'closes' => '17:00'],
                        ['id' => 'tue', 'day' => 'Tuesday', 'opens' => '08:00', 'closes' => '17:00'],
                        ['id' => 'wed', 'day' => 'Wednesday', 'opens' => '08:00', 'closes' => '17:00'],
                        ['id' => 'thu', 'day' => 'Thursday', 'opens' => '08:00', 'closes' => '17:00'],
                        ['id' => 'fri', 'day' => 'Friday', 'opens' => '08:00', 'closes' => '13:00'],
                    ],
                ],
            ],
        ];

        $content = [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Make your own power',
                'subtitle' => 'Solar, batteries and heat pumps in Regensburg',
                'text' => 'Independent consulting, a yield forecast you can check and installation by our own teams, from the roof to the meter cabinet.',
                'buttons' => [
                    ['label' => 'Book a free consultation', 'url' => '/contact'],
                    ['label' => 'Our services', 'url' => '/services'],
                ],
                'background' => ['id' => $fileId, 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'layout' => 'figures',
                'cards' => [
                    ['title' => '1,400+', 'text' => 'Systems installed since 2011'],
                    ['title' => '380', 'text' => 'Heat pumps running on solar power'],
                    ['title' => '72%', 'text' => 'Average self-sufficiency with a battery'],
                    ['title' => '4.9/5', 'text' => 'From 386 Google reviews'],
                ],
            ]],
            $this->services(),
            $this->badges(),
            $this->prices(),
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'Your way to your own power',
                'layout' => 'horizontal',
                'items' => [
                    ['label' => 'Step 1', 'title' => 'Consultation', 'text' => 'Your consumption, your plans and a first estimate, free of charge.'],
                    ['label' => 'Step 2', 'title' => 'Survey and offer', 'text' => 'Roof and meter cabinet checked on site, offer with yield forecast.'],
                    ['label' => 'Step 3', 'title' => 'Installation', 'text' => 'Our own teams, usually two days for a home, registration included.'],
                    ['label' => 'Step 4', 'title' => 'Grid connection', 'text' => 'Meter exchange, app setup and a yield check after the first year.'],
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
                'title' => 'Trusted across Eastern Bavaria',
                'items' => $this->reviews(),
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'How much could your roof produce?',
                'text' => 'Send us your address and your annual consumption. You get a free first estimate of the yield, the costs and the payback time within two working days.',
                'buttons' => [
                    ['label' => 'Get a free estimate', 'url' => '/contact'],
                    ['label' => 'Call 0941 5893 410', 'url' => 'tel:+499415893410'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        $meta = [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => 'Sonnreich Energie plans and installs solar systems, battery storage, heat pumps and wallboxes in Regensburg, with independent consulting and a yield forecast.',
                'keywords' => 'solar Regensburg, photovoltaics, solar installer, battery storage, heat pump installation, wallbox, energy consulting',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => 'Sonnreich Energie | Solar, Batteries and Heat Pumps in Regensburg',
                'description' => 'Independent consulting and installation by our own teams, from the roof to the meter cabinet.',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        return $this->saveRoot( 'Sonnreich Energie | Solar, Batteries and Heat Pumps in Regensburg', $config, $meta, $content, $elementId, $fileId );
    }


    /**
     * Creates the Sonnreich SVG logo and returns its file ID.
     *
     * @return string File ID
     */
    protected function logoFile() : string
    {
        if( !isset( $this->logoFile ) )
        {
            $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 80" role="img" aria-labelledby="title desc">
  <title id="title">Sonnreich logo</title>
  <desc id="desc">Leaf green rounded square with a sun yellow sun rising above a white solar module beside the Sonnreich wordmark</desc>
  <rect x="4" y="8" width="64" height="64" rx="12" fill="#1F7A4D"/>
  <circle cx="36" cy="34" r="9" fill="#F5B82E"/>
  <path d="M36 15v5M22 34h-5M50 34h5M26 24l-3.5-3.5M46 24l3.5-3.5" stroke="#F5B82E" stroke-width="4" stroke-linecap="round"/>
  <path d="M16 62l6-16h28l6 16z" fill="#FFFFFF"/>
  <path d="M34 46l-2 16M38 46l2 16M19 54h34" stroke="#1F7A4D" stroke-width="2.5"/>
  <text x="84" y="54" fill="#FFFFFF" font-family="'Avenir Next', Avenir, 'Century Gothic', Futura, Montserrat, system-ui, sans-serif" font-size="36" font-weight="700" letter-spacing="0.5">Sonnreich</text>
</svg>
SVG;

            $this->logoFile = $this->svgFile(
                $svg,
                'sonnreich-logo.svg',
                'Sonnreich logo',
                'Leaf green rounded square with a sun yellow sun rising above a white solar module beside the Sonnreich wordmark',
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
            [['name' => 'Solar systems', 'title' => 'Rooftop Solar Systems in Regensburg | Sonnreich Energie', 'path' => 'solar-systems'],
                'Turn your roof into a power plant', 'roofs', 'installers',
                "## Planned for your roof and your consumption\n\nA good solar system isn't the largest one that fits, but the one that matches your roof, your consumption and your plans. We measure the roof, check the shading over the year and calculate the yield and payback time before you decide.\n\nOur own teams mount the modules, connect the inverter and register the system with the grid operator. On most homes we finish in two days, and the system feeds into the grid as soon as the grid operator has exchanged the meter.",
                [
                    ['title' => 'Yield forecast', 'text' => 'Shading analysis and a yield forecast we check after the first year.'],
                    ['title' => 'Quality modules', 'text' => 'Glass-glass modules with 30 years of performance warranty.'],
                    ['title' => 'Registration', 'text' => 'Grid operator and market master data register handled by us.'],
                ]],
            [['name' => 'Battery storage', 'title' => 'Home Battery Storage in Regensburg | Sonnreich Energie', 'path' => 'battery-storage'],
                'Use your solar power after sunset', 'battery', 'battery-module',
                "## Sized to your evenings\n\nA battery stores the midday surplus for the evening and the night. We size it to your real consumption, because a battery that is too large rarely gets full and costs more than it saves.\n\nWe retrofit batteries to existing solar systems of all common makers and, if you like, add a backup power function that keeps important circuits running during a power cut.",
                [
                    ['title' => 'Right size', 'text' => 'Capacity calculated from your load profile, not from a price list.'],
                    ['title' => 'Retrofit', 'text' => 'Batteries for existing solar systems, also those we did not install.'],
                    ['title' => 'Backup power', 'text' => 'Fridge, heating and internet keep running during a power cut.'],
                ]],
            [['name' => 'Heat pumps', 'title' => 'Heat Pumps with Solar Power in Regensburg | Sonnreich Energie', 'path' => 'heat-pumps'],
                'Heat with the sun and the air', 'heat-pump-house', 'heat-pump',
                "## Planned for existing homes\n\nA heat pump runs on electricity, so it pairs well with a solar system. We calculate the heat load of every room, check your radiators and choose a unit that runs quietly and efficiently at low flow temperatures.\n\nAn energy manager runs the heat pump when the sun shines, for example to heat the hot water at midday. We prepare all documents for the federal subsidy and remove the old boiler.",
                [
                    ['title' => 'Heat load calculation', 'text' => 'Room by room, so only the radiators that are really too small get replaced.'],
                    ['title' => 'Subsidy support', 'text' => 'All confirmations for your grant application, prepared by us.'],
                    ['title' => 'Solar control', 'text' => 'The heat pump uses your solar surplus before it buys from the grid.'],
                ]],
            [['name' => 'Wallboxes', 'title' => 'Wallboxes and Solar Charging in Regensburg | Sonnreich Energie', 'path' => 'wallboxes'],
                'Drive on sunshine', 'wallbox-brick', 'ev',
                "## Charge with your own power\n\nA wallbox charges your electric car faster and safer than a household socket. Connected to your solar system, it only uses the surplus the house doesn't need, or charges at full power when you are in a hurry.\n\nWe check your meter cabinet, install the wallbox with its own circuit and register it with the grid operator, so you can also use the reduced grid fee for controllable devices.",
                [
                    ['title' => 'Surplus charging', 'text' => 'The car charges with the power your roof produces right now.'],
                    ['title' => 'Safe installation', 'text' => 'Own circuit, residual current protection and a test protocol.'],
                    ['title' => 'Lower grid fee', 'text' => 'Registered as a controllable device for a reduced grid fee.'],
                ]],
            [['name' => 'Energy consulting', 'title' => 'Independent Energy Consulting in Regensburg | Sonnreich Energie', 'path' => 'energy-consulting'],
                'A plan before a purchase', 'consulting', 'roof',
                "## Numbers you can check\n\nSolar, battery, heat pump or all three? We start with your consumption, your house and your plans for the next years and show you which step brings the most for your money.\n\nYou get a written report with yields, costs, subsidies and payback times, based on stated assumptions. If you later order from us, the consulting fee is credited.",
                [
                    ['title' => 'Consumption analysis', 'text' => 'Your bills and meter data turned into a clear load profile.'],
                    ['title' => 'Site visit', 'text' => 'Roof, meter cabinet and boiler room checked by an engineer.'],
                    ['title' => 'Written report', 'text' => 'Options compared with costs, subsidies and payback times.'],
                ]],
            [['name' => 'Service and maintenance', 'title' => 'Solar and Heat Pump Maintenance in Regensburg | Sonnreich Energie', 'path' => 'service-maintenance'],
                'Keep your system at full power', 'helmet', 'inverters',
                "## Monitoring, service and repairs\n\nA solar system that loses yield unnoticed costs you money every day. We watch your system online, get an alert when the yield drops and send a technician before you notice the difference on your bill.\n\nWe also service heat pumps and batteries and repair systems of all common makers, including those installed by other companies.",
                [
                    ['title' => 'Online monitoring', 'text' => 'Alerts on faults and a yield report every month.'],
                    ['title' => 'Inspection', 'text' => 'Electrical test, module check and thermal imaging every four years.'],
                    ['title' => 'Fault service', 'text' => 'Heat pump and battery faults answered on the same working day.'],
                ]],
        ];
    }


    /**
     * Creates a Solar demo page below the given parent and returns it.
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

        return $this->savePage( $data, $content, $parent, $elementId, $fileId, $footer, 'Sonnreich Energie, solar Regensburg, photovoltaics, battery storage, heat pump, wallbox, energy consulting' );
    }


    /**
     * Builds the Solar demo page tree.
     */
    protected function pages() : void
    {
        $this->projectsId = (string) Str::uuid7();
        $home = $this->home();

        $this->addServices( $home )
            ->addProjects( $home )
            ->addAbout( $home )
            ->addContact( $home )
            ->addImprint( $home )
            ->addPrivacy( $home );
    }


    /**
     * Returns the package price list element.
     *
     * @return array<string, mixed> Pricing content element
     */
    protected function prices() : array
    {
        return ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
            'title' => 'Package prices',
            'text' => 'Prices for a typical family home including installation, registration and 0% VAT. You get a fixed offer after the site survey.',
            'items' => [
                [
                    'name' => 'Solar 8 kWp',
                    'prices' => [['id' => 'solar', 'amount' => 10900, 'label' => '€10,900']],
                    'text' => 'Rooftop system for homes with up to 4,000 kWh consumption.',
                    'features' => "- 20 glass-glass modules\n- Hybrid inverter, ready for a battery\n- Monitoring app",
                    'url' => '/solar-systems',
                    'button' => 'Solar systems',
                ],
                [
                    'name' => 'Solar 10 kWp + battery',
                    'prices' => [['id' => 'battery', 'amount' => 19900, 'label' => '€19,900']],
                    'text' => 'Solar system with a 10 kWh battery for about 70% self-sufficiency.',
                    'features' => "- 25 glass-glass modules\n- 10 kWh battery with backup power\n- Energy manager included",
                    'url' => '/battery-storage',
                    'button' => 'Battery storage',
                    'highlight' => true,
                    'badge' => 'Most chosen',
                ],
                [
                    'name' => 'Energy check',
                    'prices' => [['id' => 'check', 'amount' => 249, 'label' => '€249']],
                    'text' => 'Site visit with consumption analysis and a written report.',
                    'features' => "- Roof and meter cabinet checked\n- Subsidies calculated\n- Credited on your order",
                    'url' => '/energy-consulting',
                    'button' => 'Energy consulting',
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
     * @param array<int, array<string, string>> $steps Project steps
     * @param array<int, string> $photos PHOTOS keys of the slideshow images
     * @return Page Created page
     */
    protected function project( Page $parent, array $data, string $title, string $text, string $cover,
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
                'title' => 'On site',
                'files' => array_map( fn( $key ) => ['id' => $this->cropped( $key, 1500, 1000 ), 'type' => 'file'], $photos ),
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Planning something similar?',
                'text' => 'Tell us about your home and you get a free first estimate within two working days.',
                'buttons' => [
                    ['label' => 'Book a free consultation', 'url' => '/contact'],
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
            'title' => 'Our Projects | Sonnreich Energie',
            'path' => 'projects',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Recent projects',
                'subtitle' => 'Our work',
                'text' => 'Solar roofs, batteries and heat pumps in Regensburg and Eastern Bavaria, with the numbers behind each project.',
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
     * Returns the customer reviews.
     *
     * @return array<int, array<string, string>> Testimonial items
     */
    protected function reviews() : array
    {
        return [
            ['name' => 'Julia and Stefan R.', 'role' => 'Solar and battery, Donaustauf', 'text' => 'Two other companies told us our east-west roof was a problem. Sonnreich showed us the numbers, and after the first year the real yield was 4% above the forecast.'],
            ['name' => 'Bernhard W.', 'role' => 'Heat pump, Kelheim', 'text' => 'No more oil deliveries and a heat pump that runs mostly on our own power from spring to autumn. They even handled the subsidy, I only had to sign.'],
            ['name' => 'Bäckerei Maier', 'role' => '99 kWp solar roof, Neutraubling', 'text' => 'The team worked on weekends, so our production never stopped for an hour. Our electricity bill has dropped by a third since.'],
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
                    ['label' => 'Book a free consultation', 'url' => '/contact'],
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
                'title' => 'Book a free consultation',
                'text' => 'Tell us about your home and you get a free first estimate within two working days.',
                'buttons' => [
                    ['label' => 'Book a free consultation', 'url' => '/contact'],
                    ['label' => 'Call 0941 5893 410', 'url' => 'tel:+499415893410'],
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
                ['title' => 'Solar systems', 'text' => 'Rooftop systems with a yield forecast, installed by our own teams.', 'url' => '/solar-systems', 'file' => ['id' => $this->img( 'roofs' ), 'type' => 'file']],
                ['title' => 'Battery storage', 'text' => 'Solar power for the evening, sized to your consumption.', 'url' => '/battery-storage', 'file' => ['id' => $this->img( 'battery' ), 'type' => 'file']],
                ['title' => 'Heat pumps', 'text' => 'Heating that runs on your own solar power, with subsidy support.', 'url' => '/heat-pumps', 'file' => ['id' => $this->img( 'heat-pump' ), 'type' => 'file']],
                ['title' => 'Wallboxes', 'text' => 'Charge your electric car with surplus power from your roof.', 'url' => '/wallboxes', 'file' => ['id' => $this->img( 'wallbox' ), 'type' => 'file']],
                ['title' => 'Energy consulting', 'text' => 'Independent analysis and a clear plan before you buy anything.', 'url' => '/energy-consulting', 'file' => ['id' => $this->img( 'consulting' ), 'type' => 'file']],
                ['title' => 'Service and maintenance', 'text' => 'Monitoring, inspection and repairs for systems of all makers.', 'url' => '/service-maintenance', 'file' => ['id' => $this->img( 'helmet' ), 'type' => 'file']],
            ],
        ]];
    }
}
