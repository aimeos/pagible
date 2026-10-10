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
 * Flow theme demo for the fictional Brenner & Quell plumbing and heating company.
 */
class FlowDemo extends AbstractDemo
{
    /** @var array<string, string> Meta descriptions keyed by page path */
    protected const DESCRIPTIONS = [
        'about' => 'Meet Brenner & Quell: a master plumbing and heating business in Freiburg, run by the second generation with 18 employees and a 24/7 emergency service.',
        'bathrooms' => 'Bathroom renovation in Freiburg from one hand: planning, plumbing, tiling and electrics, including level-access showers for every age.',
        'contact' => 'Ask Brenner & Quell for a fixed price on heat pumps, heating service, bathrooms, drinking water or gas installations in Freiburg.',
        'drinking-water' => 'Drinking water installations in Freiburg: new pipes, legionella testing for landlords, water softeners and filters by a licensed company.',
        'emergency-service' => '24/7 plumbing and heating emergency service in Freiburg for burst pipes, heating failures, blocked drains and water leaks.',
        'gas-installations' => 'Gas pipe checks, gas boiler service and safe gas installations in Freiburg by a company registered with the local network operator.',
        'heat-pumps' => 'Heat pumps for existing homes in Freiburg: heat load calculation, subsidy application and installation by certified heating engineers.',
        'heating-service' => 'Annual heating service and boiler repairs in Freiburg for gas, oil and heat pump systems, with a fixed price and a service report.',
        'herdern-heat-pump' => 'A 1970s house in Freiburg-Herdern switched from an old gas boiler to an air source heat pump in five days, keeping most of the original radiators.',
        'imprint' => 'Legal notice of Brenner & Quell Haustechnik GmbH, Freiburg im Breisgau.',
        'privacy' => 'Privacy policy of Brenner & Quell Haustechnik GmbH, Freiburg im Breisgau.',
        'emmendingen-water-pipes' => 'New drinking water pipes for an apartment building with 18 flats in Emmendingen, installed floor by floor while the tenants stayed.',
        'projects' => 'Heat pumps, bathrooms and drinking water installations Brenner & Quell has completed in Freiburg and the surrounding area.',
        'services' => 'Heat pumps, heating service, bathrooms, drinking water, gas installations and a 24/7 emergency service from one Freiburg company.',
        'wiehre-bathroom' => 'A dated bathroom in Freiburg-Wiehre turned into a level-access bathroom with a walk-in shower in fourteen working days.',
    ];

