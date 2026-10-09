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
 * Brew theme demo for the fictional Tamper & Crumb café, roastery and bakery.
 */
class BrewDemo extends AbstractDemo
{
    /** @var array<string, string> Meta descriptions keyed by page path */
    protected const DESCRIPTIONS = [
        'careers' => 'Work at Tamper & Crumb in Leipzig: baristas, bakers and roastery staff with fair pay, a five-day week and free coffee training.',
        'catering' => 'Coffee catering in Leipzig: espresso bar, batch brew and pastries for offices, weddings and events, from 20 to 400 guests.',
        'classes' => 'Coffee classes in Leipzig: home barista, latte art and cupping workshops in small groups at the Tamper & Crumb roastery.',
        'coffee' => 'Seasonal single-origin coffees and the Morning Ritual blend, roasted every Tuesday in Leipzig. Beans, ground coffee and subscriptions.',
        'farm-visit' => 'A visit to Finca El Nevado in Huila, Colombia: how the Ramírez family grows and processes the coffee we have bought for six years.',
        'grain-guide' => 'Why we bake with heritage grains: spelt, einkorn and rye from Saxon farms, long fermentation and what it means for flavour.',
        'imprint' => 'Legal notice of Tamper & Crumb Kaffeerösterei & Bäckerei GmbH, Leipzig.',
        'journal' => 'The Tamper & Crumb journal: brew guides, origin stories and notes from our bakery in Leipzig.',
        'menu' => 'The Tamper & Crumb menu: espresso and filter coffee, matcha and chai, pastries from our own bakery and breakfast until 13:00.',
        'order' => 'Pre-order coffee and pastry boxes from Tamper & Crumb in Leipzig and pick them up at the counter without waiting.',
        'privacy' => 'Privacy policy of Tamper & Crumb Kaffeerösterei & Bäckerei GmbH, Leipzig.',
        'story' => 'The story of Tamper & Crumb: a roastery and bakery in Leipzig-Plagwitz since 2014, run by baristas and bakers.',
        'v60-guide' => 'How to brew great V60 pour-over coffee at home: ratio, grind size, water temperature and a step-by-step recipe.',
        'visit' => 'Visit Tamper & Crumb on Karl-Heine-Straße in Leipzig: opening hours, getting here, seating, terrace and accessibility.',
        'wholesale' => 'Wholesale coffee for cafés, restaurants and offices in Saxony: freshly roasted beans, barista training and equipment support.',
    ];

    /**
     * Curated Unsplash photos used by the café demo.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    protected const PHOTOS = [
        'avocado-toast' => ['photo-1631311915775-e8f4250a7d4e', 'Avocado on rye', 'Avocado toast on dark rye bread served on a plate'],
        'baker' => ['photo-1549057188-efd70413345e', 'Baker', 'Smiling woman shaping dough at a floured workbench'],
        'barista-2' => ['photo-1569683236049-bc137196a02a', 'Roaster', 'Young man in a leather apron standing in a café'],
        'barista-3' => ['photo-1599000117587-9f2f56878366', 'Barista', 'Barista with curly black hair holding a milk pitcher'],
        'barista-5' => ['photo-1736901217577-8438e562922c', 'Café manager', 'Man with glasses and a moustache in a café'],
        'barista-machine' => ['photo-1595928642569-a3a948004535', 'At the machine', 'Barista pulling a shot on a chrome espresso machine'],
        'barista-team' => ['photo-1753351052046-8c6818304a4f', 'Our team', 'Group of baristas laughing together behind the counter'],
        'beans' => ['photo-1447933601403-0c6688de566e', 'Roasted beans', 'Close-up of freshly roasted dark brown coffee beans'],
        'beans-3' => ['photo-1580933073521-dc49ac0d4e6a', 'Coffee beans', 'Roasted coffee beans scattered on a light surface'],
        'bag-shelf' => ['photo-1562186971-736d2e3ae153', 'Retail shelf', 'Bags of coffee lined up on a wooden shelf'],
        'cinnamon-buns' => ['photo-1686207855146-c3ffe2166d40', 'Cardamom buns', 'Freshly baked twisted buns on a tray'],
        'coffee-cart' => ['photo-1776604198818-7b6a90e40a81', 'Coffee van', 'Green coffee van serving drinks at an outdoor event'],
        'coffee-cart-2' => ['photo-1778313119675-2fecda5a377f', 'Coffee cart', 'Mobile coffee cart set up outdoors'],
        'coffee-cherries' => ['photo-1788276363214-7dd2432c806c', 'Coffee cherries', 'Ripe red coffee cherries on the branch'],
        'coffee-sacks' => ['photo-1565273975921-c884f2b703df', 'Green coffee sacks', 'Jute sacks of green coffee stacked in a warehouse'],
        'croissants' => ['photo-1555507036-ab1f4038808a', 'Croissants', 'Golden butter croissants on a wooden board'],
        'croissants-2' => ['photo-1623334044303-241021148842', 'Fresh croissants', 'Freshly baked croissants on a baking tray'],
        'cupping' => ['photo-1558996260-4bac67dbd110', 'Cupping', 'Rows of cupping bowls with ground coffee on a table'],
        'espresso-machine' => ['photo-1461988366670-48e401bafb0a', 'Espresso machine', 'Espresso machine group head extracting into a cup'],
        'espresso-shot' => ['photo-1736813132132-7cd457c12ea6', 'Espresso', 'Espresso pouring from a portafilter into a glass'],
        'farm-harvest' => ['photo-1772228616071-aa344913b93e', 'Harvest', 'Freshly harvested coffee cherries in a basket'],
        'flat-white' => ['photo-1531441802565-2948024f1b22', 'Flat white', 'Flat white with latte art seen from above'],
        'green-beans' => ['photo-1703646619157-eb553d16d402', 'Green coffee', 'Unroasted green coffee beans'],
        'iced-coffee' => ['photo-1461023058943-07fcbe16d735', 'Iced coffee', 'Iced coffee in a glass with a straw'],
        'interior-1' => ['photo-1600093463592-8e36ae95ef56', 'Garden room', 'Bright café room with plants and wooden tables'],
        'latte-art' => ['photo-1512568400610-62da28bc8a13', 'Latte art', 'Latte with a rosetta pattern on a bed of coffee beans'],
        'latte-art-2' => ['photo-1529892485617-25f63cd7b1e9', 'Four lattes', 'Four lattes with different latte art patterns'],
        'latte-pour' => ['photo-1541167760496-1628856ab772', 'Pouring a latte', 'Milk being poured into a latte to create latte art'],
        'pour-over' => ['photo-1442512595331-e89e73853f31', 'Pour-over', 'Hot water poured from a gooseneck kettle into a pour-over dripper'],
        'roaster' => ['photo-1741994042855-48baa6a524b6', 'Coffee roaster', 'Drum coffee roaster with beans cooling in the tray'],
        'roaster-2' => ['photo-1511537190424-bbbab87ac5eb', 'Roasting', 'Freshly roasted beans in the cooling tray of a roaster'],
        'sourdough' => ['photo-1549413468-cd78edb7e75c', 'Sourdough', 'Rustic sourdough loaves on a wooden table'],
        'sourdough-2' => ['photo-1590301157172-7ba48dd1c2b2', 'Sourdough loaf', 'Sliced sourdough loaf with an open crumb'],
        'storefront' => ['photo-1559925393-8be0ec4767c8', 'Street café', 'Café front with tables on the pavement and a chalkboard'],
        'tea' => ['photo-1544483827-d15c3e6cabc2', 'Tea', 'Cup of tea on a wooden table'],
        'terrace' => ['photo-1759050475187-674550fa911e', 'Terrace', 'Outdoor café terrace with tables in the sun'],
        'terrace-2' => ['photo-1779678484442-3c32f3a7b629', 'Summer terrace', 'Café terrace with chairs and plants'],
        'window-seat' => ['photo-1774979517595-87ba8a988d1b', 'Window seat', 'Guest sitting at a warm-lit window bar'],
    ];

    private string $element;
    private string $journalId;
    /** @var array<string, string> */
    private array $icons = [];
    private string $logoFile;


