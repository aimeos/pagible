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
 * Tonic theme demo for the fictional Brass Hour cocktail bar in Hamburg.
 */
class TonicDemo extends AbstractDemo
{
    /** @var array<string, string> Meta descriptions keyed by page path */
    protected const DESCRIPTIONS = [
        'bar-notes' => 'Brass Hour bar notes: cocktail recipes, stories from behind the bar and what our team is drinking this season.',
        'book' => 'Book a table at Brass Hour in Hamburg-Altona for two to eight guests. Walk-ins are always welcome at the bar.',
        'careers' => 'Work at Brass Hour in Hamburg: bartenders, barbacks and hosts with fair pay, closing times before three and paid spirits training.',
        'events' => 'What\'s on at Brass Hour in Hamburg: live jazz on Wednesdays, vinyl nights, guest bartenders and Negroni Sundays.',
        'gift-cards' => 'Brass Hour gift cards for drinks, masterclasses and private tastings in Hamburg, valid for three years.',
        'ice-program' => 'Why clear ice matters: how Brass Hour freezes, cuts and carves its own ice for stirred cocktails and highballs.',
        'imprint' => 'Legal notice of Brass Hour Bar GmbH, Hamburg.',
        'low-abv' => 'Low and no alcohol cocktails at Brass Hour: how we build drinks with full flavour and less booze.',
        'masterclasses' => 'Cocktail masterclasses in Hamburg: classic cocktails, gin and botanicals, and whisky tastings in small groups at Brass Hour.',
        'menu' => 'The Brass Hour menu: signature cocktails, classics, wine by the glass, low and no alcohol drinks and bar snacks until late.',
        'negroni-at-home' => 'How to make a great Negroni at home: ratio, ice, stirring and the orange peel, with the Brass Hour house recipe.',
        'privacy' => 'Privacy policy of Brass Hour Bar GmbH, Hamburg.',
        'private-events' => 'Private events at Brass Hour in Hamburg: the Vault room for up to 24 guests, full buyouts for 120 and bar takeovers.',
        'story' => 'The story of Brass Hour: a cocktail bar in a former brass foundry cellar in Hamburg-Altona since 2016.',
        'visit' => 'Visit Brass Hour on Paul-Roosen-Straße in Hamburg: opening hours, directions, walk-ins, dress code and accessibility.',
    ];

    /**
     * Curated Unsplash photos used by the bar demo.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    protected const PHOTOS = [
        'amber-cocktail' => ['photo-1761361371739-546f12a9cee4', 'Brass Hour Sour', 'Amber cocktail with an orange peel on the bar'],
        'back-bar' => ['photo-1696062985889-de626efe0148', 'Back bar', 'Shelves of bottles lit warmly behind the bar'],
        'bar-counter' => ['photo-1726835498689-b4f6dbcdbdfb', 'Bar counter', 'Long bar counter with stools in warm light'],
        'bar-room' => ['photo-1709548145082-04d0cde481d4', 'Bar room', 'Dimly lit cocktail bar with a long counter and warm lamps'],
        'bartender-1' => ['photo-1632987795947-fed1e9359f27', 'Head bartender', 'Man behind the bar holding a bottle'],
        'bartender-2' => ['photo-1681412330003-2ce8e52f4aa8', 'Bar manager', 'Woman pouring a drink behind the bar'],
        'bartender-3' => ['photo-1578004612790-5c32842373b1', 'Bartender', 'Man shaking a cocktail shaker'],
        'bartender-4' => ['photo-1632995561688-d382b4c5c735', 'Bartender', 'Woman preparing a cocktail at the bar'],
        'bartender-pouring' => ['photo-1647776112336-72f4c30fafc1', 'Pouring', 'Bartender pouring a cocktail into a glass'],
        'bartender-shake' => ['photo-1567850809572-96538630a0ec', 'Shaking', 'Bartender shaking a cocktail behind the bar'],
        'booth' => ['photo-1578911489158-334e5cd2a051', 'The Vault', 'Private bar room with leather seating and low lights'],
        'bottles' => ['photo-1758580815638-0f866a8a4a64', 'Bottles', 'Rows of spirit bottles on a lit shelf'],
        'bottles-2' => ['photo-1577695464142-e3a24f4e88f2', 'Whisky shelf', 'Whisky bottles lined up on a wooden shelf'],
        'candles' => ['photo-1598373728278-73ad46eaa0da', 'Candlelight', 'Candles on a bar table in the dark'],
        'cellar' => ['photo-1769766407780-109f553a136e', 'Cellar bar', 'Entrance to a cellar bar with a neon sign at night'],
        'champagne' => ['photo-1652272160614-594d88786592', 'Champagne', 'Glasses of champagne being poured'],
        'cheers' => ['photo-1560433956-b2847671bb86', 'Cheers', 'Friends clinking glasses at the bar'],
        'door' => ['photo-1790561032578-65ce234fc78e', 'The door', 'Heavy metal door of the bar at night'],
        'group' => ['photo-1681641090195-5adb0c54eeb0', 'Table of friends', 'Group of friends at a table with cocktails'],
        'ice' => ['photo-1578323851363-cf6a1a6afbb6', 'Clear ice', 'Block of crystal-clear ice'],
        'jazz' => ['photo-1783496116757-cf1fd14a4621', 'Jazz night', 'Jazz trio playing in a dim bar'],
        'lounge' => ['photo-1640902106532-47dd3a2e833e', 'Lounge', 'Lounge with velvet seating and low lights'],
        'martini' => ['photo-1575023782549-62ca0d244b39', 'Martini', 'Dry martini with an olive in a coupe'],
        'mocktail' => ['photo-1595977514600-72cbc8376c38', 'Low and no', 'Alcohol-free cocktail with fresh garnish'],
        'negroni' => ['photo-1551751299-1b51cab2694c', 'Negroni', 'Negroni on the rocks with an orange slice'],
        'negroni-2' => ['photo-1586338211598-e2d64cf97e28', 'Negroni', 'Red Negroni cocktail with orange peel'],
        'old-fashioned' => ['photo-1514362545857-3bc16c4c7d1b', 'Old fashioned', 'Old fashioned over a large ice cube'],
        'olives' => ['photo-1774988867921-90838a39a869', 'Martini and olives', 'Cocktail served with a dish of olives'],
        'peel' => ['photo-1761393212023-2945a7310c41', 'Orange peel', 'Spiral of orange peel over a cocktail'],
        'red-cocktail' => ['photo-1791401176011-ba8befcb28b9', 'Altona Spritz', 'Red cocktail with ice and orange peel'],
        'snack-board' => ['photo-1770670644198-a9eb1df927c6', 'Bar snacks', 'Board with cheese, ham and bar snacks'],
        'strain' => ['photo-1470337458703-46ad1756a187', 'Stirred down', 'Cocktail strained over a big clear ice cube'],
        'toast' => ['photo-1699730148588-42aabafe9c72', 'A toast', 'Group of friends raising their glasses'],
        'two-cocktails' => ['photo-1767510533137-37adb7df4fe3', 'Two cocktails', 'Two cocktails on the bar under a brass lamp'],
        'vinyl' => ['photo-1603048588665-791ca8aea617', 'Vinyl night', 'Record spinning on a turntable'],
        'wine' => ['photo-1469234496837-d0101f54be3e', 'Wine', 'Glasses of red wine on a table'],
    ];

    private string $element;
    private string $notesId;
    /** @var array<string, string> */
    private array $icons = [];
    private string $logoFile;