    /**
     * Curated Unsplash photos used by the plumbing and heating demo.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    protected const PHOTOS = [
        'bath-new' => ['photo-1584622650111-993a426fbf0a', 'Renovated bathroom', 'Modern bathroom with a glass walk-in shower, wide vanity and large mirror'],
        'bath-old' => ['photo-1552321554-5fefe8c9ef14', 'Bathroom before the renovation', 'Small dated bathroom with a plain basin and bare white walls'],
        'basin' => ['photo-1576698483491-8c43f0862543', 'Wash basin', 'Ceramic vessel basin with a brass tap in front of a marble wall'],
        'boiler' => ['photo-1594233078955-e1f73a02ebb2', 'Old boiler', 'Old copper coloured water heater mounted on a green wall'],
        'heating-room' => ['photo-1650551182991-b07558247564', 'Heating room', 'Heating pipes with valves, a circulation pump and pressure gauges'],
        'heat-pump' => ['photo-1776860150305-108ed577d7d4', 'Heat pump', 'Outdoor heat pump unit on a lawn next to a brick house'],
        'heat-pump-house' => ['photo-1776860155275-eee24bfb1dee', 'House with heat pump', 'Modern white house with a heat pump unit beside the driveway'],
        'manifold' => ['photo-1650551182956-47efa0f90b64', 'Heating valves', 'Close view of heating valves, a pressure gauge and a pump'],
        'meter' => ['photo-1658758904121-ee49fc5e205c', 'Gas meter', 'Gas meter below insulated heating and water pipes'],
        'pipes' => ['photo-1538474705339-e87de81450e8', 'Old pipes', 'Rows of old grey pipes with bends and flanges along a wall'],
        'plumber' => ['photo-1676210134188-4c05dd172f89', 'Plumber at work', 'Plumber in a red shirt fitting the waste pipe under a sink'],
        'portrait' => ['photo-1749532125405-70950966b0e5', 'Our plumber', 'Plumber in work trousers repairing the plumbing of a bathroom'],
        'radiator' => ['photo-1669725341213-7379ff6c90d5', 'Radiator', 'White panel radiator below a window in a bright room'],
        'shower' => ['photo-1613849925362-38fb4c16ff36', 'Walk-in shower', 'Marble walk-in shower with a rain shower head and black fittings'],
        'siphon' => ['photo-1676210133055-eab6ef033ce3', 'Fitting a sink', 'Plumber connecting the siphon below a wash basin'],
        'tap' => ['photo-1521207418485-99c705420785', 'Running water', 'Clear water running from a kitchen tap'],
        'thermostat' => ['photo-1663602692362-80e4564384c0', 'Room thermostat', 'Hands holding a digital room thermostat showing 19 degrees'],
        'toilet' => ['photo-1676210134050-6f12c6898395', 'Wall-hung toilet', 'Plumber in red gloves fitting a wall-hung toilet'],
        'water-heater' => ['photo-1620653713380-7a34b773fef8', 'Water heater', 'Plumber tightening a pipe on a hot water cylinder with pliers'],
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
            'title' => 'About Brenner & Quell | Plumbing and Heating in Freiburg Since 1987',
            'path' => 'about',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Master craft, second generation',
                'subtitle' => 'About Brenner & Quell',
                'text' => 'Eighteen people, nine service vans and one promise: a clean site, a fixed price and a heating system that runs for decades.',
                'buttons' => [
                    ['label' => 'Get a fixed price', 'url' => '/contact'],
                    ['label' => 'See our projects', 'url' => '/projects'],
                ],
                'background' => ['id' => $this->img( 'portrait' ), 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'heating-room' ), 'type' => 'file'],
                'position' => 'grid-start',
                'ratio' => '1-1',
                'text' => "## From the boiler room to the heat pump\n\nWerner Brenner and Josef Quell opened their workshop in Freiburg in 1987 and installed gas boilers all over the Breisgau. Today Katrin Brenner, master plumber and heating engineer, runs the company with a team of twelve installers, three apprentices and our own customer service.\n\nWe are a master craftsman business, a member of the SHK guild and a registered installer with bnNETZE, the local network operator, for gas and drinking water work. Every installation is documented, and you get the service report by email on the same day.",
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
            'title' => 'Contact Brenner & Quell | Plumbing and Heating in Freiburg',
            'path' => 'contact',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => 'quote', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Get a fixed price',
                'description' => 'Tell us what you need and add photos of your boiler, its type plate or the bathroom if you can. We reply within one working day. For emergencies, please call 0761 4587 299.',
                'inputs' => [
                    ['field' => 'name', 'required' => true, 'input' => 'text'],
                    ['field' => 'telephone', 'required' => true, 'input' => 'text'],
                    ['field' => 'email', 'required' => true, 'input' => 'text'],
                    ['field' => 'Job type', 'required' => true, 'input' => 'select', 'options' => "Heat pump\nHeating service or repair\nNew bathroom\nDrinking water\nGas installation\nSomething else"],
                    ['field' => 'Postcode', 'required' => true, 'input' => 'text'],
                ],
                'attachments' => 3,
            ]],
            ['id' => Utils::uid(), 'type' => 'map', 'group' => 'main', 'data' => [
                'title' => 'Our workshop',
                'text' => "**Brenner & Quell Haustechnik**\nQuellenweg 8 · 79108 Freiburg im Breisgau\n\n**Call**\n0761 4587 210 · Monday to Thursday 07:30–17:00, Friday 07:30–14:00\n\n**Emergencies**\n0761 4587 299 · 24 hours, every day\n\n**Email**\ninfo@brenner-quell.example\n\nWe work in Freiburg, Emmendingen, Waldkirch, Kirchzarten, Breisach and Bad Krozingen.",
                'location' => [
                    'latitude' => 48.0306,
                    'longitude' => 7.8285,
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
            'title' => 'Imprint | Brenner & Quell Haustechnik',
            'path' => 'imprint',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "# Imprint\n\n**Brenner & Quell Haustechnik GmbH**\nQuellenweg 8\n79108 Freiburg im Breisgau\nGermany\n\nTelephone: 0761 4587 210\nEmail: info@brenner-quell.example\n\nManaging director: Katrin Brenner\nRegister court: Amtsgericht Freiburg, HRB 712345\nVAT ID: DE 123 456 789\n\nMaster craftsman business in the plumbing, heating and air conditioning trade, registered in the trades register of the Chamber of Crafts Freiburg.\n\nProfessional title: Installateur- und Heizungsbauermeister (awarded in Germany)\nChamber: Handwerkskammer Freiburg, Bismarckallee 6, 79098 Freiburg im Breisgau\nProfessional rules: Handwerksordnung (www.gesetze-im-internet.de/hwo)\n\nConsumer dispute resolution: We are not willing or obliged to take part in dispute resolution proceedings before a consumer arbitration board.\n\nThis is a demo website for the Flow theme. Brenner & Quell is a fictional company.",
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
            'title' => 'Privacy Policy | Brenner & Quell Haustechnik',
            'path' => 'privacy',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "# Privacy policy\n\n## Who is responsible\n\nBrenner & Quell Haustechnik GmbH, managing director Katrin Brenner, Quellenweg 8, 79108 Freiburg im Breisgau, info@brenner-quell.example.\n\n## Quote requests\n\nWhen you send the quote form, we use your name, phone number, email address, postcode, job type, message and the photos you attach only to prepare your offer and plan the job (Art. 6 (1) (b) GDPR). Please don't upload photos that show people or documents we don't need. Requests that don't lead to an order are deleted together with the photos after six months.\n\n## Orders\n\nFor orders, we keep offers, invoices and service reports for the periods required by commercial and tax law (up to ten years). We share your data with manufacturers, the network operator, the chimney sweep or the KfW only as far as necessary for your job, warranty claims or your grant.\n\n## This website\n\nThe website doesn't use tracking or advertising cookies. Our server stores technical access data such as the IP address for seven days to protect against attacks. The map is loaded from OpenStreetMap only after you open it.\n\n## Your rights\n\nYou have the right to access, rectification, erasure, restriction of processing, objection and data portability, and you can lodge a complaint with the data protection authority of Baden-Württemberg (Landesbeauftragter für den Datenschutz und die Informationsfreiheit Baden-Württemberg).\n\nThis is a demo website for the Flow theme. Brenner & Quell is a fictional company.",
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
            'name' => 'Herdern heat pump',
            'title' => 'From Gas Boiler to Heat Pump in Freiburg-Herdern',
            'path' => 'herdern-heat-pump',
        ], 'Warm radiators without gas',
            "The 1970s house still had its original radiators and a gas boiler that was 24 years old. Many installers wanted to replace every radiator first. Our room-by-room heat load calculation showed that only three of them were too small, so the family kept the others and saved almost €9,000.\n\nThe new air source heat pump runs at a flow temperature of 48 °C on the coldest days. Together with the hydraulic balancing and a heat pump electricity tariff, the heating costs fell by around a third in the first winter. The KfW grant was approved in 2025; under the rules in force since July 2026 it would be lower.",
            'heat-pump-house', ['boiler', 'heat-pump'],
            [
                ['title' => '5', 'text' => 'Days from the old boiler to warm radiators'],
                ['title' => '€16.4k', 'text' => 'Federal KfW grant, approved in 2025'],
                ['title' => '−33%', 'text' => 'Heating costs in the first winter'],
            ],
            [
                ['label' => 'Day 1', 'title' => 'Removal', 'text' => 'Gas boiler and chimney connection removed, gas pipe sealed by our gas fitters.'],
                ['label' => 'Days 2–3', 'title' => 'Heat pump and cylinder', 'text' => 'Outdoor unit on its base, buffer and hot water cylinder in the old boiler room.'],
                ['label' => 'Day 4', 'title' => 'Radiators', 'text' => 'Three larger radiators fitted and new thermostatic valves on all others.'],
                ['label' => 'Day 5', 'title' => 'Balancing and handover', 'text' => 'Hydraulic balancing, app setup and a walk through the controls with the owners.'],
            ],
            ['heating-room', 'manifold', 'thermostat', 'heat-pump'],
        );

        $this->project( $projects, [
            'name' => 'Wiehre bathroom',
            'title' => 'Level-Access Bathroom in Freiburg-Wiehre',
            'path' => 'wiehre-bathroom',
        ], 'A bathroom for the next thirty years',
            "The owners wanted to stay in their flat as long as possible, but the high bathtub was becoming a daily obstacle. We replaced it with a walk-in shower without a step, a wall-hung toilet at a comfortable height and a basin you can use while sitting. As the old building has timber beam floors, we fitted a shallow floor drain and raised the bathroom floor slightly, so there is still no step at the door.\n\nPlumbers, tilers and an electrician worked to one schedule, coordinated by a single site manager. The owners had one contact, one fixed price and a finished bathroom after fourteen working days.",
            'shower', ['bath-old', 'bath-new'],
            [
                ['title' => '14', 'text' => 'Working days from removal to the last silicone joint'],
                ['title' => '0 cm', 'text' => 'Step into the new shower'],
                ['title' => '1', 'text' => 'Contact for all trades'],
            ],
            [
                ['label' => 'Days 1–2', 'title' => 'Strip out', 'text' => 'Old tiles, bathtub and pipes removed, rooms next door protected from dust.'],
                ['label' => 'Days 3–5', 'title' => 'Pipes and drains', 'text' => 'New water pipes, a floor drain for the shower and the frame for the toilet.'],
                ['label' => 'Days 6–12', 'title' => 'Sealing and tiles', 'text' => 'Waterproofing with drying time, large format tiles and the glass shower screen.'],
                ['label' => 'Days 13–14', 'title' => 'Fittings', 'text' => 'Basin, toilet, thermostatic shower, lighting and a final pressure test.'],
            ],
            ['toilet', 'siphon', 'basin', 'shower'],
        );

        $this->project( $projects, [
            'name' => 'Emmendingen water pipes',
            'title' => 'New Drinking Water Pipes for 18 Flats in Emmendingen',
            'path' => 'emmendingen-water-pipes',
        ], 'Clean water on every floor',
            "The legionella test in the apartment building came back above the technical action value of 100 CFU per 100 ml, so the health office required a risk analysis. On top of that, the galvanised steel pipes from 1968 were clogged with rust. We replaced the risers and the pipes in every flat with stainless steel, insulated them and installed a new hot water cylinder with a circulation pump.\n\nThe work ran floor by floor, so every tenant was without water for a single day only. The follow-up test after four weeks showed no legionella in any sample.",
            'tap', ['pipes', 'heating-room'],
            [
                ['title' => '18', 'text' => 'Flats with new pipes'],
                ['title' => '1 day', 'text' => 'Without water for each tenant'],
                ['title' => '0', 'text' => 'Legionella found in the follow-up test'],
            ],
            [
                ['label' => 'Week 1', 'title' => 'Survey and plan', 'text' => 'Pipe routes, hot water demand and a schedule agreed with the property manager.'],
                ['label' => 'Weeks 2–4', 'title' => 'Risers', 'text' => 'New stainless steel risers in the shafts with a temporary supply for the tenants.'],
                ['label' => 'Weeks 5–8', 'title' => 'Flats', 'text' => 'Kitchens and bathrooms connected floor by floor, one day per flat.'],
                ['label' => 'Week 9', 'title' => 'Flushing and testing', 'text' => 'Pipes flushed, hot water cylinder commissioned and samples taken by the lab.'],
            ],
            ['water-heater', 'meter', 'tap', 'manifold'],
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
            'title' => 'Plumbing and Heating Services in Freiburg | Brenner & Quell',
            'path' => 'services',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Heating, water and gas from one company',
                'subtitle' => 'Our services',
                'text' => 'From a dripping tap to a complete heat pump system. Master craftsmen, fixed prices and a service report for every job.',
                'buttons' => [
                    ['label' => 'Get a fixed price', 'url' => '/contact'],
                ],
            ]],
            $this->services(),
            $this->prices(),
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Common questions',
                'items' => [
                    ['title' => 'Does a heat pump work with my radiators?', 'text' => 'In most houses, yes. We calculate the heat load of every room and check each radiator. Usually only a few need to be replaced, and underfloor heating is not required.'],
                    ['title' => 'Do you help with the subsidy application?', 'text' => 'Yes. We prepare the confirmation you need for the KfW grant and sign the contract with a condition, so you only order once the grant is approved. Since July 2026 the grant is 30% plus a climate speed bonus of 16% and an income bonus of up to 40%, on up to €28,000 of costs. The City of Freiburg adds its own "Klimafreundlich Wohnen" programme on top.'],
                    ['title' => 'Can I still install a new gas boiler?', 'text' => 'Yes. The Building Modernisation Act passed in July 2026 dropped the rule that new heating must use 65% renewable energy. A gas boiler gets no KfW grant, though, and rising CO₂ prices make gas more expensive every year. We calculate both options for your house.'],
                    ['title' => 'Will district heating come to my street?', 'text' => 'Freiburg plans to cover about half of its heat demand with district heating. Check the city\'s heat network map before you decide. If your street isn\'t planned for the next years, a heat pump is usually the better choice.'],
                    ['title' => 'How often should my heating be serviced?', 'text' => 'Gas and oil heating once a year, heat pumps every one to two years. Regular service keeps the manufacturer warranty valid and the system efficient. With our maintenance contract, we book the date for you and you get priority in our emergency service.'],
                    ['title' => 'What should I do if I smell gas?', 'text' => 'Don\'t use light switches or phones in the building, open the windows, close the gas valve and leave the house. Call the bnNETZE gas emergency number 0800 2 767 767 from outside, then call us.'],
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
            'title' => 'Qualified and registered',
            'layout' => 'badges',
            'cards' => [
                ['title' => 'Meisterbetrieb', 'text' => 'Master craftsman business in plumbing and heating'],
                ['title' => 'SHK guild', 'text' => 'Member of the local plumbing and heating guild'],
                ['title' => 'Gas & water', 'text' => 'Registered installer with bnNETZE for gas and drinking water'],
                ['title' => 'Heat pump expert', 'text' => 'F-gas certified (category I) for split heat pumps'],
                ['title' => 'Maintenance contract', 'text' => 'Yearly service at a fixed date and priority in our emergency service'],
            ],
        ]];
    }


    /**
     * Creates the shared Brenner & Quell footer and returns its ID.
     *
     * @return string Element ID
     */
    protected function element() : string
    {
        return $this->element ??= $this->saveElement( 'cards', 'Brenner & Quell footer', ['columns' => '4', 'cards' => [
            ['title' => 'Brenner & Quell', 'text' => "Master plumbers and heating engineers for homes and landlords in Freiburg and the Breisgau since 1987."],
            ['title' => 'Services', 'text' => "- [Heat pumps](/heat-pumps)\n- [Heating service](/heating-service)\n- [Bathrooms](/bathrooms)\n- [Drinking water](/drinking-water)"],
            ['title' => 'Company', 'text' => "- [Our projects](/projects)\n- [About us](/about)\n- [Imprint](/imprint)\n- [Privacy](/privacy)"],
            ['title' => 'Contact', 'text' => "Quellenweg 8\n79108 Freiburg im Breisgau\n\n0761 4587 210\nEmergencies 0761 4587 299\n[Get a fixed price](/contact)"],
        ]] );
    }