    /**
     * Creates the careers page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addCareers( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Careers',
            'title' => 'Jobs at Tamper & Crumb Leipzig | Barista, Baker, Roaster',
            'path' => 'careers',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Make good coffee with good people',
                'subtitle' => 'Careers',
                'text' => 'We are a team of 18 baristas, bakers and roasters. No split shifts, no night shifts in the café and a training budget for everyone.',
                'buttons' => [
                    ['label' => 'Send your application', 'url' => 'mailto:jobs@tamperandcrumb.example'],
                ],
                'files' => [['id' => $this->arch( 'barista-team' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'barista-machine' ), 'type' => 'file'],
                'position' => 'grid-end',
                'ratio' => '1-1',
                'text' => "## What you get\n\n- A wage above the hospitality tariff, plus a fair share of the tips\n- Five-day weeks with two days off in a row\n- SCA barista and sensory courses, paid by us\n- Free coffee, lunch on every shift and a bag of beans every week\n- A say in what we roast and bake next",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Open positions',
                'columns' => '3',
                'cards' => [
                    ['title' => 'Barista (m/f/d)', 'text' => "**Full or part time**\nYou love people and milk texture. Experience helps, but we train you on our machines."],
                    ['title' => 'Baker (m/f/d)', 'text' => "**Full time, from 05:00**\nLaminated dough and sourdough are your thing, or you want them to be."],
                    ['title' => 'Roastery assistant (m/f/d)', 'text' => "**Part time, Tue and Wed**\nRoasting, packing and shipping our coffees to cafés and subscribers."],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Come by for a coffee',
                'text' => 'Send us a few lines about yourself. A CV is welcome but not required, and every application gets an answer within a week.',
                'buttons' => [
                    ['label' => 'Send your application', 'url' => 'mailto:jobs@tamperandcrumb.example'],
                    ['label' => 'Read our story', 'url' => '/story'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the catering page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addCatering( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Catering',
            'title' => 'Coffee Catering in Leipzig for Offices and Events | Tamper & Crumb',
            'path' => 'catering',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'A coffee bar for your event',
                'subtitle' => 'Catering',
                'text' => 'Our coffee van, two baristas and a table full of pastries. For office breakfasts, conferences, weddings and markets from 20 to 400 guests.',
                'buttons' => [
                    ['label' => 'Ask for a quote', 'url' => '#catering-request'],
                ],
                'files' => [['id' => $this->arch( 'coffee-cart' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Three ways to cater',
                'columns' => '3',
                'cards' => [
                    ['title' => 'Office breakfast', 'text' => "**From 20 guests**\nBatch brew in pump flasks, croissants, buns and fruit, delivered by bike before 9.", 'file' => ['id' => $this->img( 'croissants-2' ), 'type' => 'file']],
                    ['title' => 'Espresso bar', 'text' => "**From 50 guests**\nOur van or a mobile bar with baristas, oat milk and the full espresso menu.", 'file' => ['id' => $this->img( 'coffee-cart-2' ), 'type' => 'file']],
                    ['title' => 'Wedding and party', 'text' => "**From 80 guests**\nEspresso tonics at the reception and cheesecake instead of a wedding cake, if you like.", 'file' => ['id' => $this->img( 'latte-art-2' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
                'title' => 'Guide prices',
                'text' => 'Final prices depend on the date, place and number of guests. All prices plus VAT.',
                'items' => [
                    [
                        'name' => 'Breakfast box',
                        'prices' => [['id' => 'box', 'amount' => 9, 'label' => '€9 / person']],
                        'text' => 'Delivered, self-service.',
                        'features' => "- Batch brew and tea\n- Two pastries per person\n- Seasonal fruit\n- Cups, milk and oat milk",
                        'url' => '#catering-request',
                        'button' => 'Ask for a quote',
                    ],
                    [
                        'name' => 'Espresso bar',
                        'prices' => [['id' => 'bar', 'amount' => 690, 'label' => 'from €690']],
                        'text' => 'Three hours, two baristas.',
                        'features' => "- Full espresso and filter menu\n- Up to 200 drinks included\n- Oat, almond and whole milk\n- Set-up and clean-up",
                        'url' => '#catering-request',
                        'button' => 'Ask for a quote',
                        'highlight' => true,
                        'badge' => 'Most booked',
                    ],
                    [
                        'name' => 'Coffee van',
                        'prices' => [['id' => 'van', 'amount' => 1190, 'label' => 'from €1,190']],
                        'text' => 'Five hours, outdoors.',
                        'features' => "- Our green coffee van\n- Up to 400 drinks included\n- Pastry counter on request\n- Own power and water",
                        'url' => '#catering-request',
                        'button' => 'Ask for a quote',
                    ],
                ],
            ]],
            ['id' => 'catering-request', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Ask for a quote',
                'description' => 'Tell us about your event and we send you an offer within two working days.',
                'inputs' => [
                    ['field' => 'name', 'required' => true, 'input' => 'text'],
                    ['field' => 'email', 'required' => true, 'input' => 'text'],
                    ['field' => 'telephone', 'required' => false, 'input' => 'text'],
                    ['field' => 'Type of event', 'required' => true, 'input' => 'select', 'options' => "Office breakfast\nConference\nWedding\nMarket or festival\nOther"],
                    ['field' => 'Date', 'required' => true, 'input' => 'text'],
                    ['field' => 'Number of guests', 'required' => true, 'input' => 'text'],
                    ['field' => 'Location', 'required' => true, 'input' => 'text'],
                    ['field' => 'Anything else we should know', 'required' => false, 'input' => 'textarea'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the coffee classes page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addClasses( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Classes',
            'title' => 'Barista, Latte Art and Cupping Classes in Leipzig | Tamper & Crumb',
            'path' => 'classes',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Brew better coffee at home',
                'subtitle' => 'Classes at the roastery',
                'text' => 'Hands-on workshops for six people at a time, on the same machines we use behind the bar. Coffee and pastries included, of course.',
                'buttons' => [
                    ['label' => 'Book a class', 'url' => 'mailto:classes@tamperandcrumb.example'],
                    ['label' => 'Gift vouchers', 'url' => '/order'],
                ],
                'files' => [['id' => $this->arch( 'latte-pour' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
                'title' => 'Choose your class',
                'text' => 'Classes take place on Saturday and Sunday afternoons in the roastery behind the café. Vouchers are valid for a year.',
                'items' => [
                    [
                        'name' => 'Home barista',
                        'file' => ['id' => $this->img( 'barista-machine' ), 'type' => 'file'],
                        'prices' => [['id' => 'home', 'amount' => 79, 'label' => '€79']],
                        'text' => '3 hours, for beginners.',
                        'features' => "- Dialling in espresso\n- Grind size, dose and ratio\n- Steaming milk at home\n- Cleaning your machine",
                        'url' => 'mailto:classes@tamperandcrumb.example',
                        'button' => 'Book home barista',
                    ],
                    [
                        'name' => 'Latte art',
                        'file' => ['id' => $this->img( 'latte-art' ), 'type' => 'file'],
                        'prices' => [['id' => 'latte', 'amount' => 89, 'label' => '€89']],
                        'text' => '3 hours, some experience.',
                        'features' => "- Silky microfoam\n- Heart, tulip and rosetta\n- Pouring with oat milk\n- 20 drinks of practice",
                        'url' => 'mailto:classes@tamperandcrumb.example',
                        'button' => 'Book latte art',
                        'highlight' => true,
                        'badge' => 'Popular',
                    ],
                    [
                        'name' => 'Cupping',
                        'file' => ['id' => $this->img( 'cupping' ), 'type' => 'file'],
                        'prices' => [['id' => 'cupping', 'amount' => 49, 'label' => '€49']],
                        'text' => '2 hours, for everyone.',
                        'features' => "- Taste eight origins side by side\n- Processing and roast level\n- The flavour wheel\n- A bag of your favourite",
                        'url' => 'mailto:classes@tamperandcrumb.example',
                        'button' => 'Book cupping',
                    ],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'A class afternoon',
                'layout' => 'horizontal',
                'items' => [
                    ['label' => '14:00', 'title' => 'Welcome', 'text' => 'Coffee, a cardamom bun and a tour of the roastery.'],
                    ['label' => '14:30', 'title' => 'Theory', 'text' => 'Fifteen minutes on what happens in the cup. No slides.'],
                    ['label' => '14:45', 'title' => 'Practice', 'text' => 'Two people per machine, a trainer always next to you.'],
                    ['label' => '17:00', 'title' => 'Take home', 'text' => 'Recipe card, a bag of beans and ten percent off equipment.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Questions about our classes',
                'items' => [
                    ['title' => 'Do I need my own machine?', 'text' => 'No. You practise on our machines and grinders. We happily look at photos of your setup at home and give you tips.'],
                    ['title' => 'Can I book a private class?', 'text' => 'Yes, for up to six people from €390. It is a popular team event.'],
                    ['title' => 'Are the classes in English?', 'text' => 'Most classes are in German, one weekend a month in English. Ask us for the next date.'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the coffee page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addCoffee( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Coffee',
            'title' => 'Single-Origin Coffee Beans, Roasted in Leipzig | Tamper & Crumb',
            'path' => 'coffee',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Roasted on Tuesdays, in your cup by Friday',
                'subtitle' => 'Our coffee',
                'text' => 'We buy from six farms we know by name and roast in small batches on a 15 kg drum roaster in Plagwitz. Light enough to taste the origin, never sour.',
                'buttons' => [
                    ['label' => 'Start a subscription', 'url' => '#subscription'],
                    ['label' => 'Wholesale', 'url' => '/wholesale'],
                ],
                'files' => [['id' => $this->arch( 'roaster-2' ), 'type' => 'file']],
            ]],
            $this->roasting( 'This season\'s coffees', true ),
            ['id' => 'subscription', 'type' => 'pricing', 'group' => 'main', 'data' => [
                'title' => 'Coffee subscription',
                'text' => 'Fresh beans in your letterbox, roasted on Tuesday and posted on Wednesday. Pause or cancel any time.',
                'items' => [
                    [
                        'name' => 'House',
                        'prices' => [['id' => 'house', 'kind' => 'subscription', 'interval' => 30, 'currency' => 'EUR', 'amount' => 22, 'label' => '€22 / month']],
                        'text' => '2 × 250 g Morning Ritual.',
                        'features' => "- Our chocolatey house blend\n- Beans or ground to order\n- Free shipping in Germany",
                        'url' => '/order',
                        'button' => 'Choose House',
                    ],
                    [
                        'name' => 'Roaster\'s choice',
                        'prices' => [['id' => 'choice', 'kind' => 'subscription', 'interval' => 30, 'currency' => 'EUR', 'amount' => 29, 'label' => '€29 / month']],
                        'text' => '2 × 250 g single origins.',
                        'features' => "- Two different coffees each month\n- Tasting notes and brew recipes\n- First access to rare lots\n- Free shipping in Germany",
                        'url' => '/order',
                        'button' => 'Choose Roaster\'s choice',
                        'highlight' => true,
                        'badge' => 'Our favourite',
                    ],
                    [
                        'name' => 'Office',
                        'prices' => [['id' => 'office', 'kind' => 'subscription', 'interval' => 30, 'currency' => 'EUR', 'amount' => 79, 'label' => '€79 / month']],
                        'text' => '4 × 1 kg for teams.',
                        'features' => "- Espresso or filter roast\n- Delivered by cargo bike in Leipzig\n- Grinder check twice a year",
                        'url' => '/wholesale',
                        'button' => 'Choose Office',
                    ],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'From farm to cup',
                'layout' => 'horizontal',
                'items' => [
                    ['label' => 'Harvest', 'title' => 'Picked ripe', 'text' => 'Only red cherries, picked by hand in several rounds.'],
                    ['label' => 'Import', 'title' => 'Bought direct', 'text' => 'We pay at least 2.5 times the market price, agreed before harvest.'],
                    ['label' => 'Tuesday', 'title' => 'Roasted', 'text' => 'Small batches, every profile tasted the next morning.'],
                    ['label' => 'Rest', 'title' => 'Rested', 'text' => 'Five to ten days of rest for espresso, three for filter.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'coffee-cherries' ), 'type' => 'file'],
                'position' => 'grid-start',
                'ratio' => '1-1',
                'text' => "## What we pay and why\n\nFarmers carry the biggest risk and earn the smallest share of the price of your cup. We publish what we pay for every lot, and it is never less than two and a half times the commodity price.\n\n- **Finca El Nevado, Colombia:** €7.40 per kg green coffee\n- **Hambela, Ethiopia:** €8.10 per kg green coffee\n- **Karogoto, Kenya:** €9.20 per kg green coffee\n\n[Read about our visit to Finca El Nevado](/farm-visit)",
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Coffee questions',
                'items' => [
                    ['title' => 'Beans or ground?', 'text' => 'Beans stay fresh for weeks, ground coffee for days. If you don\'t have a grinder, tell us how you brew and we grind it to match.'],
                    ['title' => 'How should I store my coffee?', 'text' => 'In the closed bag, away from light and heat. Not in the fridge, please.'],
                    ['title' => 'When is my coffee at its best?', 'text' => 'Between one and six weeks after the roast date printed on the bag.'],
                ],
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
            'title' => 'Imprint | Tamper & Crumb',
            'path' => 'imprint',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "# Imprint\n\n**Tamper & Crumb Kaffeerösterei & Bäckerei GmbH**\nKarl-Heine-Straße 42\n04229 Leipzig\nGermany\n\nTelephone: +49 341 2345 670\nEmail: hello@tamperandcrumb.example\n\nManaging directors: Samuel Mensah, Lena Vogt\nCommercial register: Leipzig Local Court, HRB 00000\nVAT ID: DE000000000\n\n## Food safety\n\nResponsible food business operator: Tamper & Crumb GmbH. Competent authority: Veterinär- und Lebensmittelaufsichtsamt der Stadt Leipzig.\n\n## Consumer dispute resolution\n\nWe are neither willing nor obliged to take part in dispute resolution proceedings before a consumer arbitration board.\n\nThis is a demo website for the Brew theme. Tamper & Crumb is a fictional café and the people named are not real.",
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the journal page and its posts below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addJournal( Page $home ) : static
    {
        $journal = $this->journal( $home );

        $this->post( $journal, [
            'name' => 'The V60 at home',
            'title' => 'How to Brew V60 Pour-Over Coffee at Home: Our Recipe',
            'path' => 'v60-guide',
        ], 'The V60 at home, in four minutes',
            "The V60 is the brewer we use most behind the bar, and the one we recommend most for home. It is cheap, forgiving and shows the character of a light roast better than any machine.\n\nOur recipe uses 15 g of coffee for 250 g of water. Grind about as fine as table salt, use water just off the boil and pour in slow circles. If it tastes sour, grind finer. If it tastes bitter, grind coarser.",
            'pour-over',
            [
                ['title' => '1:16', 'text' => 'Coffee to water ratio, 15 g to 250 g'],
                ['title' => '94 °C', 'text' => 'Water temperature, about 30 seconds off the boil'],
                ['title' => '3:30', 'text' => 'Minutes of total brew time'],
            ],
            [
                ['label' => '0:00', 'title' => 'Bloom', 'text' => 'Pour 40 g of water and swirl, so all coffee is wet.'],
                ['label' => '0:45', 'title' => 'First pour', 'text' => 'Pour slowly up to 150 g in small circles.'],
                ['label' => '1:30', 'title' => 'Second pour', 'text' => 'Pour up to 250 g and give the dripper a gentle swirl.'],
                ['label' => '3:30', 'title' => 'Drawdown', 'text' => 'The bed should be flat. Remove the dripper and enjoy.'],
            ],
            [
                ['title' => 'Which filters should I use?', 'text' => 'White paper filters, rinsed with hot water first. Brown filters taste of paper.'],
                ['title' => 'Do I need a scale?', 'text' => 'It helps more than any other tool. A simple kitchen scale with 1 g steps is enough.'],
                ['title' => 'Which coffee works best?', 'text' => 'Light and fruity coffees like our Ethiopia Guji or Kenya Nyeri.'],
            ],
        );

        $this->post( $journal, [
            'name' => 'Finca El Nevado',
            'title' => 'Visiting Finca El Nevado in Huila, Colombia',
            'path' => 'farm-visit',
        ], 'Six harvests with the Ramírez family',
            "Finca El Nevado sits at 1,750 metres in the hills above Pitalito in Huila. Don Arturo Ramírez and his daughter Paula grow Caturra and Castillo on eleven hectares, shaded by banana and guamo trees.\n\nWe first cupped their coffee in 2019 and have bought their best lot every year since. This spring we visited for the harvest, picked with the family for three days and agreed the price for the next two years.",
            'farm-harvest',
            [
                ['title' => '1,750 m', 'text' => 'Altitude of the farm above sea level'],
                ['title' => '11 ha', 'text' => 'Of coffee, shaded by fruit trees'],
                ['title' => '6', 'text' => 'Harvests we have bought so far'],
            ],
            [
                ['label' => 'Day 1', 'title' => 'Picking', 'text' => 'Only ripe red cherries, the pickers pass each tree up to five times.'],
                ['label' => 'Day 2', 'title' => 'Fermentation', 'text' => 'The cherries are pulped and ferment for 36 hours in tanks.'],
                ['label' => 'Day 3', 'title' => 'Drying', 'text' => 'Three weeks on raised beds under a parabolic roof.'],
                ['label' => 'Week 12', 'title' => 'Shipping', 'text' => 'In GrainPro bags by sea to Hamburg and by train to Leipzig.'],
            ],
            [
                ['title' => 'Is the coffee organic?', 'text' => 'Not certified, but the family has not used synthetic pesticides since 2016.'],
                ['title' => 'How much do you pay?', 'text' => '€7.40 per kg of green coffee, about 2.7 times the commodity price this year.'],
                ['title' => 'Where can I taste it?', 'text' => 'As espresso and filter in the café, and in bags from our coffee page.'],
            ],
        );

        $this->post( $journal, [
            'name' => 'Baking with heritage grains',
            'title' => 'Why We Bake with Heritage Grains from Saxony',
            'path' => 'grain-guide',
        ], 'Older grains, slower dough, better bread',
            "Our bakers start at five every morning, but the dough for the day started two days earlier. Long, cool fermentation makes bread that tastes deeper, keeps longer and is easier to digest.\n\nWe mill spelt, einkorn and rye from two farms near Grimma ourselves. These old varieties yield less than modern wheat, but they need little fertiliser and they taste of something.",
            'sourdough',
            [
                ['title' => '48 h', 'text' => 'From mixing the dough to the oven'],
                ['title' => '40 km', 'text' => 'From the fields near Grimma to our mill'],
                ['title' => '3', 'text' => 'Heritage grains: spelt, einkorn and rye'],
            ],
            [
                ['label' => 'Monday', 'title' => 'Milling', 'text' => 'We mill only what we need for the next two days.'],
                ['label' => 'Tuesday', 'title' => 'Mixing', 'text' => 'Flour, water, salt and our 10-year-old sourdough starter.'],
                ['label' => 'Tuesday night', 'title' => 'Cold proof', 'text' => 'The dough rests at 4 °C for at least 36 hours.'],
                ['label' => 'Wednesday', 'title' => 'Baking', 'text' => 'In a stone deck oven, dark and crusty.'],
            ],
            [
                ['title' => 'Is spelt gluten-free?', 'text' => 'No. Spelt and einkorn contain gluten. Our banana bread is our gluten-free bake.'],
                ['title' => 'Can I buy your bread to take home?', 'text' => 'Yes, every day from 7:30 while it lasts. Pre-order on our order page to be sure.'],
                ['title' => 'Do you sell your starter?', 'text' => 'We give it away. Bring a clean jar and ask at the counter.'],
            ],
        );

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
            'title' => 'Café Menu: Coffee, Pastries and Breakfast | Tamper & Crumb Leipzig',
            'path' => 'menu',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Our menu',
                'subtitle' => 'Café & bakery',
                'text' => 'Espresso from our own roastery, pastries from our own oven and breakfast until 13:00. Ask us about today\'s single origin on filter.',
                'buttons' => [
                    ['label' => 'Order ahead', 'url' => '/order'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
                'title' => 'Coffee & drinks',
                'items' => [
                    [
                        'name' => 'Espresso & milk',
                        'prices' => [['id' => 'espresso', 'label' => 'Double shot']],
                        'text' => 'Morning Ritual blend or the single origin of the week.',
                        'features' => "- Espresso **2.80**\n- Cortado **3.60**\n- Flat white **3.90**\n- Cappuccino **3.90**\n- Latte **4.20**\n- Mocha **4.50**",
                    ],
                    [
                        'name' => 'Filter & cold',
                        'prices' => [['id' => 'filter', 'label' => 'Single origin']],
                        'text' => 'Brewed fresh, rotating every week.',
                        'features' => "- Batch brew **3.20**\n- V60 pour-over **4.80**\n- Cold brew **4.20**\n- Espresso tonic **4.60**",
                        'highlight' => true,
                        'badge' => 'This week: Kenya',
                    ],
                    [
                        'name' => 'Not coffee',
                        'prices' => [['id' => 'tea', 'label' => 'Hot or iced']],
                        'text' => 'Made with whole milk or oat milk.',
                        'features' => "- Matcha latte **4.60**\n- Chai latte **4.20**\n- Hot chocolate **3.80**\n- Loose-leaf tea **3.20**",
                    ],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
                'title' => 'Bakery & breakfast',
                'items' => [
                    [
                        'name' => 'From our oven',
                        'file' => ['id' => $this->img( 'croissants' ), 'type' => 'file'],
                        'prices' => [['id' => 'bakery', 'label' => 'Baked daily']],
                        'text' => 'Fresh from 7:30, while they last.',
                        'features' => "- Butter croissant **3.20**\n- Cardamom bun `V` **3.80**\n- Almond croissant `N` **4.20**\n- Banana bread `VG` `GF` **3.90**\n- Basque cheesecake **5.50**",
                    ],
                    [
                        'name' => 'Breakfast',
                        'file' => ['id' => $this->img( 'avocado-toast' ), 'type' => 'file'],
                        'prices' => [['id' => 'breakfast', 'label' => 'Until 13:00']],
                        'text' => 'On our own sourdough and rye.',
                        'features' => "- Sourdough toast, butter, jam `V` **5.50**\n- Eggs on sourdough `V` **8.50**\n- Avocado on rye `VG` **9.50**\n- Granola, yoghurt, fruit `V` `GF` **7.50**",
                    ],
                    [
                        'name' => 'Extras',
                        'file' => ['id' => $this->img( 'latte-pour' ), 'type' => 'file'],
                        'prices' => [['id' => 'extras', 'label' => 'Make it yours']],
                        'text' => 'Oat milk is always free for kids.',
                        'features' => "- Oat, almond or soy milk **0.50**\n- Extra shot **0.70**\n- House vanilla syrup **0.60**\n- Decaf, any drink **0.00**",
                    ],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "`V` vegetarian · `VG` vegan · `GF` gluten-free · `N` contains nuts\n\nAll prices in euros, including VAT. Please tell us about allergies, our full allergen list is at the counter.",
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Good to know',
                'items' => [
                    ['title' => 'Do you have decaf?', 'text' => 'Yes, a Swiss Water decaf from Peru. Every espresso drink is available decaf.'],
                    ['title' => 'Can I bring my own cup?', 'text' => 'Please do. You get 30 cents off every drink in your own cup.'],
                    ['title' => 'Do you take cards?', 'text' => 'Cards and phones only, no cash. Small amounts are fine.'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the hidden pre-order page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addOrder( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Order ahead',
            'title' => 'Order Ahead: Coffee and Pastry Boxes | Tamper & Crumb Leipzig',
            'path' => 'order',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
                'title' => 'Order ahead, skip the queue',
                'text' => 'Order by 18:00 and pick up from 7:30 the next morning. Your box waits at the end of the counter with your name on it.',
                'items' => [
                    [
                        'name' => 'Breakfast for two',
                        'file' => ['id' => $this->img( 'croissants-2' ), 'type' => 'file'],
                        'prices' => [['id' => 'two', 'kind' => 'once', 'currency' => 'EUR', 'amount' => 16, 'label' => '€16']],
                        'text' => 'Two drinks, two pastries.',
                        'features' => "- Two drinks of your choice\n- Two croissants or buns\n- Butter and jam\n- Ready from 7:30",
                        'url' => '#order-form',
                        'button' => 'Order breakfast for two',
                    ],
                    [
                        'name' => 'Pastry box',
                        'file' => ['id' => $this->img( 'cinnamon-buns' ), 'type' => 'file'],
                        'prices' => [['id' => 'pastry', 'kind' => 'once', 'currency' => 'EUR', 'amount' => 24, 'label' => '€24']],
                        'text' => 'Six pastries, mixed.',
                        'features' => "- Croissants and cardamom buns\n- Almond croissants\n- A slice of banana bread\n- Good for the office",
                        'url' => '#order-form',
                        'button' => 'Order a pastry box',
                        'highlight' => true,
                        'badge' => 'Best seller',
                    ],
                    [
                        'name' => 'Loaf & beans',
                        'file' => ['id' => $this->img( 'sourdough-2' ), 'type' => 'file'],
                        'prices' => [['id' => 'loaf', 'kind' => 'once', 'currency' => 'EUR', 'amount' => 19, 'label' => '€19']],
                        'text' => 'For the weekend at home.',
                        'features' => "- Spelt sourdough loaf\n- 250 g coffee of your choice\n- Ground on request\n- Saturday only",
                        'url' => '#order-form',
                        'button' => 'Order loaf and beans',
                    ],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'How it works',
                'layout' => 'horizontal',
                'items' => [
                    ['label' => 'Step 1', 'title' => 'Order', 'text' => 'Choose a box and a pick-up time by 18:00.'],
                    ['label' => 'Step 2', 'title' => 'Confirm', 'text' => 'You get an email with your pick-up code.'],
                    ['label' => 'Step 3', 'title' => 'Pick up', 'text' => 'Say your name at the end of the counter. Pay there.'],
                ],
            ]],
            ['id' => 'order-form', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Your order',
                'description' => 'You pay when you pick up. If something is sold out, we call you before you come.',
                'inputs' => [
                    ['field' => 'name', 'required' => true, 'input' => 'text'],
                    ['field' => 'email', 'required' => true, 'input' => 'text'],
                    ['field' => 'telephone', 'required' => true, 'input' => 'text'],
                    ['field' => 'Box', 'required' => true, 'input' => 'select', 'options' => "Breakfast for two\nPastry box\nLoaf & beans"],
                    ['field' => 'Pick-up day and time', 'required' => true, 'input' => 'text'],
                    ['field' => 'Drinks, milk and wishes', 'required' => false, 'input' => 'textarea'],
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
            'title' => 'Privacy Policy | Tamper & Crumb',
            'path' => 'privacy',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "# Privacy policy\n\n## Who is responsible\n\nTamper & Crumb Kaffeerösterei & Bäckerei GmbH, Karl-Heine-Straße 42, 04229 Leipzig, privacy@tamperandcrumb.example.\n\n## Orders and requests\n\nWhen you pre-order, book a class or ask for catering, we use your details to handle your request (Art. 6 (1) (b) GDPR). Order data is deleted after the legal retention periods, requests without a booking after six months.\n\n## Subscriptions\n\nFor coffee subscriptions, we store your address and delivery preferences for as long as your subscription runs. Payments are handled by our payment provider, we never see your card details.\n\n## This website\n\nThe website doesn't use tracking or advertising cookies. Our server stores technical access data such as the IP address for seven days to protect against attacks. The map is loaded from OpenStreetMap only after you open it.\n\n## Your rights\n\nYou have the right to access, rectification, erasure, restriction of processing and data portability, and you can lodge a complaint with the Saxon Data Protection Commissioner.\n\nThis is a demo website for the Brew theme. Tamper & Crumb is a fictional café.",
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the story page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addStory( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Story',
            'title' => 'Our Story: A Roastery and Bakery in Leipzig-Plagwitz | Tamper & Crumb',
            'path' => 'story',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'A barista and a baker walk into a garage',
                'subtitle' => 'Since 2014',
                'text' => 'Samuel roasted coffee in a popcorn machine, Lena baked bread for half the street. In 2014 they rented an old car workshop in Plagwitz and opened at seven the next morning.',
                'files' => [['id' => $this->arch( 'storefront' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'How we got here',
                'layout' => 'vertical',
                'items' => [
                    ['label' => '2014', 'title' => 'The garage', 'text' => 'Eight seats, a second-hand lever machine and croissants from a home oven.'],
                    ['label' => '2016', 'title' => 'Our own roaster', 'text' => 'A 5 kg drum roaster moves into the back room. The neighbours love the smell.'],
                    ['label' => '2019', 'title' => 'First direct trade', 'text' => 'We buy our first lot from Finca El Nevado in Colombia.'],
                    ['label' => '2021', 'title' => 'The bakery', 'text' => 'A stone deck oven and our own stone mill for Saxon heritage grains.'],
                    ['label' => '2024', 'title' => 'Ten years', 'text' => 'A bigger roaster, a terrace on the canal and 18 people in the team.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'roaster' ), 'type' => 'file'],
                'position' => 'grid-end',
                'ratio' => '1-1',
                'text' => "## What we believe in\n\nCoffee is a fruit, bread is a craft and a café is the living room of a neighbourhood. Everything else follows from that.\n\n- **Seasonal:** we roast what is fresh, not what is always available\n- **Transparent:** we publish the prices we pay to farmers\n- **Local:** grain from Saxony, milk from a dairy 30 km away\n- **Low waste:** day-old bread becomes croutons and crumble",
            ]],
            $this->team(),
            $this->ethos(),
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Join the team',
                'text' => 'We are always looking for people who care about coffee, bread and their guests.',
                'buttons' => [
                    ['label' => 'Open positions', 'url' => '/careers'],
                    ['label' => 'Visit us', 'url' => '/visit'],
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
            'title' => 'Visit Us: Opening Hours and Directions | Tamper & Crumb Leipzig',
            'path' => 'visit',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Come in, the kettle is on',
                'subtitle' => 'Visit us',
                'text' => 'Forty seats inside, twenty on the terrace by the Karl-Heine canal. No reservations, except for groups of eight or more.',
                'buttons' => [
                    ['label' => 'Get directions', 'url' => 'https://www.openstreetmap.org/?mlat=51.3330&mlon=12.3390#map=17/51.3330/12.3390'],
                    ['label' => 'Call us', 'url' => 'tel:+493412345670'],
                ],
                'files' => [['id' => $this->arch( 'terrace' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Opening hours',
                'header' => 'row+col',
                'table' => [
                    ['', 'Café', 'Breakfast', 'Bakery counter'],
                    ['Monday – Friday', '7:30 – 17:00', 'until 13:00', '7:30 – 17:00'],
                    ['Saturday', '8:00 – 17:00', 'until 14:00', '8:00 – 15:00'],
                    ['Sunday', '9:00 – 16:00', 'until 14:00', '9:00 – 13:00'],
                    ['Holidays', '9:00 – 16:00', 'until 14:00', 'closed'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Inside the café',
                'columns' => '3',
                'cards' => [
                    ['title' => 'Window bar', 'text' => 'Laptops welcome on weekdays, with sockets at every seat and free Wi-Fi.', 'file' => ['id' => $this->img( 'window-seat' ), 'type' => 'file']],
                    ['title' => 'Big table', 'text' => 'A long oak table for groups and Sunday breakfasts with the family.', 'file' => ['id' => $this->img( 'interior-1' ), 'type' => 'file']],
                    ['title' => 'Canal terrace', 'text' => 'From April to October, with blankets for chilly mornings.', 'file' => ['id' => $this->img( 'terrace-2' ), 'type' => 'file']],
                ],
            ]],
            $this->map( 'Find us' ),
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Before you come',
                'items' => [
                    ['title' => 'Is the café accessible?', 'text' => 'Yes. There is a ramp at the side entrance and an accessible toilet.'],
                    ['title' => 'Can I bring my dog?', 'text' => 'Dogs are welcome inside and on the terrace. Water bowls are by the door.'],
                    ['title' => 'Is there parking?', 'text' => 'Street parking is limited. Tram 14 stops at Felsenkeller, two minutes away, and there are bike racks in front.'],
                    ['title' => 'Do you take reservations?', 'text' => 'Only for groups of eight or more. Call us a day ahead.'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the wholesale page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addWholesale( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Wholesale',
            'title' => 'Wholesale Coffee for Cafés, Restaurants and Offices | Tamper & Crumb',
            'path' => 'wholesale',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Serve coffee your guests remember',
                'subtitle' => 'Wholesale',
                'text' => 'More than 40 cafés, restaurants and offices in Saxony pour our coffee. We roast to order, train your team and help with your equipment.',
                'buttons' => [
                    ['label' => 'Request samples', 'url' => '#wholesale-request'],
                ],
                'files' => [['id' => $this->arch( 'coffee-sacks' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'layout' => 'figures',
                'cards' => [
                    ['title' => '40+', 'text' => 'Wholesale partners in Saxony'],
                    ['title' => '48 h', 'text' => 'From roasting to your door'],
                    ['title' => '12 t', 'text' => 'Coffee roasted every year'],
                    ['title' => '1 kg', 'text' => 'Minimum order, no contract'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'What you get',
                'columns' => '3',
                'cards' => [
                    ['title' => 'Coffee roasted for you', 'text' => 'A house espresso developed with you, and seasonal single origins for your filter menu.', 'file' => ['id' => $this->img( 'bag-shelf' ), 'type' => 'file']],
                    ['title' => 'Barista training', 'text' => 'Two free training sessions per year for your team, at your place or in our roastery.', 'file' => ['id' => $this->img( 'barista-machine' ), 'type' => 'file']],
                    ['title' => 'Equipment support', 'text' => 'Advice on machines and grinders, and a technician we trust when something breaks.', 'file' => ['id' => $this->img( 'espresso-machine' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'Our partners say',
                'items' => [
                    ['name' => 'Owner', 'role' => 'Bistro in Leipzig-Connewitz', 'text' => 'Our guests ask about the coffee more than about the food. Samuel tasted our espresso every week until it was right.'],
                    ['name' => 'Office manager', 'role' => 'Software company, 120 people', 'text' => 'The cargo bike brings fresh beans every Monday, and the grinder check twice a year means the coffee stays good.'],
                ],
            ]],
            ['id' => 'wholesale-request', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Request samples',
                'description' => 'Tell us about your business and we send you three coffees to try, free of charge.',
                'inputs' => [
                    ['field' => 'name', 'required' => true, 'input' => 'text'],
                    ['field' => 'Business name', 'required' => true, 'input' => 'text'],
                    ['field' => 'email', 'required' => true, 'input' => 'text'],
                    ['field' => 'telephone', 'required' => false, 'input' => 'text'],
                    ['field' => 'Type of business', 'required' => true, 'input' => 'select', 'options' => "Café\nRestaurant or bar\nHotel\nOffice\nShop\nOther"],
                    ['field' => 'Coffee per week', 'required' => false, 'input' => 'select', 'options' => "Less than 5 kg\n5–15 kg\nMore than 15 kg"],
                    ['field' => 'Your machine and grinder', 'required' => false, 'input' => 'textarea'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates (once) an arched 4:5 hero image and returns its file ID.
     *
     * @param string $key PHOTOS key
     * @return string File ID
     */
    protected function arch( string $key ) : string
    {
        return $this->cropped( $key, 960, 1200, true, [480, 960] );
    }