    /**
     * Creates the hidden table booking page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addBook( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Book a table',
            'title' => 'Book a Table | Brass Hour Cocktail Bar Hamburg',
            'path' => 'book',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Save yourself a seat',
                'subtitle' => 'Book a table',
                'text' => 'We keep half of our tables and the whole bar counter for walk-ins. For everything else, tell us when you come and we hold a table for you for 15 minutes.',
                'files' => [['id' => $this->portrait( 'candles' ), 'type' => 'file']],
            ]],
            ['id' => 'booking-form', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Your booking',
                'description' => 'Bookings for two to eight guests. You get a confirmation by email, usually within the hour.',
                'inputs' => [
                    ['field' => 'name', 'required' => true, 'input' => 'text'],
                    ['field' => 'email', 'required' => true, 'input' => 'text'],
                    ['field' => 'telephone', 'required' => true, 'input' => 'text'],
                    ['field' => 'Date', 'required' => true, 'input' => 'text'],
                    ['field' => 'Time', 'required' => true, 'input' => 'select', 'options' => "18:00\n19:00\n20:00\n21:00\n22:00"],
                    ['field' => 'Guests', 'required' => true, 'input' => 'select', 'options' => "2\n3\n4\n5\n6\n7\n8"],
                    ['field' => 'Occasion or wishes', 'required' => false, 'input' => 'textarea'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Booking questions',
                'items' => [
                    ['title' => 'How long can we stay?', 'text' => 'Tables are yours for two and a half hours. After that, you are welcome to move to the bar.'],
                    ['title' => 'Do you charge for no-shows?', 'text' => 'No. Please call or email us if your plans change, so someone else can have the table.'],
                    ['title' => 'More than eight guests?', 'text' => 'Have a look at our private events. The Vault seats up to 24.'],
                ],
            ]],
        ], $home );

        return $this;
    }


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
            'title' => 'Bar Jobs in Hamburg: Bartender, Barback, Host | Brass Hour',
            'path' => 'careers',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Good drinks need good people',
                'subtitle' => 'Careers',
                'text' => 'We are a team of 14 bartenders, barbacks and hosts. Last orders are never after half past two, and everyone gets a paid training budget.',
                'buttons' => [
                    ['label' => 'Send your application', 'url' => 'mailto:jobs@brasshour.example'],
                ],
                'files' => [['id' => $this->portrait( 'bartender-shake' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'bar-counter' ), 'type' => 'file'],
                'position' => 'grid-end',
                'ratio' => '1-1',
                'text' => "## What you get\n\n- A wage above the hospitality tariff and a fair share of the tips\n- Four nights a week, with two days off in a row\n- WSET and spirits courses, paid by us\n- A taxi home after every closing shift\n- Your own drink on the menu after a year",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Open positions',
                'columns' => '3',
                'cards' => [
                    ['title' => 'Bartender (m/f/d)', 'text' => "**Full or part time**\nYou know your classics and love talking to guests. We teach you the rest."],
                    ['title' => 'Barback (m/f/d)', 'text' => "**Part time, Thu to Sat**\nIce, glassware and prep for the bar. The best way into a career with us."],
                    ['title' => 'Host (m/f/d)', 'text' => "**Part time, evenings**\nYou greet every guest at the door and keep the room running smoothly."],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Come by for a drink',
                'text' => 'Send us a few lines about yourself. A CV is welcome but not required, and every application gets an answer within a week.',
                'buttons' => [
                    ['label' => 'Send your application', 'url' => 'mailto:jobs@brasshour.example'],
                    ['label' => 'Read our story', 'url' => '/story'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the events page and its sub-pages below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addEvents( Page $home ) : static
    {
        $events = $this->page( [
            'lang' => 'en',
            'name' => 'What\'s on',
            'title' => 'What\'s On: Live Jazz, Vinyl Nights and Guest Shifts | Brass Hour Hamburg',
            'path' => 'events',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Something on, every night',
                'subtitle' => 'What\'s on',
                'text' => 'Live jazz in the back room, records on the house turntable and guest bartenders from the best bars in Europe. Entry is always free.',
                'buttons' => [
                    ['label' => 'Book a table', 'url' => '/book'],
                ],
                'files' => [['id' => $this->portrait( 'jazz' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'Every week',
                'layout' => 'vertical',
                'items' => [
                    ['label' => 'Tuesday', 'title' => 'Industry night', 'text' => 'Hospitality staff get 20 percent off. Show us your payslip or your burns.'],
                    ['label' => 'Wednesday', 'title' => 'Live jazz', 'text' => 'The Elbe Street Trio plays two sets from 20:30. Standards, bossa and the odd surprise.'],
                    ['label' => 'Thursday', 'title' => 'Vinyl night', 'text' => 'Bring a record, we play it. The best pick of the night drinks on the house.'],
                    ['label' => 'Fri & Sat', 'title' => 'Late bar', 'text' => 'Soul and disco from 22:00 and the kitchen open until one.'],
                    ['label' => 'Sunday', 'title' => 'Negroni Sunday', 'text' => 'Every Negroni and its cousins for €9, all evening long.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Coming up',
                'columns' => '3',
                'cards' => [
                    ['title' => 'Guest shift: Paris', 'text' => "**Thursday, 6 November**\nTwo bartenders from a famous Marais speakeasy take over the bar for one night.", 'file' => ['id' => $this->img( 'bartender-pouring' ), 'type' => 'file']],
                    ['title' => 'Mezcal & music', 'text' => "**Friday, 14 November**\nA tasting of six mezcals with a producer from Oaxaca, followed by a DJ set.", 'file' => ['id' => $this->img( 'vinyl' ), 'type' => 'file']],
                    ['title' => 'Champagne Christmas', 'text' => "**Saturday, 20 December**\nGrower champagne by the glass and a festive menu of sparkling cocktails.", 'file' => ['id' => $this->img( 'champagne' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Never miss a night',
                'text' => 'Our newsletter tells you about guest shifts and tastings before they sell out. Once a month, never more.',
                'buttons' => [
                    ['label' => 'Sign up by email', 'url' => 'mailto:hello@brasshour.example?subject=Newsletter'],
                    ['label' => 'Book a table', 'url' => '/book'],
                ],
            ]],
        ], $home );

        return $this->addPrivate( $events )
            ->addMasterclasses( $events )
            ->addGifts( $events );
    }


    /**
     * Creates the gift cards page below the events page.
     *
     * @param Page $parent Events page
     * @return static Same object for fluent calls
     */
    protected function addGifts( Page $parent ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Gift cards',
            'title' => 'Gift Cards for Cocktails and Masterclasses | Brass Hour Hamburg',
            'path' => 'gift-cards',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
                'title' => 'Give an evening at the bar',
                'text' => 'Our gift cards come in a black envelope with a brass seal, by post or to pick up at the bar. They are valid for three years.',
                'items' => [
                    [
                        'name' => 'Round for two',
                        'file' => ['id' => $this->img( 'two-cocktails' ), 'type' => 'file'],
                        'prices' => [['id' => 'round', 'kind' => 'once', 'currency' => 'EUR', 'amount' => 50, 'label' => '€50']],
                        'text' => 'Four cocktails, one evening.',
                        'features' => "- Any drink on the menu\n- Bar snacks included\n- Table booking on request\n- Valid for three years",
                        'url' => 'mailto:hello@brasshour.example?subject=Gift%20card',
                        'button' => 'Order round for two',
                    ],
                    [
                        'name' => 'Masterclass',
                        'file' => ['id' => $this->img( 'bartender-shake' ), 'type' => 'file'],
                        'prices' => [['id' => 'class', 'kind' => 'once', 'currency' => 'EUR', 'amount' => 140, 'label' => '€140']],
                        'text' => 'Classic cocktails for two.',
                        'features' => "- Three hours behind the bar\n- Six cocktails each\n- Recipe booklet and jigger\n- Free choice of date",
                        'url' => 'mailto:hello@brasshour.example?subject=Gift%20card',
                        'button' => 'Order a masterclass',
                        'highlight' => true,
                        'badge' => 'Most given',
                    ],
                    [
                        'name' => 'Open value',
                        'file' => ['id' => $this->img( 'champagne' ), 'type' => 'file'],
                        'prices' => [['id' => 'value', 'kind' => 'once', 'currency' => 'EUR', 'amount' => 25, 'label' => 'from €25']],
                        'text' => 'You choose the amount.',
                        'features' => "- In steps of €25, any amount\n- For drinks, food and events\n- Partial use possible\n- Valid for three years",
                        'url' => 'mailto:hello@brasshour.example?subject=Gift%20card',
                        'button' => 'Order a gift card',
                    ],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Gift card questions',
                'items' => [
                    ['title' => 'How fast do you send them?', 'text' => 'Orders before noon go out the same day. You can also pick them up at the bar from 18:00.'],
                    ['title' => 'Can I use a gift card online?', 'text' => 'Gift cards are redeemed at the bar and for masterclasses booked by email.'],
                    ['title' => 'Is there a digital version?', 'text' => 'Yes. We email you a PDF that you can print or forward.'],
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
            'title' => 'Imprint | Brass Hour',
            'path' => 'imprint',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "# Imprint\n\n**Brass Hour Bar GmbH**\nPaul-Roosen-Straße 18\n22767 Hamburg\nGermany\n\nTelephone: +49 40 2345 6780\nEmail: hello@brasshour.example\n\nManaging directors: Malik Osei, Ingrid Lund\nCommercial register: Hamburg Local Court, HRB 00000\nVAT ID: DE000000000\n\n## Protection of minors\n\nWe serve alcohol only to guests aged 18 and over and may ask for an ID.\n\n## Consumer dispute resolution\n\nWe are neither willing nor obliged to take part in dispute resolution proceedings before a consumer arbitration board.\n\nThis is a demo website for the Tonic theme. Brass Hour is a fictional bar and the people named are not real.",
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the bar notes page and its posts below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addNotes( Page $home ) : static
    {
        $notes = $this->notes( $home );

        $this->post( $notes, [
            'name' => 'The Negroni at home',
            'title' => 'How to Make a Great Negroni at Home: Our House Recipe',
            'path' => 'negroni-at-home',
        ], 'The Negroni at home, in three minutes',
            "Equal parts gin, Campari and sweet vermouth: the Negroni is the easiest classic to make and the easiest to get slightly wrong. Most home Negronis are too warm, too diluted or too sweet.\n\nOur house recipe leans a little drier with a dash more gin, uses a fresh bottle of vermouth from the fridge and is stirred, never shaken, over one big piece of clear ice.",
            'negroni-2',
            [
                ['title' => '35:25:25', 'text' => 'Gin, Campari, vermouth in ml'],
                ['title' => '30 s', 'text' => 'Stirring time on plenty of ice'],
                ['title' => '1', 'text' => 'One big cube, never crushed'],
            ],
            [
                ['label' => 'Step 1', 'title' => 'Chill', 'text' => 'Put a rocks glass in the freezer for ten minutes.'],
                ['label' => 'Step 2', 'title' => 'Measure', 'text' => 'Gin, Campari and vermouth into a mixing glass full of ice.'],
                ['label' => 'Step 3', 'title' => 'Stir', 'text' => 'Thirty seconds, until the glass is frosty on the outside.'],
                ['label' => 'Step 4', 'title' => 'Peel', 'text' => 'Strain over a big cube and express an orange peel on top.'],
            ],
            [
                ['title' => 'How long does vermouth keep?', 'text' => 'It is wine. Keep it in the fridge and use it within a month.'],
                ['title' => 'Which gin should I use?', 'text' => 'A classic London dry with plenty of juniper. Save the floral gins for tonic.'],
                ['title' => 'Can I batch it?', 'text' => 'Yes. Add 20 percent water, bottle it and keep it in the freezer.'],
            ],
        );

        $this->post( $notes, [
            'name' => 'Our ice program',
            'title' => 'Why Clear Ice Matters: The Brass Hour Ice Program',
            'path' => 'ice-program',
        ], 'Ninety kilos of clear ice, every day',
            "Ice is the most used ingredient behind any bar, and the most overlooked. Cloudy ice from a machine melts fast and waters a drink down before you are halfway through.\n\nWe freeze our own blocks from the top down for three days, so the air and minerals are pushed to the bottom. Then we cut them by hand into cubes, spears and spheres for every glass on the menu.",
            'ice',
            [
                ['title' => '72 h', 'text' => 'Freezing time for every block'],
                ['title' => '90 kg', 'text' => 'Of clear ice cut every day'],
                ['title' => '5', 'text' => 'Shapes for different glasses'],
            ],
            [
                ['label' => 'Day 1', 'title' => 'Fill', 'text' => 'Filtered water into insulated tubs, open only at the top.'],
                ['label' => 'Day 3', 'title' => 'Release', 'text' => 'The clear top is lifted out, the cloudy bottom is used for shaking.'],
                ['label' => 'Daily', 'title' => 'Cut', 'text' => 'A band saw and chisels turn each block into cubes and spears.'],
                ['label' => 'Service', 'title' => 'Temper', 'text' => 'Every cube rests a minute before it goes in the glass, so it doesn\'t crack.'],
            ],
            [
                ['title' => 'Can I make clear ice at home?', 'text' => 'Yes. Freeze water in a small cooler with the lid open, and cut off the cloudy bottom.'],
                ['title' => 'Why does clear ice matter?', 'text' => 'It melts slower, so your drink stays cold without getting watery.'],
                ['title' => 'Do you sell your ice?', 'text' => 'For private events and to a few bars nearby. Ask us.'],
            ],
        );

        $this->post( $notes, [
            'name' => 'Low and no',
            'title' => 'Low and No Alcohol Cocktails That Taste Like Real Drinks',
            'path' => 'low-abv',
        ], 'Less booze, the same craft',
            "A third of our guests order at least one drink with little or no alcohol, and they deserve the same care as everyone else. A good low-ABV drink isn't a juice with a fancy garnish.\n\nWe build them like any other cocktail: a bitter or savoury base, acidity, texture and dilution. Vermouth, sherry and our own shrubs do the heavy lifting.",
            'mocktail',
            [
                ['title' => '6', 'text' => 'Low and no drinks on the menu'],
                ['title' => '0.0 %', 'text' => 'Alcohol in our zero-proof drinks'],
                ['title' => '1 in 3', 'text' => 'Guests who order at least one'],
            ],
            [
                ['label' => 'Base', 'title' => 'Bitter or savoury', 'text' => 'Tea, verjus, a shrub or an alcohol-free aperitif.'],
                ['label' => 'Balance', 'title' => 'Acid and sweet', 'text' => 'Citrus or vinegar, and honey or syrup in small amounts.'],
                ['label' => 'Texture', 'title' => 'Body', 'text' => 'Egg white, aquafaba or a little saline to make it feel full.'],
                ['label' => 'Finish', 'title' => 'Aroma', 'text' => 'Fresh herbs, peel and bitters for the nose.'],
            ],
            [
                ['title' => 'Are the zero-proof drinks cheaper?', 'text' => 'A little. They take as long to make, so the difference is small.'],
                ['title' => 'Can I get any classic as low-ABV?', 'text' => 'Many of them, like the Americano or a sherry cobbler. Just ask.'],
                ['title' => 'Do bitters contain alcohol?', 'text' => 'Yes, a tiny amount. Tell us and we leave them out.'],
            ],
        );

        return $this;
    }


    /**
     * Creates the masterclasses page below the events page.
     *
     * @param Page $parent Events page
     * @return static Same object for fluent calls
     */
    protected function addMasterclasses( Page $parent ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Masterclasses',
            'title' => 'Cocktail Masterclasses and Tastings in Hamburg | Brass Hour',
            'path' => 'masterclasses',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Step behind the bar',
                'subtitle' => 'Masterclasses',
                'text' => 'Hands-on classes for eight people at a time, on our own bar before we open. You shake, stir and taste, and you go home with the recipes.',
                'buttons' => [
                    ['label' => 'Book a class', 'url' => 'mailto:classes@brasshour.example'],
                    ['label' => 'Gift cards', 'url' => '/gift-cards'],
                ],
                'files' => [['id' => $this->portrait( 'bartender-pouring' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
                'title' => 'Choose your class',
                'text' => 'Classes run on Saturday and Sunday afternoons from 15:00. Snacks and water included, of course.',
                'items' => [
                    [
                        'name' => 'Classic cocktails',
                        'file' => ['id' => $this->img( 'bartender-shake' ), 'type' => 'file'],
                        'prices' => [['id' => 'classic', 'amount' => 69, 'label' => '€69']],
                        'text' => '3 hours, for beginners.',
                        'features' => "- Shaking and stirring\n- Six classics, start to finish\n- Balance, dilution and ice\n- Recipe booklet to take home",
                        'url' => 'mailto:classes@brasshour.example',
                        'button' => 'Book classic cocktails',
                        'highlight' => true,
                        'badge' => 'Popular',
                    ],
                    [
                        'name' => 'Gin & botanicals',
                        'file' => ['id' => $this->img( 'bottles' ), 'type' => 'file'],
                        'prices' => [['id' => 'gin', 'amount' => 79, 'label' => '€79']],
                        'text' => '2.5 hours, for everyone.',
                        'features' => "- Eight gins side by side\n- What juniper really tastes like\n- The perfect gin and tonic\n- Your own botanical blend",
                        'url' => 'mailto:classes@brasshour.example',
                        'button' => 'Book gin & botanicals',
                    ],
                    [
                        'name' => 'Whisky tasting',
                        'file' => ['id' => $this->img( 'bottles-2' ), 'type' => 'file'],
                        'prices' => [['id' => 'whisky', 'amount' => 89, 'label' => '€89']],
                        'text' => '2 hours, for everyone.',
                        'features' => "- Six whiskies, four countries\n- Grain, malt, cask and age\n- Water or no water\n- A dram of something rare",
                        'url' => 'mailto:classes@brasshour.example',
                        'button' => 'Book whisky tasting',
                    ],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'A class afternoon',
                'layout' => 'horizontal',
                'items' => [
                    ['label' => '15:00', 'title' => 'Welcome', 'text' => 'A spritz, a tour of the back bar and the ice room.'],
                    ['label' => '15:20', 'title' => 'Tools', 'text' => 'Jigger, shaker, bar spoon and how to hold them.'],
                    ['label' => '15:40', 'title' => 'Practice', 'text' => 'Two guests per station, a bartender always next to you.'],
                    ['label' => '18:00', 'title' => 'Last round', 'text' => 'Your favourite drink, made by you, for the group.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Questions about our classes',
                'items' => [
                    ['title' => 'How much will I drink?', 'text' => 'Small tasting portions, about three full cocktails over the afternoon. Please don\'t drive.'],
                    ['title' => 'Can I book a private class?', 'text' => 'Yes, for up to 16 people from €590. It is a popular team event.'],
                    ['title' => 'Is there a minimum age?', 'text' => 'All classes are for guests aged 18 and over.'],
                ],
            ]],
        ], $parent );

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
            'title' => 'Cocktail Menu: Signatures, Classics, Wine and Bar Snacks | Brass Hour Hamburg',
            'path' => 'menu',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Our menu',
                'subtitle' => 'Drinks & snacks',
                'text' => 'Eight signatures that change with the seasons, the classics done right and a short list of natural wines. Ask the bar for anything that isn\'t on it.',
                'buttons' => [
                    ['label' => 'Book a table', 'url' => '/book'],
                ],
                'files' => [['id' => $this->portrait( 'two-cocktails' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
                'title' => 'Cocktails',
                'items' => [
                    [
                        'name' => 'Signatures',
                        'prices' => [['id' => 'signatures', 'label' => 'Autumn menu']],
                        'text' => 'Created by our team, changing four times a year.',
                        'features' => "- Brass Hour Sour **14.00**\n- Altona Spritz **12.00**\n- Smoke & Mirrors **15.00**\n- Foundry Martini **15.00**\n- Night Ferry **14.00**",
                        'highlight' => true,
                        'badge' => 'New this season',
                    ],
                    [
                        'name' => 'Classics',
                        'prices' => [['id' => 'classics', 'label' => 'Done right']],
                        'text' => 'Stirred over clear ice or shaken hard, always fresh.',
                        'features' => "- Negroni **12.00**\n- Old fashioned **13.00**\n- Dry martini **13.00**\n- Daiquiri **11.00**\n- Whisky sour **12.00**",
                    ],
                    [
                        'name' => 'Low & no',
                        'prices' => [['id' => 'low', 'label' => 'Full flavour']],
                        'text' => 'The same craft, with little or no alcohol.',
                        'features' => "- Americano `LOW` **9.00**\n- Adonis `LOW` **10.00**\n- Bamboo `LOW` **10.00**\n- Rose Tonic `0.0` **8.00**\n- Verjus Sour `0.0` **8.00**",
                    ],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
                'title' => 'Wine, beer & snacks',
                'items' => [
                    [
                        'name' => 'Wine & bubbles',
                        'file' => ['id' => $this->img( 'wine' ), 'type' => 'file'],
                        'prices' => [['id' => 'wine', 'label' => 'By the glass']],
                        'text' => 'Natural, low-intervention wines.',
                        'features' => "- Champagne **16.00**\n- Mosel Riesling **9.00**\n- Orange wine **10.00**\n- Beaujolais **9.50**",
                    ],
                    [
                        'name' => 'Beer & cider',
                        'file' => ['id' => $this->img( 'bar-counter' ), 'type' => 'file'],
                        'prices' => [['id' => 'beer', 'label' => 'Local taps']],
                        'text' => 'From small Hamburg breweries.',
                        'features' => "- Pilsner, 0.3 l **4.50**\n- Pale ale, 0.3 l **5.50**\n- Dry cider, 0.33 l **5.50**\n- Zero lager `0.0` **4.50**",
                    ],
                    [
                        'name' => 'Bar snacks',
                        'file' => ['id' => $this->img( 'snack-board' ), 'type' => 'file'],
                        'prices' => [['id' => 'snacks', 'label' => 'Until 01:00']],
                        'text' => 'Small plates to share with friends.',
                        'features' => "- Gordal olives `VG` **5.00**\n- Almonds `VG` `N` **5.00**\n- Charcuterie **16.00**\n- Truffle fries `V` **8.00**",
                    ],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "`LOW` low alcohol · `0.0` alcohol-free · `V` vegetarian · `VG` vegan · `N` contains nuts\n\nAll prices in euros, including VAT. We serve alcohol only to guests aged 18 and over. Please tell us about allergies.",
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Good to know',
                'items' => [
                    ['title' => 'Can I order something off the menu?', 'text' => 'Of course. Tell us what you like and the bartender makes you something.'],
                    ['title' => 'Is there a minimum spend?', 'text' => 'No, not at the bar or at tables. Only for private events.'],
                    ['title' => 'Do you take cards?', 'text' => 'Cards and phones only, no cash.'],
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
            'title' => 'Privacy Policy | Brass Hour',
            'path' => 'privacy',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "# Privacy policy\n\n## Who is responsible\n\nBrass Hour Bar GmbH, Paul-Roosen-Straße 18, 22767 Hamburg, privacy@brasshour.example.\n\n## Bookings and requests\n\nWhen you book a table, a class or a private event, we use your details to handle your booking (Art. 6 (1) (b) GDPR). Bookings are deleted after the legal retention periods, requests without a booking after six months.\n\n## Gift cards\n\nFor gift cards sent by post, we store the delivery address until the card has been sent. Payments are handled by our payment provider, we never see your card details.\n\n## This website\n\nThe website doesn't use tracking or advertising cookies. Our server stores technical access data such as the IP address for seven days to protect against attacks. The map is loaded from OpenStreetMap only after you open it.\n\n## Your rights\n\nYou have the right to access, rectification, erasure, restriction of processing and data portability, and you can lodge a complaint with the Hamburg Commissioner for Data Protection.\n\nThis is a demo website for the Tonic theme. Brass Hour is a fictional bar.",
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the private events page below the events page.
     *
     * @param Page $parent Events page
     * @return static Same object for fluent calls
     */
    protected function addPrivate( Page $parent ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Private events',
            'title' => 'Private Events and Bar Hire in Hamburg | Brass Hour',
            'path' => 'private-events',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'The whole bar, just for you',
                'subtitle' => 'Private events',
                'text' => 'Birthdays, launches, weddings and team nights. Our private room seats 24, the whole bar holds 120, and we write a cocktail menu just for your evening.',
                'buttons' => [
                    ['label' => 'Ask for a quote', 'url' => '#private-request'],
                ],
                'files' => [['id' => $this->portrait( 'booth' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Three ways to celebrate',
                'columns' => '3',
                'cards' => [
                    ['title' => 'The Vault', 'text' => "**Up to 24 guests**\nOur private room in the old foundry vault, with its own bar and a record player.", 'file' => ['id' => $this->img( 'lounge' ), 'type' => 'file']],
                    ['title' => 'Full buyout', 'text' => "**Up to 120 guests**\nThe whole bar, our team and the music, just for you. Sunday to Thursday only.", 'file' => ['id' => $this->img( 'toast' ), 'type' => 'file']],
                    ['title' => 'Bar takeover', 'text' => "**At your place**\nTwo of our bartenders, a mobile bar and a menu for your event anywhere in Hamburg.", 'file' => ['id' => $this->img( 'bartender-pouring' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
                'title' => 'Guide prices',
                'text' => 'Final prices depend on the date, the number of guests and your drinks. All prices including VAT.',
                'items' => [
                    [
                        'name' => 'The Vault',
                        'prices' => [['id' => 'vault', 'amount' => 900, 'label' => 'from €900']],
                        'text' => 'Minimum spend, four hours.',
                        'features' => "- Private room and bar\n- Your own bartender\n- Welcome drink for everyone\n- Snack boards on request",
                        'url' => '#private-request',
                        'button' => 'Ask for a quote',
                        'highlight' => true,
                        'badge' => 'Most booked',
                    ],
                    [
                        'name' => 'Full buyout',
                        'prices' => [['id' => 'buyout', 'amount' => 4500, 'label' => 'from €4,500']],
                        'text' => 'Minimum spend, five hours.',
                        'features' => "- The whole bar for you\n- Custom cocktail menu\n- DJ or live trio on request\n- Coat check and door host",
                        'url' => '#private-request',
                        'button' => 'Ask for a quote',
                    ],
                    [
                        'name' => 'Bar takeover',
                        'prices' => [['id' => 'takeover', 'amount' => 1200, 'label' => 'from €1,200']],
                        'text' => 'Three hours, 50 guests.',
                        'features' => "- Two bartenders, mobile bar\n- Glassware and clear ice\n- Up to 150 drinks included\n- Set-up and clean-up",
                        'url' => '#private-request',
                        'button' => 'Ask for a quote',
                    ],
                ],
            ]],
            ['id' => 'private-request', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Ask for a quote',
                'description' => 'Tell us about your event and we send you an offer within two working days.',
                'inputs' => [
                    ['field' => 'name', 'required' => true, 'input' => 'text'],
                    ['field' => 'email', 'required' => true, 'input' => 'text'],
                    ['field' => 'telephone', 'required' => false, 'input' => 'text'],
                    ['field' => 'Type of event', 'required' => true, 'input' => 'select', 'options' => "Birthday\nCompany event\nWedding\nProduct launch\nOther"],
                    ['field' => 'Date', 'required' => true, 'input' => 'text'],
                    ['field' => 'Number of guests', 'required' => true, 'input' => 'text'],
                    ['field' => 'Anything else we should know', 'required' => false, 'input' => 'textarea'],
                ],
            ]],
        ], $parent );

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
            'title' => 'Our Story: A Cocktail Bar in an Old Foundry Cellar | Brass Hour Hamburg',
            'path' => 'story',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Two bartenders and a forgotten cellar',
                'subtitle' => 'Since 2016',
                'text' => 'Malik and Ingrid met behind the bar of a hotel in Oslo. In 2016 they found the cellar of an old brass foundry in Altona, cleaned out a century of dust and opened the door on a Friday night.',
                'files' => [['id' => $this->portrait( 'door' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'How we got here',
                'layout' => 'vertical',
                'items' => [
                    ['label' => '1908', 'title' => 'The foundry', 'text' => 'Ship fittings and lamps are cast in brass where the bar is today.'],
                    ['label' => '2016', 'title' => 'Doors open', 'text' => 'Twenty seats, one bartender each and a menu of twelve drinks.'],
                    ['label' => '2018', 'title' => 'The ice room', 'text' => 'We start freezing and cutting our own clear ice.'],
                    ['label' => '2021', 'title' => 'The Vault', 'text' => 'The old coal store becomes our private room for 24 guests.'],
                    ['label' => '2024', 'title' => 'Recognised', 'text' => 'Listed among the best bars in Germany for the third year in a row.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'back-bar' ), 'type' => 'file'],
                'position' => 'grid-end',
                'ratio' => '1-1',
                'text' => "## What we believe in\n\nA great bar is a room where time slows down. The drinks matter, but the welcome matters more.\n\n- **Hospitality first:** every guest is greeted at the door\n- **Seasonal:** fruit and herbs from farms around Hamburg\n- **Precise:** every drink measured, every ice cube cut by hand\n- **Low waste:** citrus husks become cordials and syrups",
            ]],
            $this->team(),
            $this->ethos(),
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Join the team',
                'text' => 'We are always looking for people who care about their guests and their craft.',
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
            'title' => 'Visit Us: Opening Hours and Directions | Brass Hour Hamburg',
            'path' => 'visit',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Down the stairs, through the brass door',
                'subtitle' => 'Visit us',
                'text' => 'Look for the small brass lamp above the cellar stairs. Forty seats, a twelve-metre bar and the Vault for private events.',
                'buttons' => [
                    ['label' => 'Get directions', 'url' => 'https://www.openstreetmap.org/?mlat=53.5560&mlon=9.9570#map=17/53.5560/9.9570'],
                    ['label' => 'Call us', 'url' => 'tel:+494023456780'],
                ],
                'files' => [['id' => $this->portrait( 'cellar' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Opening hours',
                'header' => 'row+col',
                'table' => [
                    ['', 'Bar', 'Kitchen', 'Last orders'],
                    ['Tuesday – Thursday', '18:00 – 01:00', 'until 23:00', '00:30'],
                    ['Friday – Saturday', '18:00 – 03:00', 'until 01:00', '02:30'],
                    ['Sunday', '17:00 – 00:00', 'until 22:00', '23:30'],
                    ['Monday', 'closed', 'closed', '–'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Inside the bar',
                'columns' => '3',
                'cards' => [
                    ['title' => 'The bar counter', 'text' => 'Twelve metres of oak and brass. Always kept free for walk-ins.', 'file' => ['id' => $this->img( 'bar-counter' ), 'type' => 'file']],
                    ['title' => 'The lounge', 'text' => 'Velvet booths for two to eight guests, bookable online.', 'file' => ['id' => $this->img( 'lounge' ), 'type' => 'file']],
                    ['title' => 'The Vault', 'text' => 'Our private room for up to 24 guests, with its own bar.', 'file' => ['id' => $this->img( 'booth' ), 'type' => 'file']],
                ],
            ]],
            $this->map( 'Find us' ),
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Before you come',
                'items' => [
                    ['title' => 'Do I need to book?', 'text' => 'No. We keep the bar counter and half of the tables for walk-ins. Booking helps on Fridays and Saturdays.'],
                    ['title' => 'Is there a dress code?', 'text' => 'No dress code, just come as you are. We only ask you to be kind to our team and the other guests.'],
                    ['title' => 'Is the bar accessible?', 'text' => 'The bar is in a cellar with twelve steps. Call us and we open the side entrance with a lift for you.'],
                    ['title' => 'How do I get there?', 'text' => 'S-Bahn to Holstenstraße, five minutes on foot. Parking is hard to find, so please take a taxi home.'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates (once) a 4:5 portrait hero image and returns its file ID.
     *
     * @param string $key PHOTOS key
     * @return string File ID
     */
    protected function portrait( string $key ) : string
    {
        return $this->cropped( $key, 960, 1200, true, [480, 960] );
    }


    /**
     * Creates the shared Brass Hour footer and returns its ID.
     *
     * @return string Element ID
     */
    protected function element() : string
    {
        return $this->element ??= $this->saveElement( 'cards', 'Brass Hour footer', ['columns' => '4', 'cards' => [
            ['title' => 'Bar', 'text' => "- [Menu](/menu)\n- [Book a table](/book)\n- [Visit us](/visit)\n- [What's on](/events)"],
            ['title' => 'Events', 'text' => "- [Private events](/private-events)\n- [Masterclasses](/masterclasses)\n- [Gift cards](/gift-cards)"],
            ['title' => 'Brass Hour', 'text' => "- [Our story](/story)\n- [Bar notes](/bar-notes)\n- [Careers](/careers)\n- [Imprint](/imprint)\n- [Privacy](/privacy)"],
            ['title' => 'Newsletter', 'text' => "Guest shifts, tastings and new menus, once a month.\n\n[Sign up by email](mailto:hello@brasshour.example?subject=Newsletter)"],
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
            'title' => 'Crafted, never rushed',
            'layout' => 'badges',
            'cards' => [
                ['title' => 'Hand-cut ice', 'text' => 'Clear ice cut by hand, 90 kg a day', 'file' => $this->icon( 'ice' )],
                ['title' => 'Seasonal menu', 'text' => 'Changing four times a year', 'file' => $this->icon( 'leaf' )],
                ['title' => 'Walk-ins welcome', 'text' => 'The bar is never booked out', 'file' => $this->icon( 'door' )],
                ['title' => 'Low & no', 'text' => 'Six drinks with little or no alcohol', 'file' => $this->icon( 'glass' )],
                ['title' => 'Live music', 'text' => 'A jazz trio every Wednesday', 'file' => $this->icon( 'music' )],
            ],
        ]];
    }


    /**
     * Returns the ID of the primary bar image.
     *
     * @return string File ID
     */
    protected function file() : string
    {
        return $this->img( 'bar-room' );
    }


    /**
     * Returns the Brass Hour home page.
     *
     * @return Page Home page
     */
    protected function home() : Page
    {
        $elementId = $this->element();
        $fileId = $this->file();

        $config = [
            'website' => Validation::entry( 'website', ['title' => 'Brass Hour'], 'config' ),
        ] + $this->logos( $this->logoFile() ) + [
            'tonic::bar' => [
                'type' => 'tonic::bar',
                'files' => [],
                'data' => [
                    'name' => 'Brass Hour',
                    'business-type' => 'BarOrPub',
                    'street-address' => 'Paul-Roosen-Straße 18',
                    'postal-code' => '22767',
                    'locality' => 'Hamburg',
                    'country' => 'DE',
                    'telephone' => '+49 40 2345 6780',
                    'email' => 'hello@brasshour.example',
                    'announcement' => 'Walk-ins welcome · Live jazz every Wednesday',
                    'booking' => '/book',
                    'menu' => '/menu',
                    'cuisine' => 'Cocktails, Wine, Bar snacks',
                    'price-range' => '€€',
                    'notice' => 'Over 18 only. Please drink responsibly.',
                    'action-bar' => true,
                    'hours' => [
                        ['id' => 'tue', 'day' => 'Tuesday', 'opens' => '18:00', 'closes' => '01:00'],
                        ['id' => 'wed', 'day' => 'Wednesday', 'opens' => '18:00', 'closes' => '01:00'],
                        ['id' => 'thu', 'day' => 'Thursday', 'opens' => '18:00', 'closes' => '01:00'],
                        ['id' => 'fri', 'day' => 'Friday', 'opens' => '18:00', 'closes' => '03:00'],
                        ['id' => 'sat', 'day' => 'Saturday', 'opens' => '18:00', 'closes' => '03:00'],
                        ['id' => 'sun', 'day' => 'Sunday', 'opens' => '17:00', 'closes' => '00:00'],
                    ],
                ],
            ],
        ];

        $content = [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Slow drinks for *late nights*',
                'subtitle' => 'Cocktail bar in Hamburg-Altona',
                'text' => 'Seasonal cocktails over hand-cut ice, natural wine and live jazz, in the cellar of an old brass foundry. Open from six, Tuesday to Sunday.',
                'buttons' => [
                    ['label' => 'Book a table', 'url' => '/book'],
                    ['label' => 'See the menu', 'url' => '/menu'],
                ],
                'background' => ['id' => $this->img( 'bar-room' ), 'type' => 'file'],
                'background-animation' => 'zoom',
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'layout' => 'figures',
                'cards' => [
                    ['title' => '2016', 'text' => 'Pouring drinks in Altona'],
                    ['title' => '8', 'text' => 'Seasonal signatures'],
                    ['title' => '90 kg', 'text' => 'Of clear ice cut every day'],
                    ['title' => '03:00', 'text' => 'Last call on the weekend'],
                ],
            ]],
            $this->signatures(),
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'strain' ), 'type' => 'file'],
                'position' => 'grid-end',
                'ratio' => '1-1',
                'text' => "## Stirred slowly, *served cold*\n\nEvery drink is measured to the millilitre and poured over ice we freeze and cut ourselves. It stays cold to the last sip, without turning into water.\n\n- **Clear ice:** frozen for three days, cut by hand\n- **Fresh juice:** pressed every afternoon\n- **House cordials:** from Hamburg fruit and herbs\n\n[Read about our ice program](/ice-program)",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'What\'s on',
                'columns' => '3',
                'cards' => [
                    ['title' => 'Live jazz', 'text' => "**Wednesday · 20:30**\nThe Elbe Street Trio in the back room, two sets, no cover charge.", 'url' => '/events', 'file' => ['id' => $this->img( 'jazz' ), 'type' => 'file']],
                    ['title' => 'Vinyl night', 'text' => "**Thursday · 21:00**\nBring a record and we play it. The best pick of the night drinks free.", 'url' => '/events', 'file' => ['id' => $this->img( 'vinyl' ), 'type' => 'file']],
                    ['title' => 'Negroni Sunday', 'text' => "**Sunday · 17:00**\nThe Negroni and all of its cousins for €9, all evening long.", 'url' => '/events', 'file' => ['id' => $this->img( 'negroni' ), 'type' => 'file']],
                ],
            ]],
            $this->ethos(),
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'What our guests say',
                'items' => $this->reviews(),
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'lounge' ), 'type' => 'file'],
                'position' => 'grid-start',
                'ratio' => '1-1',
                'text' => "## Your night, *our room*\n\nBirthdays, launches and team nights in the Vault for up to 24 guests, or the whole bar for 120. We write a cocktail menu just for your evening.\n\n[Plan a private event](/private-events)",
            ]],
            ['id' => Utils::uid(), 'type' => 'blog', 'group' => 'main', 'data' => [
                'title' => 'From our bar notes',
                'layout' => 'cards',
                'parent-page' => ['value' => $this->notesId, 'label' => 'Bar notes'],
                'order' => '_lft',
                'limit' => 3,
            ]],
            $this->map( 'Visit us' ),
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Save yourself a seat',
                'text' => 'The bar counter is always kept for walk-ins. For a table in the lounge, especially on Fridays and Saturdays, book ahead.',
                'buttons' => [
                    ['label' => 'Book a table', 'url' => '/book'],
                    ['label' => 'What\'s on', 'url' => '/events'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        $meta = [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => 'Brass Hour in Hamburg-Altona: seasonal cocktails over hand-cut ice, natural wine and live jazz in an old foundry cellar. Walk-ins welcome, open until 03:00 on weekends.',
                'keywords' => 'cocktail bar Hamburg, bar Altona, speakeasy Hamburg, live jazz Hamburg, cocktails, natural wine, late night bar',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => 'Brass Hour | Cocktail Bar in Hamburg-Altona',
                'description' => 'Slow drinks for late nights.',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        return $this->saveRoot( 'Brass Hour | Cocktail Bar in Hamburg-Altona', $config, $meta, $content, $elementId, $fileId );
    }


    /**
     * Creates a brass-colored line icon once and returns its file reference.
     *
     * @param string $name Icon name: door, glass, ice, leaf or music
     * @return array<string, string> File reference
     */
    protected function icon( string $name ) : array
    {
        $paths = [
            'door' => '<path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"/><path d="M4 21h16"/><circle cx="15" cy="12" r="0.8"/>',
            'glass' => '<path d="M4 4h16l-8 9z"/><path d="M12 13v7"/><path d="M8 20h8"/><path d="M16 4l2-2"/>',
            'ice' => '<path d="M12 3l8 4.5v9L12 21l-8-4.5v-9z"/><path d="M12 12l8-4.5"/><path d="M12 12v9"/><path d="M12 12L4 7.5"/>',
            'leaf' => '<path d="M5 19c0-9 6-14 15-14 0 9-5 15-14 15"/><path d="M5 19c3-4 6-7 10-9"/>',
            'music' => '<path d="M9 18V5l11-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="17" cy="16" r="3"/>',
        ];

        $this->icons[$name] ??= $this->svgFile(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#C9A45C" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">' . $paths[$name] . '</svg>',
            'icon-' . $name . '.svg',
            ucfirst( $name ) . ' icon',
            'Brass line icon: ' . $name,
            true,
        );

        return ['id' => $this->icons[$name], 'type' => 'file'];
    }


    /**
     * Creates the bar notes overview page and returns it.
     *
     * @param Page $home Home page
     * @return Page Bar notes page
     */
    protected function notes( Page $home ) : Page
    {
        return $this->page( [
            'id' => $this->notesId,
            'lang' => 'en',
            'name' => 'Bar notes',
            'title' => 'Bar Notes: Cocktail Recipes and Stories from Behind the Bar | Brass Hour',
            'path' => 'bar-notes',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Notes from behind the bar',
                'subtitle' => 'Bar notes',
                'text' => 'Recipes to make at home, the craft behind our drinks and what our team is drinking this season.',
                'files' => [['id' => $this->portrait( 'old-fashioned' ), 'type' => 'file']],
            ]],
            ['id' => 'notes-list', 'type' => 'blog', 'group' => 'main', 'data' => [
                'layout' => 'cards',
                'parent-page' => ['value' => $this->notesId, 'label' => 'Bar notes'],
                'order' => '_lft',
                'limit' => 12,
            ]],
        ], $home );
    }


    /**
     * Creates the Brass Hour SVG logo and returns its file ID.
     *
     * @return string File ID
     */
    protected function logoFile() : string
    {
        if( !isset( $this->logoFile ) )
        {
            $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 80" role="img" aria-labelledby="title desc">
  <title id="title">Brass Hour logo</title>
  <desc id="desc">Brass Art Deco diamond with a cocktail glass beside the Brass Hour wordmark</desc>
  <path d="M38 6l32 34-32 34L6 40z" fill="none" stroke="#C9A45C" stroke-width="2"/>
  <path d="M38 13l25 27-25 27-25-27z" fill="none" stroke="#C9A45C" stroke-width="0.75"/>
  <path d="M26 30h24L38 43z" fill="#C9A45C"/>
  <path d="M38 43v11M32 54h12" stroke="#C9A45C" stroke-width="2" stroke-linecap="round"/>
  <text x="84" y="46" fill="#EDE4D3" font-family="Didot, 'Bodoni 72', 'Bodoni MT', 'Iowan Old Style', Georgia, serif" font-size="30" letter-spacing="6">BRASS HOUR</text>
  <text x="86" y="65" fill="#C9A45C" font-family="system-ui, -apple-system, 'Segoe UI', sans-serif" font-size="10" letter-spacing="4.5">COCKTAIL BAR · HAMBURG</text>
</svg>
SVG;

            $this->logoFile = $this->svgFile(
                $svg,
                'brass-hour-logo.svg',
                'Brass Hour logo',
                'Brass Art Deco diamond with a cocktail glass beside the Brass Hour wordmark',
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
            'text' => "**Brass Hour**\nPaul-Roosen-Straße 18 · 22767 Hamburg-Altona\n\n**Opening hours**\nTuesday to Thursday 18:00–01:00\nFriday and Saturday 18:00–03:00\nSunday 17:00–00:00\n\n**Call**\n+49 40 2345 6780\n\n**Getting here**\nS-Bahn to Holstenstraße, five minutes on foot. Look for the brass lamp above the cellar stairs.",
            'location' => [
                'latitude' => 53.5560,
                'longitude' => 9.9570,
                'zoom' => 16,
            ],
            'button' => 'Open in OpenStreetMap',
        ]];
    }


    /**
     * Creates a Tonic demo page below the given parent and returns it.
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

        return $this->savePage( $data, $content, $parent, $elementId, $fileId, $footer, 'Brass Hour, cocktail bar Hamburg, bar Altona, speakeasy, cocktails, live jazz, late night bar' );
    }


    /**
     * Builds the Tonic demo page tree.
     */
    protected function pages() : void
    {
        $this->notesId = (string) Str::uuid7();
        $home = $this->home();

        $this->addMenu( $home )
            ->addEvents( $home )
            ->addStory( $home )
            ->addVisit( $home )
            ->addNotes( $home )
            ->addBook( $home )
            ->addCareers( $home )
            ->addImprint( $home )
            ->addPrivacy( $home );
    }


    /**
     * Creates a post below the bar notes page.
     *
     * @param Page $parent Bar notes page
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
                'title' => 'Questions from our guests',
                'items' => $questions,
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Taste it at the bar',
                'text' => 'Come down the stairs and let our bartenders make it for you, the way we make it every night.',
                'buttons' => [
                    ['label' => 'Book a table', 'url' => '/book'],
                    ['label' => 'See the menu', 'url' => '/menu'],
                ],
            ]],
        ], $parent );
    }


    /**
     * Returns the guest reviews.
     *
     * @return array<int, array<string, string>> Testimonial items
     */
    protected function reviews() : array
    {
        return [
            ['name' => 'Jana', 'role' => 'Regular since 2017', 'text' => 'The best Negroni in Hamburg, and the bartenders remember your name and your drink after the second visit.'],
            ['name' => 'Tobias', 'role' => 'Birthday in the Vault', 'text' => 'Twenty friends, a menu named after us and a trio playing our song. Nobody wanted to go home.'],
            ['name' => 'Mira', 'role' => 'Classic cocktails class', 'text' => 'Three hours behind a real bar and I finally know why my sours never foamed. Patient and very funny teachers.'],
        ];
    }


    /**
     * Returns the signature cocktails menu element.
     *
     * @return array<string, mixed> Pricing content element
     */
    protected function signatures() : array
    {
        return ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
            'title' => 'Signature cocktails',
            'text' => 'Created by our team and changed with the seasons. Every drink is also available with little or no alcohol.',
            'items' => [
                [
                    'name' => 'Brass Hour Sour',
                    'file' => ['id' => $this->img( 'amber-cocktail' ), 'type' => 'file'],
                    'prices' => [['id' => 'sour', 'amount' => 14, 'label' => '€14']],
                    'text' => 'Bourbon, apricot and honey.',
                    'features' => "- Lemon and egg white\n- `silky` `stone fruit` `warm`\n- Our first drink, since 2016",
                    'url' => '/menu',
                    'button' => 'Full menu',
                ],
                [
                    'name' => 'Altona Spritz',
                    'file' => ['id' => $this->img( 'red-cocktail' ), 'type' => 'file'],
                    'prices' => [['id' => 'spritz', 'amount' => 12, 'label' => '€12']],
                    'text' => 'Aperitivo, rhubarb, sparkling.',
                    'features' => "- Blood orange peel\n- `bitter` `bright` `light`\n- Perfect to start the night",
                    'url' => '/menu',
                    'button' => 'Full menu',
                    'highlight' => true,
                    'badge' => 'Most ordered',
                ],
                [
                    'name' => 'Foundry Martini',
                    'file' => ['id' => $this->img( 'martini' ), 'type' => 'file'],
                    'prices' => [['id' => 'martini', 'amount' => 15, 'label' => '€15']],
                    'text' => 'Gin, dry vermouth, sea buckthorn.',
                    'features' => "- Stirred ice-cold\n- `dry` `saline` `crisp`\n- With a dish of Gordal olives",
                    'url' => '/menu',
                    'button' => 'Full menu',
                ],
            ],
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
            ['Malik Osei', 'bartender-1', "**Head bartender**\nWrites the seasonal menu and knows every gin we stock."],
            ['Ingrid Lund', 'bartender-2', "**Bar manager**\nRuns the room, the wine list and every private event in the Vault."],
            ['Luca Romano', 'bartender-3', "**Ice program**\nCuts 90 kg of clear ice a day and teaches our classic cocktails class."],
            ['Selin Aydın', 'bartender-4', "**Low & no menu**\nCreated our alcohol-free menu and makes the house cordials."],
        ];

        return ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
            'title' => 'The people behind the bar',
            'columns' => '4',
            'cards' => array_map( fn( $person ) => [
                'title' => $person[0],
                'text' => $person[2],
                'file' => ['id' => $this->cropped( $person[1], 800, 1000 ), 'type' => 'file'],
            ], $people ),
        ]];
    }
}