    /**
     * Returns the ID of the primary plumber image.
     *
     * @return string File ID
     */
    protected function file() : string
    {
        return $this->img( 'plumber' );
    }


    /**
     * Creates the Brenner & Quell home page and returns it.
     *
     * @return Page Home page
     */
    protected function home() : Page
    {
        $elementId = $this->element();
        $fileId = $this->file();

        $config = [
            'website' => Validation::entry( 'website', ['title' => 'Brenner & Quell'], 'config' ),
        ] + $this->logos( $this->logoFile() ) + [
            'flow::business' => [
                'type' => 'flow::business',
                'files' => [],
                'data' => [
                    'name' => 'Brenner & Quell Haustechnik GmbH',
                    'business-type' => 'Plumber',
                    'street-address' => 'Quellenweg 8',
                    'postal-code' => '79108',
                    'locality' => 'Freiburg im Breisgau',
                    'country' => 'DE',
                    'telephone' => '+49 761 4587 210',
                    'emergency-phone' => '+49 761 4587 299',
                    'emergency' => true,
                    'email' => 'info@brenner-quell.example',
                    'area' => 'Freiburg im Breisgau, Emmendingen, Waldkirch, Kirchzarten, Breisach, Bad Krozingen',
                    'price-range' => '€€',
                    'call-button' => true,
                    'hours' => [
                        ['id' => 'mon', 'day' => 'Monday', 'opens' => '07:30', 'closes' => '17:00'],
                        ['id' => 'tue', 'day' => 'Tuesday', 'opens' => '07:30', 'closes' => '17:00'],
                        ['id' => 'wed', 'day' => 'Wednesday', 'opens' => '07:30', 'closes' => '17:00'],
                        ['id' => 'thu', 'day' => 'Thursday', 'opens' => '07:30', 'closes' => '17:00'],
                        ['id' => 'fri', 'day' => 'Friday', 'opens' => '07:30', 'closes' => '14:00'],
                    ],
                ],
            ],
        ];

        $content = [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Warm rooms, clean water',
                'subtitle' => 'Master plumbers and heating engineers in Freiburg',
                'text' => 'Heat pumps, heating service, new bathrooms and safe gas and water installations at a fixed price, plus a 24/7 emergency service when the heating fails.',
                'buttons' => [
                    ['label' => 'Get a fixed price', 'url' => '/contact'],
                    ['label' => 'Our services', 'url' => '/services'],
                ],
                'background' => ['id' => $fileId, 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'layout' => 'figures',
                'cards' => [
                    ['title' => '1987', 'text' => 'Family business in Freiburg'],
                    ['title' => '280', 'text' => 'Heat pumps installed'],
                    ['title' => '2 h', 'text' => 'Emergency response time'],
                    ['title' => '4.9/5', 'text' => 'From 412 customer reviews'],
                ],
            ]],
            $this->services(),
            $this->badges(),
            $this->prices(),
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'How a job runs',
                'layout' => 'horizontal',
                'items' => [
                    ['label' => 'Step 1', 'title' => 'Tell us', 'text' => 'Send photos of the boiler or bathroom, or book a visit for bigger jobs.'],
                    ['label' => 'Step 2', 'title' => 'Fixed price', 'text' => 'A written offer with the subsidies you can claim, usually within a week.'],
                    ['label' => 'Step 3', 'title' => 'Clean work', 'text' => 'Protected floors, one site manager and a tidy house every evening.'],
                    ['label' => 'Step 4', 'title' => 'Handover', 'text' => 'Pressure tests, a walk through the controls and all documents by email.'],
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
                'title' => 'Trusted across the Breisgau',
                'items' => $this->reviews(),
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Burst pipe or no heating?',
                'text' => 'Close the main water valve and call our emergency service. A plumber is with you within two hours, day or night. If you smell gas, leave the house first and call the bnNETZE gas emergency number 0800 2 767 767 from outside.',
                'buttons' => [
                    ['label' => 'Call 0761 4587 299', 'url' => 'tel:+497614587299'],
                    ['label' => 'Emergency service', 'url' => '/emergency-service'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        $meta = [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => 'Brenner & Quell installs heat pumps, services heating systems, renovates bathrooms and fits gas and water pipes in Freiburg, with a 24/7 emergency service.',
                'keywords' => 'plumber Freiburg, heating engineer, heat pump installation, heating service, bathroom renovation, drinking water, gas installation, emergency plumber',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => 'Brenner & Quell | Plumbing and Heating in Freiburg',
                'description' => 'Master plumbers and heating engineers with fixed prices and a 24/7 emergency service.',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        return $this->saveRoot( 'Brenner & Quell | Plumbing and Heating in Freiburg', $config, $meta, $content, $elementId, $fileId );
    }


    /**
     * Creates the Brenner & Quell SVG logo and returns its file ID.
     *
     * @return string File ID
     */
    protected function logoFile() : string
    {
        if( !isset( $this->logoFile ) )
        {
            $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 440 80" role="img" aria-labelledby="title desc">
  <title id="title">Brenner &amp; Quell logo</title>
  <desc id="desc">Water blue rounded square with a white drop and a copper wave beside the Brenner &amp; Quell wordmark</desc>
  <rect x="4" y="8" width="64" height="64" rx="18" fill="#0B7A9E"/>
  <path d="M36 16s-16 18-16 30a16 16 0 0 0 32 0c0-12-16-30-16-30z" fill="#FFFFFF"/>
  <path d="M21 50c5-4 10-4 15 0s10 4 15 0" fill="none" stroke="#E07B39" stroke-width="5" stroke-linecap="round"/>
  <text x="84" y="54" fill="#FFFFFF" font-family="ui-rounded, Quicksand, system-ui, Segoe UI, Roboto, Arial, sans-serif" font-size="36" font-weight="800" letter-spacing="-0.5">Brenner &amp; Quell</text>
</svg>
SVG;

            $this->logoFile = $this->svgFile(
                $svg,
                'brenner-quell-logo.svg',
                'Brenner & Quell logo',
                'Water blue rounded square with a white drop and a copper wave beside the Brenner & Quell wordmark',
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
            [['name' => 'Heat pumps', 'title' => 'Heat Pump Installation in Freiburg | Brenner & Quell', 'path' => 'heat-pumps'],
                'Heat with the air around your house', 'heat-pump-house', 'heat-pump',
                "## Planned for existing homes\n\nA heat pump doesn't need a new building. We calculate the heat load of every room, check your radiators and choose a unit that runs quietly and efficiently at low flow temperatures.\n\nWe prepare the documents for the KfW grant and the City of Freiburg's \"Klimafreundlich Wohnen\" programme, remove the old boiler and hand over a system with hydraulic balancing, a hot water cylinder and an app you understand.",
                [
                    ['title' => 'Heat load calculation', 'text' => 'Room by room, so only the radiators that are really too small get replaced.'],
                    ['title' => 'Subsidy support', 'text' => 'All confirmations for your grant application, prepared by us.'],
                    ['title' => 'Quiet placement', 'text' => 'Outdoor unit placed with a sound check for you and your neighbours.'],
                ]],
            [['name' => 'Heating service', 'title' => 'Heating Service and Boiler Repair in Freiburg | Brenner & Quell', 'path' => 'heating-service'],
                'Keep your heating running for years', 'heating-room', 'manifold',
                "## Service for gas, oil and heat pumps\n\nA serviced heating system uses less energy, breaks down less often and keeps its warranty. We clean the burner or heat exchanger, check safety devices and pressure, read the fault memory and optimise the settings.\n\nYou get a service report by email and a reminder before the next service is due. With our maintenance contract, we book the date for you and you get priority in our emergency service.",
                [
                    ['title' => 'All brands', 'text' => 'Gas and oil boilers, heat pumps and hot water cylinders of all common makers.'],
                    ['title' => 'Maintenance contract', 'text' => 'Yearly service at a fixed date and priority when your heating fails.'],
                    ['title' => 'Service report', 'text' => 'Every value measured and documented for you and the chimney sweep.'],
                ]],
            [['name' => 'Bathrooms', 'title' => 'Bathroom Renovation in Freiburg | Brenner & Quell', 'path' => 'bathrooms'],
                'Your new bathroom from one hand', 'shower', 'toilet',
                "## Planning, plumbing, tiles and light\n\nWe plan your bathroom with you on site, show you the fittings in our showroom and coordinate all trades. One site manager, one schedule and one fixed price, from the first tile removed to the last silicone joint.\n\nLevel-access showers, wall-hung toilets and wide doors make the bathroom ready for every stage of life. With a care level, the care insurance pays up to €4,180 for the conversion, and the KfW grant 455-B adds up to €6,250 while its budget lasts. Apply before you sign the order.",
                [
                    ['title' => 'One contact', 'text' => 'A site manager coordinates plumbers, tilers and electricians.'],
                    ['title' => 'Barrier-free', 'text' => 'Level-access showers and fittings planned for every age.'],
                    ['title' => 'Clean site', 'text' => 'Dust walls and protected floors in the rest of your home.'],
                ]],
            [['name' => 'Drinking water', 'title' => 'Drinking Water Installations in Freiburg | Brenner & Quell', 'path' => 'drinking-water'],
                'Clean water from every tap', 'tap', 'water-heater',
                "## Pipes, tests and treatment\n\nOld galvanised or lead pipes, low pressure and brown water are signs that your installation needs attention. We renew pipes in houses and apartment buildings, flush and disinfect them and fit filters and water softeners where they make sense.\n\nLead pipes had to be removed by 12 January 2026. If your house still has them, we replace them and confirm it for the health office.\n\nLandlords whose central hot water system has a cylinder over 400 litres or more than 3 litres in the pipes must have it tested for legionella every three years. We arrange the sampling and the report.",
                [
                    ['title' => 'Pipe renewal', 'text' => 'Stainless steel or multilayer pipes, floor by floor and flat by flat.'],
                    ['title' => 'Legionella tests', 'text' => 'Sampling points, lab tests and the report for your tenants.'],
                    ['title' => 'Water treatment', 'text' => 'Filters and softeners that protect pipes and appliances.'],
                ]],
            [['name' => 'Gas installations', 'title' => 'Gas Installations and Gas Pipe Checks in Freiburg | Brenner & Quell', 'path' => 'gas-installations'],
                'Safe gas, checked by professionals', 'meter', 'heating-room',
                "## Licensed gas fitters\n\nWork on gas pipes may only be done by companies registered with the network operator. We check your gas pipes for leaks, connect gas cookers and boilers and seal old pipes safely when you switch to a heat pump.\n\nWe recommend a gas pipe check every twelve years, as the technical rules suggest. You get a protocol for your insurance.",
                [
                    ['title' => 'Leak test', 'text' => 'Pressure and usability test of all gas pipes in your home.'],
                    ['title' => 'Connections', 'text' => 'Gas cookers, boilers and water heaters connected and tested.'],
                    ['title' => 'Safe shutdown', 'text' => 'Gas pipes sealed and the meter removal arranged when you switch.'],
                ]],
            [['name' => 'Emergency service', 'title' => '24/7 Plumbing and Heating Emergency Service in Freiburg | Brenner & Quell', 'path' => 'emergency-service'],
                'Water everywhere? We are on our way', 'portrait', 'siphon',
                "## Day and night, every day of the year\n\nBurst pipes, a heating failure in January, a blocked drain or a leaking hot water cylinder can't wait until Monday. Call our emergency service and a plumber is with you within two hours in Freiburg and the surrounding area. Customers with a maintenance contract are served first.\n\nWe stop the damage first and tell you the price of the repair before we start.\n\nIf you smell gas, leave the building first and call the bnNETZE gas emergency number 0800 2 767 767 from outside, then call us.",
                [
                    ['title' => 'Two-hour response', 'text' => 'A plumber on the way within minutes of your call.'],
                    ['title' => 'Damage stopped', 'text' => 'Leaks closed and the heating running again where possible.'],
                    ['title' => 'Clear call-out fee', 'text' => '€89 call-out fee, +50% at night, on Sundays and public holidays. Repairs are priced before we start.'],
                ]],
        ];
    }


    /**
     * Creates a Flow demo page below the given parent and returns it.
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

        return $this->savePage( $data, $content, $parent, $elementId, $fileId, $footer, 'Brenner & Quell, plumber Freiburg, heating engineer, heat pump, bathroom, drinking water, gas installation' );
    }


    /**
     * Builds the Flow plumbing and heating demo page tree.
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
     * Returns the fixed price list element.
     *
     * @return array<string, mixed> Pricing content element
     */
    protected function prices() : array
    {
        return ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
            'title' => 'Fixed prices',
            'text' => 'Prices for a typical home including VAT and travel within Freiburg. You get a written offer before we start.',
            'items' => [
                [
                    'name' => 'Gas pipe check',
                    'prices' => [['id' => 'gas', 'amount' => 159, 'label' => '€159']],
                    'text' => 'Leak and usability test of all gas pipes in a house or flat.',
                    'features' => "- Leakage measurement of all pipes\n- Protocol for your insurance\n- Repairs quoted before we start",
                    'url' => '/gas-installations',
                    'button' => 'Gas installations',
                ],
                [
                    'name' => 'Heating service',
                    'prices' => [['id' => 'service', 'amount' => 189, 'label' => 'from €189']],
                    'text' => 'Annual service for gas boilers of all common brands, oil boilers from €229.',
                    'features' => "- Cleaning and safety check\n- Settings optimised\n- Service report by email",
                    'url' => '/heating-service',
                    'button' => 'Heating service',
                    'highlight' => true,
                    'badge' => 'Most booked',
                ],
                [
                    'name' => 'Heat pump check',
                    'prices' => [['id' => 'check', 'amount' => 290, 'label' => '€290']],
                    'text' => 'Visit with heat load estimate, radiator check and subsidy advice.',
                    'features' => "- Report within a week\n- Subsidy amount calculated\n- Credited on your order",
                    'url' => '/heat-pumps',
                    'button' => 'Heat pumps',
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
                'text' => 'Send us a few photos and you get a fixed price, usually within a week.',
                'buttons' => [
                    ['label' => 'Get a fixed price', 'url' => '/contact'],
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
            'title' => 'Our Projects | Brenner & Quell',
            'path' => 'projects',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Recent projects',
                'subtitle' => 'Our work',
                'text' => 'Heat pumps, bathrooms and new water pipes in Freiburg and the Breisgau, with the numbers behind each project.',
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
            ['name' => 'Sabine and Thomas K.', 'role' => 'Heat pump, Freiburg-Herdern', 'text' => 'Three other companies wanted to replace all our radiators. Brenner & Quell calculated every room and we kept most of them. The house has never been this evenly warm.'],
            ['name' => 'Gisela M.', 'role' => 'Level-access bathroom, Freiburg-Wiehre', 'text' => 'One site manager, one schedule and exactly the price in the offer. After fourteen working days I had a bathroom I can use for many years.'],
            ['name' => 'Markus H.', 'role' => 'Emergency service, Emmendingen', 'text' => 'A pipe burst in the cellar on a Sunday evening. The plumber was there in fifty minutes and the water was off in five.'],
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
                'text' => 'Send us a few photos and you get a written offer, usually within a week.',
                'buttons' => [
                    ['label' => 'Get a fixed price', 'url' => '/contact'],
                    ['label' => 'Call 0761 4587 210', 'url' => 'tel:+497614587210'],
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
                ['title' => 'Heat pumps', 'text' => 'Planned room by room for existing homes, with help for your subsidy.', 'url' => '/heat-pumps', 'file' => ['id' => $this->img( 'heat-pump' ), 'type' => 'file']],
                ['title' => 'Heating service', 'text' => 'Annual service and repairs for gas, oil and heat pump systems.', 'url' => '/heating-service', 'file' => ['id' => $this->img( 'heating-room' ), 'type' => 'file']],
                ['title' => 'Bathrooms', 'text' => 'Complete renovations with level-access showers from one hand.', 'url' => '/bathrooms', 'file' => ['id' => $this->img( 'shower' ), 'type' => 'file']],
                ['title' => 'Drinking water', 'text' => 'New pipes, legionella tests and water treatment for clean water.', 'url' => '/drinking-water', 'file' => ['id' => $this->img( 'tap' ), 'type' => 'file']],
                ['title' => 'Gas installations', 'text' => 'Gas pipe checks, connections and safe shutdowns by licensed fitters.', 'url' => '/gas-installations', 'file' => ['id' => $this->img( 'meter' ), 'type' => 'file']],
                ['title' => 'Emergency service', 'text' => 'A plumber with you within two hours, day and night.', 'url' => '/emergency-service', 'file' => ['id' => $this->img( 'portrait' ), 'type' => 'file']],
            ],
        ]];
    }
}