    /**
     * Creates the shared Tamper & Crumb footer and returns its ID.
     *
     * @return string Element ID
     */
    protected function element() : string
    {
        return $this->element ??= $this->saveElement( 'cards', 'Tamper & Crumb footer', ['columns' => '4', 'cards' => [
            ['title' => 'Café', 'text' => "- [Menu](/menu)\n- [Order ahead](/order)\n- [Visit us](/visit)\n- [Catering](/catering)"],
            ['title' => 'Coffee', 'text' => "- [Our coffees](/coffee)\n- [Subscription](/coffee#subscription)\n- [Classes](/classes)\n- [Wholesale](/wholesale)"],
            ['title' => 'Tamper & Crumb', 'text' => "- [Our story](/story)\n- [Journal](/journal)\n- [Careers](/careers)\n- [Imprint](/imprint)\n- [Privacy](/privacy)"],
            ['title' => 'Newsletter', 'text' => "New coffees and seasonal bakes, once a month.\n\n[Sign up by email](mailto:hello@tamperandcrumb.example?subject=Newsletter)"],
        ]] );
    }


    /**
     * Returns the ethos badges element.
     *
     * @return array<string, mixed> Cards content element
     */
    protected function ethos() : array
    {
        return ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
            'title' => 'Small batches, honest bread',
            'layout' => 'badges',
            'cards' => [
                ['title' => 'Roasted weekly', 'text' => 'Every Tuesday in Plagwitz', 'file' => $this->icon( 'bean' )],
                ['title' => 'Direct trade', 'text' => 'Six farms, prices published', 'file' => $this->icon( 'leaf' )],
                ['title' => 'Own bakery', 'text' => 'Heritage grains, 48 h dough', 'file' => $this->icon( 'wheat' )],
                ['title' => 'Bring your cup', 'text' => '30 cents off every drink', 'file' => $this->icon( 'cup' )],
                ['title' => 'Low waste', 'text' => 'Grounds go to city gardens', 'file' => $this->icon( 'heart' )],
            ],
        ]];
    }


    /**
     * Returns the ID of the primary café image.
     *
     * @return string File ID
     */
    protected function file() : string
    {
        return $this->img( 'latte-art' );
    }


    /**
     * Returns the Tamper & Crumb home page.
     *
     * @return Page Home page
     */
    protected function home() : Page
    {
        $elementId = $this->element();
        $fileId = $this->file();

        $config = [
            'website' => Validation::entry( 'website', ['title' => 'Tamper & Crumb'], 'config' ),
        ] + $this->logos( $this->logoFile() ) + [
            'brew::cafe' => [
                'type' => 'brew::cafe',
                'files' => [],
                'data' => [
                    'name' => 'Tamper & Crumb',
                    'business-type' => 'CafeOrCoffeeShop',
                    'street-address' => 'Karl-Heine-Straße 42',
                    'postal-code' => '04229',
                    'locality' => 'Leipzig',
                    'country' => 'DE',
                    'telephone' => '+49 341 2345 670',
                    'email' => 'hello@tamperandcrumb.example',
                    'announcement' => 'Freshly roasted every Tuesday',
                    'order' => '/order',
                    'menu' => '/menu',
                    'cuisine' => 'Coffee, Bakery, Breakfast',
                    'price-range' => '€€',
                    'action-bar' => true,
                    'hours' => [
                        ['id' => 'mon', 'day' => 'Monday', 'opens' => '07:30', 'closes' => '17:00'],
                        ['id' => 'tue', 'day' => 'Tuesday', 'opens' => '07:30', 'closes' => '17:00'],
                        ['id' => 'wed', 'day' => 'Wednesday', 'opens' => '07:30', 'closes' => '17:00'],
                        ['id' => 'thu', 'day' => 'Thursday', 'opens' => '07:30', 'closes' => '17:00'],
                        ['id' => 'fri', 'day' => 'Friday', 'opens' => '07:30', 'closes' => '17:00'],
                        ['id' => 'sat', 'day' => 'Saturday', 'opens' => '08:00', 'closes' => '17:00'],
                        ['id' => 'sun', 'day' => 'Sunday', 'opens' => '09:00', 'closes' => '16:00'],
                    ],
                ],
            ],
        ];

        $content = [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Roasted on Tuesdays. Poured every day.',
                'subtitle' => 'Coffee roasters & bakery in Leipzig',
                'text' => 'Seasonal coffee from farms we know by name, pastries from our own oven and a table by the canal. Open every day from half past seven.',
                'buttons' => [
                    ['label' => 'See the menu', 'url' => '/menu'],
                    ['label' => 'Order ahead', 'url' => '/order'],
                ],
                'files' => [['id' => $this->arch( 'latte-pour' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'layout' => 'figures',
                'cards' => [
                    ['title' => '2014', 'text' => 'Pouring coffee in Plagwitz'],
                    ['title' => '6', 'text' => 'Farms we buy from directly'],
                    ['title' => '48 h', 'text' => 'Of fermentation in every loaf'],
                    ['title' => '7:30', 'text' => 'Doors open, every weekday'],
                ],
            ]],
            $this->roasting( 'What\'s roasting', false ),
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'croissants' ), 'type' => 'file'],
                'position' => 'grid-end',
                'ratio' => '1-1',
                'text' => "## Baked here, *before sunrise*\n\nOur bakers start at five, so the first croissants come out of the oven when we open the door. We laminate with Saxon butter and bake our bread with spelt, einkorn and rye from farms near Grimma.\n\n- **Butter croissant:** 27 layers, 3 days of work\n- **Cardamom bun:** our best seller since 2014\n- **Spelt sourdough:** to take home, from 7:30\n\n[Why we bake with heritage grains](/grain-guide)",
            ]],
            ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
                'title' => 'Signature drinks',
                'text' => 'Our baristas\' favourites. Every drink is available with oat milk and decaf.',
                'items' => [
                    [
                        'name' => 'Flat white',
                        'file' => ['id' => $this->img( 'flat-white' ), 'type' => 'file'],
                        'prices' => [['id' => 'flat-white', 'amount' => 3.9, 'label' => '€3.90']],
                        'text' => 'Double ristretto, silky milk.',
                        'features' => "- Morning Ritual blend\n- Milk chocolate and hazelnut\n- Our most ordered drink",
                        'url' => '/menu',
                        'button' => 'Full menu',
                    ],
                    [
                        'name' => 'Espresso tonic',
                        'file' => ['id' => $this->img( 'iced-coffee' ), 'type' => 'file'],
                        'prices' => [['id' => 'tonic', 'amount' => 4.6, 'label' => '€4.60']],
                        'text' => 'Kenya espresso over tonic and ice.',
                        'features' => "- Blackcurrant and grapefruit\n- Orange zest\n- Perfect on the terrace",
                        'url' => '/menu',
                        'button' => 'Full menu',
                        'highlight' => true,
                        'badge' => 'Summer favourite',
                    ],
                    [
                        'name' => 'V60 pour-over',
                        'file' => ['id' => $this->img( 'pour-over' ), 'type' => 'file'],
                        'prices' => [['id' => 'v60', 'amount' => 4.8, 'label' => '€4.80']],
                        'text' => 'Single origin, brewed by hand.',
                        'features' => "- Changes every week\n- Ask us for tasting notes\n- Learn it in our classes",
                        'url' => '/v60-guide',
                        'button' => 'Our V60 recipe',
                    ],
                ],
            ]],
            $this->ethos(),
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'Our regulars say',
                'items' => $this->reviews(),
            ]],
            ['id' => Utils::uid(), 'type' => 'blog', 'group' => 'main', 'data' => [
                'title' => 'From the journal',
                'layout' => 'cards',
                'parent-page' => ['value' => $this->journalId, 'label' => 'Journal'],
                'order' => '_lft',
                'limit' => 3,
            ]],
            $this->map( 'Visit us' ),
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Fresh beans, every month',
                'text' => 'Roasted on Tuesday, in your letterbox by Friday. Pause or cancel any time, shipping in Germany is free.',
                'buttons' => [
                    ['label' => 'Start a subscription', 'url' => '/coffee#subscription'],
                    ['label' => 'Our coffees', 'url' => '/coffee'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        $meta = [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => 'Tamper & Crumb in Leipzig-Plagwitz: specialty coffee roasted every Tuesday, pastries and sourdough from our own bakery and breakfast until 13:00.',
                'keywords' => 'café Leipzig, coffee roastery Leipzig, specialty coffee, bakery Leipzig, breakfast Leipzig, Plagwitz, flat white, sourdough',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => 'Tamper & Crumb | Coffee Roasters & Bakery in Leipzig',
                'description' => 'Roasted on Tuesdays. Poured every day.',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        return $this->saveRoot( 'Tamper & Crumb | Coffee Roasters & Bakery in Leipzig', $config, $meta, $content, $elementId, $fileId );
    }


    /**
     * Creates a roast-colored line icon once and returns its file reference.
     *
     * @param string $name Icon name: bean, cup, heart, leaf or wheat
     * @return array<string, string> File reference
     */
    protected function icon( string $name ) : array
    {
        $paths = [
            'bean' => '<ellipse cx="12" cy="12" rx="6.5" ry="9" transform="rotate(35 12 12)"/><path d="M8.5 18.5c1-3 5-4 5.5-7s-1-4.5 1.5-7"/>',
            'cup' => '<path d="M4 9h13v5a6 6 0 0 1-6 6h-1a6 6 0 0 1-6-6z"/><path d="M17 11h1.5a2.5 2.5 0 0 1 0 5H17"/><path d="M8 3c-1 1.5 1 2.5 0 4"/><path d="M12 3c-1 1.5 1 2.5 0 4"/>',
            'heart' => '<path d="M12 20s-7-4.5-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.5-7 10-7 10z"/>',
            'leaf' => '<path d="M5 19c0-9 6-14 15-14 0 9-5 15-14 15"/><path d="M5 19c3-4 6-7 10-9"/>',
            'wheat' => '<path d="M12 22V8"/><path d="M12 8c-2-1-3-3-2-5 2 1 3 3 2 5z"/><path d="M12 8c2-1 3-3 2-5-2 1-3 3-2 5z"/><path d="M12 13c-2.5 0-4-1.5-4-4 2.5 0 4 1.5 4 4z"/><path d="M12 13c2.5 0 4-1.5 4-4-2.5 0-4 1.5-4 4z"/><path d="M12 18c-2.5 0-4-1.5-4-4 2.5 0 4 1.5 4 4z"/><path d="M12 18c2.5 0 4-1.5 4-4-2.5 0-4 1.5-4 4z"/>',
        ];

        $this->icons[$name] ??= $this->svgFile(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#8A4B2A" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">' . $paths[$name] . '</svg>',
            'icon-' . $name . '.svg',
            ucfirst( $name ) . ' icon',
            'Roast brown line icon: ' . $name,
            true,
        );

        return ['id' => $this->icons[$name], 'type' => 'file'];
    }


    /**
     * Creates the journal overview page and returns it.
     *
     * @param Page $home Home page
     * @return Page Journal page
     */
    protected function journal( Page $home ) : Page
    {
        return $this->page( [
            'id' => $this->journalId,
            'lang' => 'en',
            'name' => 'Journal',
            'title' => 'Journal: Brew Guides, Origins and Baking | Tamper & Crumb',
            'path' => 'journal',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Notes from the roastery and the oven',
                'subtitle' => 'Journal',
                'text' => 'Brew guides, stories from the farms we buy from and what our bakers are working on.',
            ]],
            ['id' => 'journal-list', 'type' => 'blog', 'group' => 'main', 'data' => [
                'layout' => 'cards',
                'parent-page' => ['value' => $this->journalId, 'label' => 'Journal'],
                'order' => '_lft',
                'limit' => 12,
            ]],
        ], $home );
    }


    /**
     * Creates the Tamper & Crumb SVG logo and returns its file ID.
     *
     * @return string File ID
     */
    protected function logoFile() : string
    {
        if( !isset( $this->logoFile ) )
        {
            $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 80" role="img" aria-labelledby="title desc">
  <title id="title">Tamper &amp; Crumb logo</title>
  <desc id="desc">Round espresso brown badge with a cream coffee bean beside the Tamper &amp; Crumb wordmark</desc>
  <circle cx="38" cy="40" r="32" fill="#2A211C"/>
  <circle cx="38" cy="40" r="27" fill="none" stroke="#C8A165" stroke-width="1" stroke-dasharray="2 3"/>
  <ellipse cx="38" cy="40" rx="10" ry="15" transform="rotate(30 38 40)" fill="#C8A165"/>
  <path d="M33 52c2-5 8-6 9-12s-2-7 1-12" fill="none" stroke="#2A211C" stroke-width="2" stroke-linecap="round"/>
  <text x="84" y="45" fill="#2A211C" font-family="'Iowan Old Style', 'Palatino Linotype', Palatino, Georgia, serif" font-size="32" font-style="italic">Tamper <tspan fill="#8A4B2A">&amp;</tspan> Crumb</text>
  <text x="85" y="64" fill="#6B5D52" font-family="ui-monospace, 'SF Mono', Menlo, Consolas, monospace" font-size="10.5" letter-spacing="3">COFFEE ROASTERS · BAKERY</text>
</svg>
SVG;

            $this->logoFile = $this->svgFile(
                $svg,
                'tamper-crumb-logo.svg',
                'Tamper & Crumb logo',
                'Round espresso brown badge with a cream coffee bean beside the Tamper & Crumb wordmark',
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
            'text' => "**Tamper & Crumb**\nKarl-Heine-Straße 42 · 04229 Leipzig-Plagwitz\n\n**Opening hours**\nMonday to Friday 7:30–17:00\nSaturday 8:00–17:00\nSunday 9:00–16:00\n\n**Call**\n+49 341 2345 670\n\n**Getting here**\nTram 14 to Felsenkeller, two minutes on foot. Bike racks in front, the terrace is on the canal side.",
            'location' => [
                'latitude' => 51.3330,
                'longitude' => 12.3390,
                'zoom' => 16,
            ],
            'button' => 'Open in OpenStreetMap',
        ]];
    }


    /**
     * Creates a Brew demo page below the given parent and returns it.
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

        return $this->savePage( $data, $content, $parent, $elementId, $fileId, $footer, 'Tamper & Crumb, café Leipzig, coffee roastery, specialty coffee, bakery, breakfast, Plagwitz' );
    }


    /**
     * Builds the Brew demo page tree.
     */
    protected function pages() : void
    {
        $this->journalId = (string) Str::uuid7();
        $home = $this->home();

        $this->addMenu( $home )
            ->addCoffee( $home )
            ->addClasses( $home )
            ->addCatering( $home )
            ->addWholesale( $home )
            ->addStory( $home )
            ->addVisit( $home )
            ->addJournal( $home )
            ->addOrder( $home )
            ->addCareers( $home )
            ->addImprint( $home )
            ->addPrivacy( $home );
    }


    /**
     * Creates a journal post below the journal page.
     *
     * @param Page $parent Journal page
     * @param array<string, string> $data Page name, title and path
     * @param string $title Article headline
     * @param string $text Article text
     * @param string $cover PHOTOS key of the cover image
     * @param array<int, array<string, string>> $facts Key facts as figure cards
     * @param array<int, array<string, string>> $steps Steps of the process
     * @param array<int, array<string, string>> $questions Frequently asked questions
     * @return Page Created page
     */
    protected function post( Page $parent, array $data, string $title, string $text, string $cover,
        array $facts, array $steps, array $questions ) : Page
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
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'Step by step',
                'layout' => 'vertical',
                'items' => $steps,
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Questions from our guests',
                'items' => $questions,
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Taste it for yourself',
                'text' => 'Come by the café, or let us send you a bag of this season\'s coffee.',
                'buttons' => [
                    ['label' => 'Our coffees', 'url' => '/coffee'],
                    ['label' => 'Visit us', 'url' => '/visit'],
                ],
            ]],
        ], $parent );
    }


    /**
     * Returns the regulars' reviews.
     *
     * @return array<int, array<string, string>> Testimonial items
     */
    protected function reviews() : array
    {
        return [
            ['name' => 'Jana', 'role' => 'Regular since 2015', 'text' => 'I have tried every café in Leipzig, and I still walk twenty minutes for their flat white and a cardamom bun.'],
            ['name' => 'Tobias', 'role' => 'Coffee subscriber', 'text' => 'The Roaster\'s choice box has made me a better home barista. The recipe cards actually work.'],
            ['name' => 'Mira', 'role' => 'Latte art class', 'text' => 'Three hours, twenty lattes and my first real tulip. The trainers were patient and very funny.'],
        ];
    }


    /**
     * Returns the coffee card element.
     *
     * @param string $title Element headline
     * @param bool $all Whether to show all six coffees instead of four
     * @return array<string, mixed> Cards content element
     */
    protected function roasting( string $title, bool $all ) : array
    {
        $coffees = [
            ['Ethiopia Guji Hambela', "**Washed · €16.50**\n`jasmine` `peach` `bergamot`", 'beans', '/coffee'],
            ['Colombia Huila El Nevado', "**Washed · €14.00**\n`red apple` `caramel` `cocoa`", 'coffee-cherries', '/farm-visit'],
            ['Kenya Nyeri Karogoto', "**Washed · €17.00**\n`blackcurrant` `grapefruit` `cane sugar`", 'roaster-2', '/coffee'],
            ['Morning Ritual blend', "**Blend · €12.50**\n`milk chocolate` `hazelnut` `toffee`", 'espresso-shot', '/coffee'],
            ['Peru Swiss Water decaf', "**Decaf · €13.00**\n`almond` `honey` `cola`", 'beans-3', '/coffee'],
            ['Rwanda Huye Mountain', "**Natural · €15.50**\n`strawberry` `cacao nib` `black tea`", 'green-beans', '/coffee'],
        ];

        return ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
            'title' => $title,
            'columns' => $all ? '3' : '4',
            'cards' => array_map( fn( $coffee ) => [
                'title' => $coffee[0],
                'text' => $coffee[1],
                'url' => $coffee[3],
                'file' => ['id' => $this->img( $coffee[2] ), 'type' => 'file'],
            ], array_slice( $coffees, 0, $all ? 6 : 4 ) ),
        ]];
    }


    /**
     * Returns the team card element.
     *
     * @return array<string, mixed> Cards content element
     */
    protected function team() : array
    {
        $people = [
            ['Samuel Mensah', 'barista-5', "**Co-founder · Head of coffee**\nRoasts every Tuesday and cups every lot before we buy it."],
            ['Lena Vogt', 'baker', "**Co-founder · Head baker**\nLaminates the croissants and looks after a 10-year-old sourdough starter."],
            ['Jonas Richter', 'barista-2', "**Roaster**\nRuns the 15 kg roaster and packs your subscription on Wednesdays."],
            ['Amaru Quispe', 'barista-3', "**Head barista · Trainer**\nTeaches our latte art classes and dials in the espresso every morning."],
        ];

        return ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
            'title' => 'The people behind the counter',
            'columns' => '4',
            'cards' => array_map( fn( $person ) => [
                'title' => $person[0],
                'text' => $person[2],
                'file' => ['id' => $this->cropped( $person[1], 800, 1000 ), 'type' => 'file'],
            ], $people ),
        ]];
    }
}
