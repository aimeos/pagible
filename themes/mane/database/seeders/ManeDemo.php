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
 * Mane theme demo for the fictional Mane Atelier hair salon in Munich.
 */
class ManeDemo extends AbstractDemo
{
    /** @var array<string, string> Meta descriptions keyed by page path */
    protected const DESCRIPTIONS = [
        'balayage-guide' => 'Balayage, foilyage or highlights? How to choose the right technique, what it costs and how to make your colour last longer.',
        'book' => 'Book your appointment at Mane Atelier in Munich-Schwabing online. Choose your service and stylist, we confirm within two hours.',
        'bridal' => 'Bridal hair in Munich: trial session, wedding day styling at the salon or on location, and hair for the whole bridal party.',
        'bridal-timeline' => 'Your wedding hair timeline: when to book the trial, the last colour and the last cut before your big day.',
        'careers' => 'Hair stylist jobs in Munich: work at Mane Atelier with paid education, a four-day week and your own clients.',
        'colour' => 'Hair colour in Munich: balayage, highlights, glossing, grey blending and colour correction by the colour team of Mane Atelier.',
        'curl-care' => 'How to care for curly hair: washing, conditioning, styling and sleeping, with the routine our curl specialists recommend.',
        'gift-cards' => 'Mane Atelier gift cards for cuts, colour, treatments and products in Munich, valid for three years.',
        'hair-tips' => 'Hair tips from Mane Atelier: care guides, colour advice and bridal tips from our stylists in Munich.',
        'imprint' => 'Legal notice of Mane Atelier GmbH, Munich.',
        'new-clients' => 'Your first visit at Mane Atelier: free consultation, patch tests, what to bring and our booking and cancellation policy.',
        'privacy' => 'Privacy policy of Mane Atelier GmbH, Munich.',
        'services' => 'Services and prices at Mane Atelier in Munich: cuts, colour, balayage, curls, treatments and styling by stylist level.',
        'team' => 'Meet the stylists of Mane Atelier in Munich: colour, curl, bridal and barbering specialists with their own booking calendars.',
        'visit' => 'Visit Mane Atelier on Hohenzollernstraße in Munich-Schwabing: opening hours, directions, parking and accessibility.',
    ];

    /**
     * Curated Unsplash photos used by the salon demo.
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3?: string}>
     */
    protected const PHOTOS = [
        'acrylic-chair' => ['photo-1786325492119-7f5e6185afec', 'Styling chair', 'White acrylic styling chair in a bright salon'],
        'balayage' => ['photo-1605980625600-88b46abafa8d', 'Ash balayage', 'Soft ash blonde balayage on long curled hair', 'faces'],
        'balayage-before' => ['photo-1605980625600-88b46abafa8d', 'Before the balayage', 'Long hair before the balayage, flat and without dimension', 'faces&sat=-100&bri=-6&con=-20'],
        'basin' => ['photo-1634449571010-02389ed0f9b0', 'Wash basin', 'Wash basin with a reclining chair in the salon'],
        'beard' => ['photo-1599011176306-4a96f1516d4d', 'Beard trim', 'Barber trimming a beard with clippers'],
        'blonde' => ['photo-1515848797093-effe16ccfabb', 'Soft blonde', 'Portrait of a woman with soft blonde hair'],
        'blow-dry' => ['photo-1562322140-8baeececf3df', 'Blow-dry', 'Stylist blow-drying a client\'s hair'],
        'bob' => ['photo-1779350676620-fde279b1d023', 'Sleek bob', 'Sleek brown bob seen from behind'],
        'braiding' => ['photo-1634449571017-5fecfd26ad76', 'Braiding', 'Stylist braiding long hair in the salon'],
        'bridal' => ['photo-1549236177-77e8271c34b6', 'Bridal half-up', 'Bride with a soft half-up style and a hair accessory'],
        'bride-crown' => ['photo-1523264114838-feca761983c4', 'Bride with flowers', 'Bride with loose waves and a floral crown'],
        'bride-pinning' => ['photo-1581674210501-c760093514e8', 'Bridal styling', 'Stylist pinning a pearl hair vine into a bride\'s half-up waves', 'center&flip=h'],
        'brunette' => ['photo-1522337360788-8b13dee7a37e', 'Brunette waves', 'Woman with long brunette waves seen from behind'],
        'curls' => ['photo-1569430548104-6ca1cda3ec41', 'Defined curls', 'Woman with long, defined dark curls'],
        'curly-smile' => ['photo-1595218841793-d38b949402d2', 'Curl cut', 'Smiling woman with voluminous curly hair'],
        'cut' => ['photo-1700760934268-8aa0ef52ce0a', 'Precision cut', 'Scissors cutting wet hair in the salon'],
        'fade' => ['photo-1593702275687-f8b402bf1fb5', 'Skin fade', 'Barber cutting a skin fade with clippers'],
        'flatlay' => ['photo-1691600864306-11096725fadd', 'Scissors and flowers', 'Scissors, twine and white flowers on a white table'],
        'foils' => ['photo-1707812343087-c9ff9e5abb43', 'Foil highlights', 'Colourist placing foils for highlights'],
        'foils-2' => ['photo-1707720531504-ce087725861a', 'Colour work', 'Hair sectioned in foils during a colour appointment'],
        'hair-toss' => ['photo-1562259920-47afc3030ba2', 'Love for hair', 'Stylist tossing the long hair of a smiling client', 'center&flip=h'],
        'ombre' => ['photo-1605980766335-d3a41c7332a1', 'Lived-in colour', 'Long hair with a soft lived-in ombre'],
        'platinum-flipped' => ['photo-1779154137745-5680e6b97d65', 'Platinum bob', 'Woman with a platinum bob in profile against a white wall', 'faces&flip=h'],
        'products' => ['photo-1695527081782-33e110235ade', 'Care products', 'Shelf with hair care products in the salon'],
        'round-brush' => ['photo-1734111719430-fe4a3973f8af', 'Round brush finish', 'Round brush blow-dry on blonde hair'],
        'salon' => ['photo-1695527081848-1e46c06e6458', 'The salon', 'Bright hair salon with a stylist at work'],
        'salon-window' => ['photo-1582582450303-48cc2cfa2c43', 'Light-filled salon', 'Stylists at work in a bright salon with big windows'],
        'salon-bright' => ['photo-1633681138600-295fcd688876', 'Styling stations', 'Bright salon with white mirrors and styling chairs'],
        'salon-mirrors' => ['photo-1633681926022-84c23e8cb2d6', 'Round mirrors', 'Salon with round mirrors and a brick wall'],
        'shampoo' => ['photo-1610705267928-1b9f2fa7f1c5', 'Shampoo bar', 'Pink shampoo bottles lined up on a shelf'],
        'shears' => ['photo-1692521248572-b3108ab80955', 'Shears and eucalyptus', 'Hair shears beside a bowl of eucalyptus on a white table'],
        'silver' => ['photo-1664897604719-f97662af3de2', 'Silver blonde', 'Close-up of silver blonde hair framing a face'],
        'stylist-1' => ['photo-1648157963892-fa90a04e278b', 'Lena Hofmann', 'Portrait of a woman with blonde hair'],
        'stylist-2' => ['photo-1619718908820-5c1de14e0b7e', 'Amara Okafor', 'Portrait of a smiling woman with curly hair'],
        'stylist-3' => ['photo-1774727485537-e5455eec8baa', 'Sofia Marino', 'Portrait of a woman with short dark hair'],
        'stylist-4' => ['photo-1787008543379-91aaa64b0003', 'Jonas Weber', 'Barber with a beard cutting curly hair'],
        'team' => ['photo-1559599101-f09722fb4948', 'Our team', 'Three stylists holding their tools'],
        'team-hands' => ['photo-1595475884562-073c30d45670', 'Hands of our team', 'Raised hands holding brushes, scissors and a tint brush against a white wall'],
        'tools' => ['photo-1527799820374-dcf8d9d4a388', 'Tools of the trade', 'Scissors, combs and brushes laid out on a table'],
        'updo' => ['photo-1672788709547-6d7f239972c3', 'Updo', 'Elegant updo on red hair'],
        'wash' => ['photo-1717160675489-7779f2c91999', 'Hair wash', 'Client getting a relaxing hair wash'],
        'wash-chair' => ['photo-1695527081827-fdbc4e77be9b', 'Wash lounge', 'Wash chair with a plant in the salon'],
        'white-shirt' => ['photo-1712744626457-3ffa4ba32c8c', 'Warm welcome', 'Smiling woman with long dark hair in a white shirt'],
    ];

    private string $element;
    private string $tipsId;
    /** @var array<string, string> */
    private array $icons = [];
    private string $logoFile;


    /**
     * Creates the hidden appointment booking page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addBook( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Book now',
            'title' => 'Book an Appointment | Mane Atelier Hair Salon Munich',
            'path' => 'book',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Your chair is *waiting*',
                'subtitle' => 'Book an appointment',
                'text' => 'Tell us what you have in mind and when you would like to come. We confirm your appointment within two hours during opening times.',
                'background' => ['id' => $this->wide( 'acrylic-chair' ), 'type' => 'file'],
            ]],
            ['id' => 'booking-form', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Your appointment',
                'description' => 'New to Mane? Choose "Consultation" and we take 15 minutes to talk about your hair first, free of charge.',
                'inputs' => [
                    ['field' => 'name', 'required' => true, 'input' => 'text'],
                    ['field' => 'email', 'required' => true, 'input' => 'text'],
                    ['field' => 'telephone', 'required' => true, 'input' => 'text'],
                    ['field' => 'Service', 'required' => true, 'input' => 'select', 'options' => "Consultation\nCut & finish\nColour\nBalayage\nCurl cut\nTreatment\nBridal trial\nBarbering"],
                    ['field' => 'Stylist', 'required' => false, 'input' => 'select', 'options' => "No preference\nLena\nAmara\nSofia\nJonas"],
                    ['field' => 'Preferred date', 'required' => true, 'input' => 'text'],
                    ['field' => 'Your hair today and your wishes', 'required' => false, 'input' => 'textarea'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Booking questions',
                'items' => [
                    ['title' => 'Can I cancel or move my appointment?', 'text' => 'Yes, free of charge up to 24 hours before. Later cancellations are charged at 50 percent of the service.'],
                    ['title' => 'Do I need a patch test?', 'text' => 'Before your first colour with us, yes. It takes five minutes and has to be done at least 48 hours before.'],
                    ['title' => 'How long does a balayage take?', 'text' => 'Plan three to four hours, including toner, treatment and finish.'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the bridal page below the services page.
     *
     * @param Page $parent Services page
     * @return static Same object for fluent calls
     */
    protected function addBridal( Page $parent ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Bridal',
            'title' => 'Bridal Hair in Munich: Trial, Wedding Day and Bridal Party | Mane Atelier',
            'path' => 'bridal',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Hair for the day you will *always remember*',
                'subtitle' => 'Bridal',
                'text' => 'Soft waves, timeless updos or something entirely your own. We plan your bridal look with you in a calm trial session and stay by your side on the morning of your wedding.',
                'buttons' => [
                    ['label' => 'Book a bridal trial', 'url' => '/book'],
                    ['label' => 'See bridal prices', 'url' => '#bridal-prices'],
                ],
                'background' => ['id' => $this->wide( 'bride-pinning' ), 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Bridal looks we love',
                'columns' => '3',
                'cards' => [
                    ['title' => 'Romantic waves', 'text' => 'Loose, brushed-out waves that last from the ceremony to the last dance.', 'file' => ['id' => $this->img( 'bride-crown' ), 'type' => 'file']],
                    ['title' => 'Soft half-up', 'text' => 'Off the face, but with length and movement. Perfect with a veil or hair vine.', 'file' => ['id' => $this->img( 'bridal' ), 'type' => 'file']],
                    ['title' => 'Classic updo', 'text' => 'A low chignon or a textured bun, secured to stay perfect all day.', 'file' => ['id' => $this->img( 'updo' ), 'type' => 'file']],
                ],
            ]],
            ['id' => 'bridal-prices', 'type' => 'pricing', 'group' => 'main', 'data' => [
                'title' => 'Bridal packages',
                'text' => 'All packages include a consultation, the trial session and hair accessories fitting. Travel within Munich is free.',
                'items' => [
                    [
                        'name' => 'Bride',
                        'prices' => [['id' => 'bride', 'amount' => 290, 'label' => '€290']],
                        'text' => 'At the salon.',
                        'features' => "- Consultation and trial\n- Wedding morning styling\n- Pinning of veil and accessories\n- Touch-up kit to take along",
                        'url' => '/book',
                        'button' => 'Book the bride package',
                    ],
                    [
                        'name' => 'Bride on location',
                        'prices' => [['id' => 'location', 'amount' => 420, 'label' => '€420']],
                        'text' => 'We come to you.',
                        'features' => "- Everything in the bride package\n- Styling at your hotel or venue\n- Stylist stays until the ceremony\n- Free travel within Munich",
                        'url' => '/book',
                        'button' => 'Book on location',
                        'highlight' => true,
                        'badge' => 'Most booked',
                    ],
                    [
                        'name' => 'Bridal party',
                        'prices' => [['id' => 'party', 'amount' => 85, 'label' => '€85 per person']],
                        'text' => 'Bridesmaids, mothers, guests.',
                        'features' => "- Styling for up to eight guests\n- Half-up, waves or updo\n- Matching the bride's look\n- Combined with any bride package",
                        'url' => '/book',
                        'button' => 'Add the bridal party',
                    ],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'Your bridal journey',
                'layout' => 'horizontal',
                'items' => [
                    ['label' => '6 months before', 'title' => 'Consultation', 'text' => 'We talk about your dress, venue and the look you dream of.'],
                    ['label' => '2 months before', 'title' => 'Trial', 'text' => 'Two hours to try your style, with photos for reference.'],
                    ['label' => '2 weeks before', 'title' => 'Colour & gloss', 'text' => 'The last colour and a gloss for extra shine.'],
                    ['label' => 'Wedding day', 'title' => 'Your morning', 'text' => 'Calm, on time and with a glass of something sparkling.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Saying *yes* this year?',
                'text' => 'Wedding Saturdays from May to September fill up early. Ask for your date now, we hold it for you for one week.',
                'buttons' => [
                    ['label' => 'Ask for your date', 'url' => '/book'],
                    ['label' => 'Read the bridal hair timeline', 'url' => '/bridal-timeline'],
                ],
            ]],
        ], $parent );

        return $this;
    }


    /**
     * Creates the careers page below the team page.
     *
     * @param Page $parent Team page
     * @return static Same object for fluent calls
     */
    protected function addCareers( Page $parent ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Careers',
            'title' => 'Hair Stylist Jobs in Munich | Mane Atelier',
            'path' => 'careers',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Grow with a team that *loves* hair',
                'subtitle' => 'Careers',
                'text' => 'We are eleven stylists, colourists and assistants. Everyone works four days a week, builds their own clientele and gets paid time for education.',
                'buttons' => [
                    ['label' => 'Send your application', 'url' => 'mailto:jobs@maneatelier.example'],
                ],
                'background' => ['id' => $this->wide( 'hair-toss' ), 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'tools' ), 'type' => 'file'],
                'position' => 'grid-end',
                'ratio' => '1-1',
                'text' => "## What you get\n\n- A salary above the tariff and 10 percent of your product sales\n- A four-day week with two days off in a row\n- Twelve paid education days a year, in Munich, London or Milan\n- Your own booking calendar from day one\n- Free cuts and colour for you, every month",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Open positions',
                'columns' => '3',
                'cards' => [
                    ['title' => 'Senior stylist (m/f/d)', 'text' => "**Full time**\nAt least five years of experience and a love for precision cuts and lived-in colour."],
                    ['title' => 'Colourist (m/f/d)', 'text' => "**Full or part time**\nBalayage, blonding and colour correction are your favourite part of the day."],
                    ['title' => 'Apprentice (m/f/d)', 'text' => "**Start in September**\nThree years of training with a mentor at your side and your first clients in year two."],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Come by for a coffee',
                'text' => 'Send us a few lines and photos of your work. A CV is welcome but not required, and every application gets an answer within a week.',
                'buttons' => [
                    ['label' => 'Send your application', 'url' => 'mailto:jobs@maneatelier.example'],
                    ['label' => 'Meet the team', 'url' => '/team'],
                ],
            ]],
        ], $parent );

        return $this;
    }


    /**
     * Creates the colour page below the services page.
     *
     * @param Page $parent Services page
     * @return static Same object for fluent calls
     */
    protected function addColour( Page $parent ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Colour',
            'title' => 'Hair Colour and Balayage in Munich | Mane Atelier',
            'path' => 'colour',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Colour that grows out *beautifully*',
                'subtitle' => 'Colour & balayage',
                'text' => 'Hand-painted balayage, soft babylights and rich glosses that look as good after twelve weeks as on the first day. Planned in a consultation, mixed for you alone.',
                'buttons' => [
                    ['label' => 'Book a colour consultation', 'url' => '/book'],
                    ['label' => 'See colour prices', 'url' => '/services'],
                ],
                'background' => ['id' => $this->wide( 'silver' ), 'type' => 'file'],
            ]],
            $this->transformation( 'From flat to *dimensional*' ),
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Our colour services',
                'columns' => '3',
                'cards' => [
                    ['title' => 'Balayage', 'text' => "**From €220**\nHand-painted, soft and seamless. Grows out without a hard line.", 'file' => ['id' => $this->img( 'balayage' ), 'type' => 'file']],
                    ['title' => 'Highlights', 'text' => "**From €160**\nFine foils for brightness from root to tip, from subtle to bold.", 'file' => ['id' => $this->img( 'foils-2' ), 'type' => 'file']],
                    ['title' => 'Gloss & tone', 'text' => "**From €65**\nRefreshes faded colour and adds mirror shine in 30 minutes.", 'file' => ['id' => $this->img( 'brunette' ), 'type' => 'file']],
                    ['title' => 'Grey blending', 'text' => "**From €95**\nSoftens grey with fine lowlights instead of covering it all.", 'file' => ['id' => $this->img( 'blonde' ), 'type' => 'file']],
                    ['title' => 'Lived-in colour', 'text' => "**From €180**\nA blurred root and soft ends for three months without a visit.", 'file' => ['id' => $this->img( 'ombre' ), 'type' => 'file']],
                    ['title' => 'Colour correction', 'text' => "**By consultation**\nFrom box dye to brassy blonde, we plan the way back step by step.", 'file' => ['id' => $this->img( 'foils' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'Your colour appointment',
                'layout' => 'vertical',
                'items' => [
                    ['label' => '15 min', 'title' => 'Consultation', 'text' => 'Photos, skin tone, lifestyle and how much upkeep you want.'],
                    ['label' => '60–120 min', 'title' => 'Painting', 'text' => 'Balayage or foils, with bond protection in every mix.'],
                    ['label' => '20 min', 'title' => 'Toner & treatment', 'text' => 'A gloss for the exact shade and a mask for softness.'],
                    ['label' => '30 min', 'title' => 'Finish', 'text' => 'Blow-dry and styling, plus tips for your care at home.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Colour questions',
                'items' => [
                    ['title' => 'Which colour products do you use?', 'text' => 'Vegan, ammonia-free colours and bond builders that protect your hair during lightening.'],
                    ['title' => 'How often do I need a refresh?', 'text' => 'Balayage every three to four months, a gloss every six to eight weeks.'],
                    ['title' => 'Can I go blonde in one session?', 'text' => 'From light brown, often yes. From dark or coloured hair, we plan two or three sessions to keep your hair healthy.'],
                ],
            ]],
        ], $parent );

        return $this;
    }


    /**
     * Creates the gift cards page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addGifts( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Gift cards',
            'title' => 'Gift Cards for Hair and Care | Mane Atelier Munich',
            'path' => 'gift-cards',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
                'title' => 'Give a little *time for yourself*',
                'text' => 'Our gift cards come in a blush envelope, by post or to pick up at the salon. They are valid for three years and can be used for every service and product.',
                'items' => [
                    [
                        'name' => 'Blow-dry & gloss',
                        'file' => ['id' => $this->img( 'round-brush' ), 'type' => 'file'],
                        'prices' => [['id' => 'gloss', 'kind' => 'once', 'currency' => 'EUR', 'amount' => 95, 'label' => '€95']],
                        'text' => 'An hour of shine.',
                        'features' => "- Gloss in any shade\n- Scalp massage and wash\n- Blow-dry and finish\n- Valid for three years",
                        'url' => 'mailto:hello@maneatelier.example?subject=Gift%20card',
                        'button' => 'Order blow-dry & gloss',
                    ],
                    [
                        'name' => 'Ritual',
                        'file' => ['id' => $this->img( 'wash' ), 'type' => 'file'],
                        'prices' => [['id' => 'ritual', 'kind' => 'once', 'currency' => 'EUR', 'amount' => 160, 'label' => '€160']],
                        'text' => 'Cut, care and calm.',
                        'features' => "- Cut and finish\n- Bond repair treatment\n- 20 minute head spa\n- Travel-size care set",
                        'url' => 'mailto:hello@maneatelier.example?subject=Gift%20card',
                        'button' => 'Order the ritual',
                        'highlight' => true,
                        'badge' => 'Most given',
                    ],
                    [
                        'name' => 'Open value',
                        'file' => ['id' => $this->img( 'products' ), 'type' => 'file'],
                        'prices' => [['id' => 'value', 'kind' => 'once', 'currency' => 'EUR', 'amount' => 25, 'label' => 'from €25']],
                        'text' => 'You choose the amount.',
                        'features' => "- In steps of €25\n- For services and products\n- Partial use possible\n- Valid for three years",
                        'url' => 'mailto:hello@maneatelier.example?subject=Gift%20card',
                        'button' => 'Order a gift card',
                    ],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Gift card questions',
                'items' => [
                    ['title' => 'How fast do you send them?', 'text' => 'Orders before noon go out the same day. You can also pick them up at the reception.'],
                    ['title' => 'Is there a digital version?', 'text' => 'Yes. We email you a PDF that you can print or forward.'],
                    ['title' => 'Can the gift card be used with any stylist?', 'text' => 'Of course. The value is simply deducted from the bill, whoever does the service.'],
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
            'title' => 'Imprint | Mane Atelier',
            'path' => 'imprint',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "# Imprint\n\n**Mane Atelier GmbH**\nHohenzollernstraße 42\n80801 München\nGermany\n\nTelephone: +49 89 2345 6780\nEmail: hello@maneatelier.example\n\nManaging director: Lena Hofmann\nCommercial register: Munich Local Court, HRB 00000\nVAT ID: DE000000000\n\nMaster craftswoman in the hairdressing trade, entered in the register of crafts of the Munich and Upper Bavaria Chamber of Crafts.\n\n## Consumer dispute resolution\n\nWe are neither willing nor obliged to take part in dispute resolution proceedings before a consumer arbitration board.\n\nThis is a demo website for the Mane theme. Mane Atelier is a fictional salon and the people named are not real.",
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the new clients page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addNewClients( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'New clients',
            'title' => 'Your First Visit: Consultation, Patch Test and Policies | Mane Atelier',
            'path' => 'new-clients',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Welcome to *Mane*',
                'subtitle' => 'New clients',
                'text' => 'Every first visit starts with a free 15 minute consultation. We look at your hair, listen to what you want and only then talk about colour, cut and price.',
                'buttons' => [
                    ['label' => 'Book a free consultation', 'url' => '/book'],
                ],
                'background' => ['id' => $this->wide( 'white-shirt' ), 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'Your first visit',
                'layout' => 'horizontal',
                'items' => [
                    ['label' => 'Arrive', 'title' => 'Coffee or tea', 'text' => 'Come five minutes early and settle in.'],
                    ['label' => 'Talk', 'title' => 'Consultation', 'text' => 'Your hair history, photos you love and your routine.'],
                    ['label' => 'Relax', 'title' => 'Wash ritual', 'text' => 'Scalp massage in our reclining wash chairs.'],
                    ['label' => 'Leave', 'title' => 'Home care plan', 'text' => 'Tips and products that suit your hair.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'wash-chair' ), 'type' => 'file'],
                'position' => 'grid-start',
                'ratio' => '1-1',
                'text' => "## Good to bring along\n\n- Photos of hair you love, and of hair you don't\n- What you used on your hair in the last two years, including box dye or henna\n- Your usual styling products\n- Glasses, if you wear them, so you can see the result",
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Our policies',
                'items' => [
                    ['title' => 'Cancellations', 'text' => 'Free of charge up to 24 hours before. Later cancellations and no-shows are charged at 50 percent of the booked service.'],
                    ['title' => 'Running late', 'text' => 'Please call us. If you are more than 15 minutes late, we may have to shorten or move your appointment.'],
                    ['title' => 'Patch tests', 'text' => 'Required 48 hours before your first colour with us and after a break of six months.'],
                    ['title' => 'Children', 'text' => 'Kids cuts up to 12 years from €32. For colour, clients must be at least 16.'],
                    ['title' => 'Payment', 'text' => 'Cards, phones and cash. Gift cards can be combined with any payment.'],
                    ['title' => 'Not happy?', 'text' => 'Tell us within seven days and we fix it free of charge.'],
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
            'title' => 'Privacy Policy | Mane Atelier',
            'path' => 'privacy',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "# Privacy policy\n\n## Who is responsible\n\nMane Atelier GmbH, Hohenzollernstraße 42, 80801 München, privacy@maneatelier.example.\n\n## Appointments\n\nWhen you book an appointment, we use your name, contact details and the details you give us about your hair to plan and carry out the service (Art. 6 (1) (b) GDPR). Your colour formulas are stored in your client card so we can repeat them. You can ask us to delete your client card at any time.\n\n## Patch tests and allergies\n\nIf you tell us about allergies, we store this information with your explicit consent (Art. 9 (2) (a) GDPR) only to protect your health during treatments.\n\n## This website\n\nThe website doesn't use tracking or advertising cookies. Our server stores technical access data such as the IP address for seven days to protect against attacks. The map is loaded from OpenStreetMap only after you open it.\n\n## Your rights\n\nYou have the right to access, rectification, erasure, restriction of processing and data portability, and you can lodge a complaint with the Bavarian State Office for Data Protection Supervision.\n\nThis is a demo website for the Mane theme. Mane Atelier is a fictional salon.",
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the services and prices page and its sub-pages below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addServices( Page $home ) : static
    {
        $services = $this->page( [
            'lang' => 'en',
            'name' => 'Services',
            'title' => 'Services and Prices: Cuts, Colour, Curls and Care | Mane Atelier Munich',
            'path' => 'services',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Services & *prices*',
                'subtitle' => 'Our menu',
                'text' => 'Clear prices, no surprises. Every service includes a consultation, a wash ritual and a finish. Prices depend on the level of your stylist and the length of your hair.',
                'buttons' => [
                    ['label' => 'Book now', 'url' => '/book'],
                    ['label' => 'New clients', 'url' => '/new-clients'],
                ],
                'background' => ['id' => $this->wide( 'shears' ), 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Prices by stylist level',
                'header' => 'row+col',
                'table' => [
                    ['', 'Stylist', 'Senior stylist', 'Creative director'],
                    ['Cut & finish', '€78', '€95', '€125'],
                    ['Curl cut', '€95', '€115', '€145'],
                    ['Root colour', '€70', '€80', '€95'],
                    ['Balayage', '€220', '€260', '€320'],
                    ['Blow-dry', '€45', '€52', '€65'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
                'title' => 'The full menu',
                'text' => 'Starting prices for short hair with a stylist. For long or very thick hair, we add up to 20 percent and tell you before we start.',
                'items' => [
                    [
                        'name' => 'Cut & style',
                        'prices' => [['id' => 'cut', 'label' => 'From €45']],
                        'text' => 'Precision cuts, finished by hand.',
                        'features' => "- Cut & finish **78**\n- Curl cut `dry cut` **95**\n- Fringe trim **20**\n- Blow-dry **45**\n- Event styling **75**\n- Kids cut `under 12` **32**",
                    ],
                    [
                        'name' => 'Colour',
                        'prices' => [['id' => 'colour', 'label' => 'From €65']],
                        'text' => 'Vegan colour with bond protection.',
                        'features' => "- Gloss & tone **65**\n- Root colour **70**\n- Grey blending **95**\n- Highlights **160**\n- Lived-in colour **180**\n- Balayage `bestseller` **220**",
                        'highlight' => true,
                        'badge' => 'Our speciality',
                    ],
                    [
                        'name' => 'Care & extras',
                        'prices' => [['id' => 'care', 'label' => 'From €25']],
                        'text' => 'Treatments for strength and shine.',
                        'features' => "- Bond repair **35**\n- Moisture mask **25**\n- Head spa `20 min` **45**\n- Keratin smoothing **240**\n- Braids & updos **60**\n- Beard trim **28**",
                    ],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "All prices in euros including VAT. Every first visit starts with a free 15 minute consultation, and a patch test is required 48 hours before your first colour.\n\n**Stylist levels:** stylists have at least three years of experience, senior stylists at least seven, and creative directors lead our education and trends.",
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Good to know',
                'items' => [
                    ['title' => 'Why do prices differ between stylists?', 'text' => 'They reflect experience and demand. Every level is trained to the same Mane standard.'],
                    ['title' => 'Will I know the price before you start?', 'text' => 'Always. After the consultation you get an exact price, and it doesn\'t change.'],
                    ['title' => 'Do you charge for long hair?', 'text' => 'For long or very thick hair we add up to 20 percent for colour services, never for cuts.'],
                ],
            ]],
        ], $home );

        return $this->addColour( $services )
            ->addBridal( $services );
    }


    /**
     * Creates the team page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addTeam( Page $home ) : static
    {
        $team = $this->page( [
            'lang' => 'en',
            'name' => 'Team',
            'title' => 'Our Stylists: Colour, Curl and Bridal Specialists | Mane Atelier Munich',
            'path' => 'team',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'The hands behind *your hair*',
                'subtitle' => 'Our team',
                'text' => 'Eleven stylists, colourists and assistants, each with a speciality. Since 2014, Lena Hofmann has built a team that listens first and cuts second.',
                'buttons' => [
                    ['label' => 'Book with your stylist', 'url' => '/book'],
                ],
                'background' => ['id' => $this->wide( 'team-hands' ), 'type' => 'file'],
            ]],
            $this->team(),
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'Our story',
                'layout' => 'vertical',
                'items' => [
                    ['label' => '2014', 'title' => 'Two chairs', 'text' => 'Lena opens a tiny salon in a Schwabing backyard.'],
                    ['label' => '2017', 'title' => 'Hohenzollernstraße', 'text' => 'Eight chairs, big windows and our own colour bar.'],
                    ['label' => '2020', 'title' => 'Curl studio', 'text' => 'Amara joins and starts our dry curl cutting service.'],
                    ['label' => '2023', 'title' => 'Fully vegan', 'text' => 'Every colour and care product in the salon is now vegan.'],
                    ['label' => '2025', 'title' => 'Recognised', 'text' => 'Named one of the best salons in Bavaria by a national hair magazine.'],
                ],
            ]],
            $this->ethos(),
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Love hair as *much as we do?*',
                'text' => 'We are always looking for stylists and colourists who care about their craft and their clients.',
                'buttons' => [
                    ['label' => 'Open positions', 'url' => '/careers'],
                    ['label' => 'Visit the salon', 'url' => '/visit'],
                ],
            ]],
        ], $home );

        return $this->addCareers( $team );
    }


    /**
     * Creates the hair tips page and its posts below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addTips( Page $home ) : static
    {
        $tips = $this->tips( $home );

        $this->post( $tips, [
            'name' => 'Balayage guide',
            'title' => 'Balayage, Foilyage or Highlights? How to Choose Your Colour',
            'path' => 'balayage-guide',
        ], 'Balayage, foilyage or highlights?',
            "Three techniques, three very different results. Balayage is painted by hand and gives soft, sun-kissed brightness that grows out without a line. Foilyage combines painting with foils for more lift. Classic highlights are woven and placed from the root for an even, bright result.\n\nWhich one suits you depends on your starting colour, how bright you want to go and how often you want to come back. Here is how we decide together in the consultation.",
            'balayage',
            [
                ['title' => '3–4 h', 'text' => 'Time for a full balayage'],
                ['title' => '12 wks', 'text' => 'Until your first refresh'],
                ['title' => '2', 'text' => 'Levels of lift in one session'],
            ],
            [
                ['label' => 'Step 1', 'title' => 'Look at your base', 'text' => 'Natural or coloured, warm or cool, fine or thick.'],
                ['label' => 'Step 2', 'title' => 'Decide on brightness', 'text' => 'Soft glow or bold blonde, near the face or all over.'],
                ['label' => 'Step 3', 'title' => 'Choose the technique', 'text' => 'Balayage for softness, foils for lift, or both.'],
                ['label' => 'Step 4', 'title' => 'Plan your upkeep', 'text' => 'A gloss in between keeps the tone fresh.'],
            ],
            [
                ['title' => 'Does balayage work on dark hair?', 'text' => 'Yes, beautifully. Caramel and honey tones look especially natural on dark brown.'],
                ['title' => 'Will my hair get damaged?', 'text' => 'We add bond protection to every lightener and never lift further than your hair can take.'],
                ['title' => 'How do I keep blonde from turning yellow?', 'text' => 'Use a purple shampoo once a week and book a gloss every six to eight weeks.'],
            ],
        );

        $this->post( $tips, [
            'name' => 'Curl care',
            'title' => 'How to Care for Curly Hair: The Routine Our Curl Specialists Use',
            'path' => 'curl-care',
        ], 'Five habits for happier curls',
            "Curly hair is thirsty hair. The natural oils of the scalp travel slowly along a curl, so the ends dry out faster than in straight hair. Most frizz isn't a styling problem, it is a moisture problem.\n\nOur curl specialist Amara cuts every curl dry and individually, and she always gives her clients the same advice for home. These five habits make the biggest difference.",
            'curls',
            [
                ['title' => '2×', 'text' => 'Washes per week at most'],
                ['title' => '100 %', 'text' => 'Silicone- and sulphate-free care'],
                ['title' => '6 wks', 'text' => 'Between curl cuts'],
            ],
            [
                ['label' => 'Wash', 'title' => 'Gentle cleansing', 'text' => 'A mild shampoo on the scalp only, the lengths get clean when you rinse.'],
                ['label' => 'Condition', 'title' => 'Squish to condish', 'text' => 'Scrunch conditioner in with water until the curls clump.'],
                ['label' => 'Style', 'title' => 'Wet application', 'text' => 'Cream and gel on soaking wet hair, then hands off.'],
                ['label' => 'Night', 'title' => 'Silk pillowcase', 'text' => 'A loose pineapple on silk keeps curls defined for day two.'],
            ],
            [
                ['title' => 'What is a curl cut?', 'text' => 'A dry cut, curl by curl, that follows your natural pattern instead of fighting it.'],
                ['title' => 'Should I brush my curls?', 'text' => 'Only in the shower with conditioner in. Dry brushing breaks up the curl pattern.'],
                ['title' => 'Can I colour curly hair?', 'text' => 'Yes, with care. We use bond builders and gentle lighteners to keep the curl intact.'],
            ],
        );

        $this->post( $tips, [
            'name' => 'Bridal timeline',
            'title' => 'Your Wedding Hair Timeline: Trial, Colour and Cut',
            'path' => 'bridal-timeline',
        ], 'Your wedding hair, month by month',
            "The most common wish we hear from brides is to look like themselves, only a little more radiant. That starts long before the wedding morning: with healthy hair, the right colour at the right time and a trial that takes the nerves out of the big day.\n\nThis is the timeline we recommend to every bride, whether she wears her hair down, half-up or in a classic chignon.",
            'bride-crown',
            [
                ['title' => '6 mo', 'text' => 'Before the wedding: first consultation'],
                ['title' => '2 h', 'text' => 'For the bridal trial'],
                ['title' => '1', 'text' => 'Stylist with you all morning'],
            ],
            [
                ['label' => '6 months', 'title' => 'Plan', 'text' => 'Consultation, treatments for strength and shine, and growing out.'],
                ['label' => '2 months', 'title' => 'Trial', 'text' => 'Bring your veil and photos of your dress.'],
                ['label' => '2 weeks', 'title' => 'Colour', 'text' => 'The last colour and a gloss for extra shine.'],
                ['label' => '1 week', 'title' => 'Cut', 'text' => 'Just the ends, so everything falls into place.'],
            ],
            [
                ['title' => 'Should I wash my hair on the wedding day?', 'text' => 'Wash it the evening before. Second-day hair holds styles better.'],
                ['title' => 'How long does bridal styling take?', 'text' => 'About 90 minutes for the bride and 45 minutes for each guest.'],
                ['title' => 'Can you also do my make-up?', 'text' => 'We work with two make-up artists we trust and coordinate everything for you.'],
            ],
        );

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
            'title' => 'Visit Us: Opening Hours and Directions | Mane Atelier Munich',
            'path' => 'visit',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Light, calm and *close to the park*',
                'subtitle' => 'Visit us',
                'text' => 'Our salon is in a corner building in Schwabing, two minutes from the Englischer Garten. Eight chairs, big windows and a quiet wash lounge in the back.',
                'buttons' => [
                    ['label' => 'Get directions', 'url' => 'https://www.openstreetmap.org/?mlat=48.1616&mlon=11.5795#map=17/48.1616/11.5795'],
                    ['label' => 'Call us', 'url' => 'tel:+498923456780'],
                ],
                'background' => ['id' => $this->wide( 'salon-window' ), 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Opening hours',
                'header' => 'row+col',
                'table' => [
                    ['', 'Salon', 'Last appointment'],
                    ['Tuesday – Wednesday', '09:00 – 19:00', '17:30'],
                    ['Thursday – Friday', '09:00 – 20:00', '18:30'],
                    ['Saturday', '08:00 – 16:00', '14:30'],
                    ['Sunday – Monday', 'closed', '–'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Inside the salon',
                'columns' => '3',
                'cards' => [
                    ['title' => 'Styling floor', 'text' => 'Eight chairs in natural light, with space between every mirror.', 'file' => ['id' => $this->img( 'salon-mirrors' ), 'type' => 'file']],
                    ['title' => 'Wash lounge', 'text' => 'Reclining wash chairs, warm towels and a head massage with every wash.', 'file' => ['id' => $this->img( 'basin' ), 'type' => 'file']],
                    ['title' => 'Care bar', 'text' => 'All products we use, to try and to take home.', 'file' => ['id' => $this->img( 'shampoo' ), 'type' => 'file']],
                ],
            ]],
            $this->map( 'Find us' ),
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Before you come',
                'items' => [
                    ['title' => 'Where can I park?', 'text' => 'There is a public car park on Hohenzollernplatz, three minutes on foot. Bikes can be parked in our courtyard.'],
                    ['title' => 'How do I get there by public transport?', 'text' => 'U-Bahn U2 to Hohenzollernplatz or U3/U6 to Giselastraße, both about five minutes on foot.'],
                    ['title' => 'Is the salon accessible?', 'text' => 'Yes. The salon is on the ground floor without steps, and one wash chair is suitable for wheelchair users.'],
                    ['title' => 'Can I bring my dog?', 'text' => 'Calm dogs are welcome. Please tell us when you book, so we can seat you away from other guests with allergies.'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the shared Mane Atelier footer and returns its ID.
     *
     * @return string Element ID
     */
    protected function element() : string
    {
        return $this->element ??= $this->saveElement( 'cards', 'Mane Atelier footer', ['columns' => '4', 'cards' => [
            ['title' => 'Salon', 'text' => "- [Services & prices](/services)\n- [Colour & balayage](/colour)\n- [Bridal hair](/bridal)\n- [Book now](/book)"],
            ['title' => 'Clients', 'text' => "- [New clients](/new-clients)\n- [Gift cards](/gift-cards)\n- [Visit us](/visit)\n- [Hair tips](/hair-tips)"],
            ['title' => 'Mane Atelier', 'text' => "- [Our team](/team)\n- [Careers](/careers)\n- [Imprint](/imprint)\n- [Privacy](/privacy)"],
            ['title' => 'Newsletter', 'text' => "Care tips, new services and last-minute appointments, once a month.\n\n[Sign up by email](mailto:hello@maneatelier.example?subject=Newsletter)"],
        ]] );
    }


    /**
     * Returns the salon promise badges element.
     *
     * @return array<string, mixed> Cards content element
     */
    protected function ethos() : array
    {
        return ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
            'title' => 'The Mane promise',
            'layout' => 'badges',
            'cards' => [
                ['title' => 'Free consultation', 'text' => '15 minutes before every first visit', 'file' => $this->icon( 'chat' )],
                ['title' => 'Vegan colour', 'text' => 'Ammonia-free, with bond protection', 'file' => $this->icon( 'leaf' )],
                ['title' => 'Clear prices', 'text' => 'You know the price before we start', 'file' => $this->icon( 'tag' )],
                ['title' => 'Head spa', 'text' => 'A massage with every wash', 'file' => $this->icon( 'drop' )],
                ['title' => '7-day promise', 'text' => 'Not happy? We fix it for free', 'file' => $this->icon( 'heart' )],
            ],
        ]];
    }


    /**
     * Returns the ID of the primary salon image.
     *
     * @return string File ID
     */
    protected function file() : string
    {
        return $this->img( 'salon' );
    }


    /**
     * Returns the Mane Atelier home page.
     *
     * @return Page Home page
     */
    protected function home() : Page
    {
        $elementId = $this->element();
        $fileId = $this->file();

        $config = [
            'website' => Validation::entry( 'website', ['title' => 'Mane Atelier'], 'config' ),
        ] + $this->logos( $this->logoFile() ) + [
            'mane::salon' => [
                'type' => 'mane::salon',
                'files' => [],
                'data' => [
                    'name' => 'Mane Atelier',
                    'business-type' => 'HairSalon',
                    'street-address' => 'Hohenzollernstraße 42',
                    'postal-code' => '80801',
                    'locality' => 'München',
                    'country' => 'DE',
                    'telephone' => '+49 89 2345 6780',
                    'email' => 'hello@maneatelier.example',
                    'announcement' => 'New clients get a free 15 minute consultation',
                    'booking' => '/book',
                    'prices' => '/services',
                    'price-range' => '€€€',
                    'notice' => 'Please cancel or move your appointment at least 24 hours before.',
                    'action-bar' => true,
                    'hours' => [
                        ['id' => 'tue', 'day' => 'Tuesday', 'opens' => '09:00', 'closes' => '19:00'],
                        ['id' => 'wed', 'day' => 'Wednesday', 'opens' => '09:00', 'closes' => '19:00'],
                        ['id' => 'thu', 'day' => 'Thursday', 'opens' => '09:00', 'closes' => '20:00'],
                        ['id' => 'fri', 'day' => 'Friday', 'opens' => '09:00', 'closes' => '20:00'],
                        ['id' => 'sat', 'day' => 'Saturday', 'opens' => '08:00', 'closes' => '16:00'],
                    ],
                ],
            ],
        ];

        $content = [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Hair that feels like *you*',
                'subtitle' => 'Hair salon in Munich-Schwabing',
                'text' => 'Precision cuts, hand-painted colour and curl care in a calm, light-filled salon. Every visit starts with a conversation, never with the scissors.',
                'buttons' => [
                    ['label' => 'Book now', 'url' => '/book'],
                    ['label' => 'Services & prices', 'url' => '/services'],
                ],
                'background' => ['id' => $this->wide( 'platinum-flipped' ), 'type' => 'file'],
                'background-animation' => 'zoom',
                'main' => true,
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'layout' => 'figures',
                'cards' => [
                    ['title' => '2014', 'text' => 'Caring for hair in Schwabing'],
                    ['title' => '4.9', 'text' => 'Average rating from 1,200 reviews'],
                    ['title' => '11', 'text' => 'Stylists and colourists'],
                    ['title' => '100 %', 'text' => 'Vegan colour and care'],
                ],
            ]],
            $this->signatures(),
            $this->transformation( 'Real clients, *real results*' ),
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'curly-smile' ), 'type' => 'file'],
                'position' => 'grid-end',
                'ratio' => '1-1',
                'text' => "## Curls, cut *curl by curl*\n\nOur curl specialists cut curly and coily hair dry, following each curl's natural shape. The result is volume where you want it, definition that lasts and a routine you can do at home.\n\n- **Dry curl cut:** shaped curl by curl\n- **Curl hydration:** deep moisture treatment\n- **Styling lesson:** your routine, step by step\n\n[Read our curl care guide](/curl-care)",
            ]],
            $this->team(),
            $this->ethos(),
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'Kind words from our clients',
                'items' => $this->reviews(),
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'bridal' ), 'type' => 'file'],
                'position' => 'grid-start',
                'ratio' => '1-1',
                'text' => "## Bridal hair, *calm and timeless*\n\nFrom the first consultation to the last pin on your wedding morning, at the salon or at your venue. We style the whole bridal party, too.\n\n[Discover bridal hair](/bridal)",
            ]],
            ['id' => Utils::uid(), 'type' => 'blog', 'group' => 'main', 'data' => [
                'title' => 'Hair tips from our stylists',
                'layout' => 'cards',
                'parent-page' => ['value' => $this->tipsId, 'label' => 'Hair tips'],
                'order' => '_lft',
                'limit' => 3,
            ]],
            $this->map( 'Visit the salon' ),
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Ready for a *fresh start?*',
                'text' => 'Book online in two minutes. New to Mane? Your first visit starts with a free consultation.',
                'buttons' => [
                    ['label' => 'Book now', 'url' => '/book'],
                    ['label' => 'Gift cards', 'url' => '/gift-cards'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        $meta = [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => 'Mane Atelier in Munich-Schwabing: precision cuts, balayage, curl cuts and bridal hair with vegan colour. Free consultation for new clients, book online.',
                'keywords' => 'hair salon Munich, hairdresser Schwabing, balayage Munich, curl cut Munich, bridal hair Munich, vegan hair colour',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => 'Mane Atelier | Hair Salon in Munich-Schwabing',
                'description' => 'Hair that feels like you.',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        return $this->saveRoot( 'Mane Atelier | Hair Salon in Munich-Schwabing', $config, $meta, $content, $elementId, $fileId );
    }


    /**
     * Creates a clay rose line icon once and returns its file reference.
     *
     * @param string $name Icon name: chat, drop, heart, leaf or tag
     * @return array<string, string> File reference
     */
    protected function icon( string $name ) : array
    {
        $paths = [
            'chat' => '<path d="M4 5h16v11H9l-5 4z"/><path d="M8 9h8"/><path d="M8 12h5"/>',
            'drop' => '<path d="M12 3c3.5 4.5 6 8 6 11a6 6 0 0 1-12 0c0-3 2.5-6.5 6-11z"/><path d="M9 15a3 3 0 0 0 3 3"/>',
            'heart' => '<path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/>',
            'leaf' => '<path d="M5 19c0-9 6-14 15-14 0 9-5 15-14 15"/><path d="M5 19c3-4 6-7 10-9"/>',
            'tag' => '<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="8.5" r="1.2"/>',
        ];

        $this->icons[$name] ??= $this->svgFile(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#9A5A43" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">' . $paths[$name] . '</svg>',
            'icon-' . $name . '.svg',
            ucfirst( $name ) . ' icon',
            'Clay rose line icon: ' . $name,
            true,
        );

        return ['id' => $this->icons[$name], 'type' => 'file'];
    }


    /**
     * Creates the Mane Atelier SVG logo and returns its file ID.
     *
     * @return string File ID
     */
    protected function logoFile() : string
    {
        if( !isset( $this->logoFile ) )
        {
            $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 64" role="img" aria-labelledby="title desc">
  <title id="title">Mane Atelier logo</title>
  <desc id="desc">Monogram M drawn as a flowing lock of hair ending in a curl beside the Mane Atelier wordmark</desc>
  <g fill="none" stroke-linecap="round" stroke-linejoin="round">
    <path d="M4 60C4 32 8 6 17 6S28 30 30 37C32 30 34 6 43 6S56 32 56 50c0 6-5 9-9 6.5s-1.5-8 3-7" stroke="#9A5A43" stroke-width="2.6"/>
    <path d="M10.5 60C10.5 38 13 17 18 15" stroke="#D9B8A8" stroke-width="1.4"/>
    <path d="M49.5 42C49.5 31 46 17 42 15" stroke="#D9B8A8" stroke-width="1.4"/>
  </g>
  <text x="74" y="36" fill="#1F1A17" font-family="'Cormorant Garamond', 'Hoefler Text', Baskerville, 'Iowan Old Style', Georgia, serif" font-size="36" letter-spacing="8">MANE</text>
  <text x="76" y="56" fill="#9A5A43" font-family="system-ui, -apple-system, 'Segoe UI', sans-serif" font-size="11.5" letter-spacing="4.2">ATELIER · MÜNCHEN</text>
</svg>
SVG;

            $this->logoFile = $this->svgFile(
                $svg,
                'mane-atelier-logo.svg',
                'Mane Atelier logo',
                'Monogram M drawn as a flowing lock of hair ending in a curl beside the Mane Atelier wordmark',
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
            'text' => "**Mane Atelier**\nHohenzollernstraße 42 · 80801 München-Schwabing\n\n**Opening hours**\nTuesday and Wednesday 09:00–19:00\nThursday and Friday 09:00–20:00\nSaturday 08:00–16:00\n\n**Call**\n+49 89 2345 6780\n\n**Getting here**\nU2 to Hohenzollernplatz, five minutes on foot. Bikes can be parked in our courtyard.",
            'location' => [
                'latitude' => 48.1616,
                'longitude' => 11.5795,
                'zoom' => 16,
            ],
            'button' => 'Open in OpenStreetMap',
        ]];
    }


    /**
     * Creates a Mane demo page below the given parent and returns it.
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

        return $this->savePage( $data, $content, $parent, $elementId, $fileId, $footer, 'Mane Atelier, hair salon Munich, hairdresser Schwabing, balayage, curl cut, bridal hair, vegan colour' );
    }


    /**
     * Builds the Mane demo page tree.
     */
    protected function pages() : void
    {
        $this->tipsId = (string) Str::uuid7();
        $home = $this->home();

        $this->addServices( $home )
            ->addTeam( $home )
            ->addNewClients( $home )
            ->addGifts( $home )
            ->addTips( $home )
            ->addVisit( $home )
            ->addBook( $home )
            ->addImprint( $home )
            ->addPrivacy( $home );
    }


    /**
     * Creates a post below the hair tips page.
     *
     * @param Page $parent Hair tips page
     * @param array<string, string> $data Page name, title and path
     * @param string $title Article headline
     * @param string $text Article text
     * @param string $cover PHOTOS key of the cover image
     * @param array<int, array<string, string>> $facts Key facts as figure cards
     * @param array<int, array<string, string>> $steps Steps of the routine
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
                'title' => 'Questions from our clients',
                'items' => $questions,
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Let us *take care of it*',
                'text' => 'Book a free consultation and we find the right cut, colour and care routine for your hair together.',
                'buttons' => [
                    ['label' => 'Book now', 'url' => '/book'],
                    ['label' => 'Services & prices', 'url' => '/services'],
                ],
            ]],
        ], $parent );
    }


    /**
     * Returns the client reviews.
     *
     * @return array<int, array<string, string>> Testimonial items
     */
    protected function reviews() : array
    {
        return [
            ['name' => 'Katharina', 'role' => 'Balayage with Sofia', 'text' => 'For the first time, my blonde looks natural and still bright after three months. Sofia took so much time to understand what I wanted.'],
            ['name' => 'Nadia', 'role' => 'Curl cut with Amara', 'text' => 'I spent years straightening my hair. One curl cut and a styling lesson later, I wear my curls every day.'],
            ['name' => 'Felix', 'role' => 'Cut with Jonas', 'text' => 'Quick, precise and the best head massage in Munich. I don\'t book anywhere else anymore.'],
        ];
    }


    /**
     * Returns the signature services element.
     *
     * @return array<string, mixed> Pricing content element
     */
    protected function signatures() : array
    {
        return ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
            'title' => 'Signature services',
            'text' => 'Every service includes a consultation, our wash ritual with head massage and a finish. Prices with a stylist, for short hair.',
            'items' => [
                [
                    'name' => 'Cut & finish',
                    'file' => ['id' => $this->img( 'blow-dry' ), 'type' => 'file'],
                    'prices' => [['id' => 'cut', 'amount' => 78, 'label' => 'from €78']],
                    'text' => 'Precision cut, styled by hand.',
                    'features' => "- Consultation and wash ritual\n- Cut to your face shape\n- Blow-dry and styling tips",
                    'url' => '/services',
                    'button' => 'All cut prices',
                ],
                [
                    'name' => 'Balayage',
                    'file' => ['id' => $this->img( 'balayage' ), 'type' => 'file'],
                    'prices' => [['id' => 'balayage', 'amount' => 220, 'label' => 'from €220']],
                    'text' => 'Hand-painted, soft and seamless.',
                    'features' => "- Toner and bond protection\n- Treatment and finish\n- Grows out without a line",
                    'url' => '/colour',
                    'button' => 'Discover colour',
                    'highlight' => true,
                    'badge' => 'Most booked',
                ],
                [
                    'name' => 'Curl cut',
                    'file' => ['id' => $this->img( 'curls' ), 'type' => 'file'],
                    'prices' => [['id' => 'curl', 'amount' => 95, 'label' => 'from €95']],
                    'text' => 'Cut dry, curl by curl.',
                    'features' => "- Curl hydration treatment\n- Diffusing and definition\n- Styling lesson for home",
                    'url' => '/curl-care',
                    'button' => 'Curl care guide',
                ],
            ],
        ]];
    }


    /**
     * Returns the stylist card element.
     *
     * @return array<string, mixed> Cards content element
     */
    protected function team() : array
    {
        $people = [
            ['Lena Hofmann', 'stylist-1', "**Founder & creative director**\nPrecision cuts and soft, lived-in blondes. Trained in London and Milan."],
            ['Amara Okafor', 'stylist-2', "**Curl specialist**\nDry curl cuts and routines for curly and coily hair of every type."],
            ['Sofia Marino', 'stylist-3', "**Colour director**\nBalayage, colour correction and the perfect gloss for every skin tone."],
            ['Jonas Weber', 'stylist-4', "**Senior stylist & barber**\nShort cuts, fades and beard care, with a famously good head massage."],
        ];

        return ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
            'title' => 'Meet your stylists',
            'columns' => '4',
            'cards' => array_map( fn( $person ) => [
                'title' => $person[0],
                'text' => $person[2],
                'file' => ['id' => $this->cropped( $person[1], 800, 1000 ), 'type' => 'file'],
            ], $people ),
        ]];
    }


    /**
     * Creates the hair tips overview page and returns it.
     *
     * @param Page $home Home page
     * @return Page Hair tips page
     */
    protected function tips( Page $home ) : Page
    {
        return $this->page( [
            'id' => $this->tipsId,
            'lang' => 'en',
            'name' => 'Hair tips',
            'title' => 'Hair Tips: Care Guides, Colour and Bridal Advice | Mane Atelier',
            'path' => 'hair-tips',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Notes from *the chair*',
                'subtitle' => 'Hair tips',
                'text' => 'Care routines, colour advice and bridal tips from our stylists, written for the time between your appointments.',
                'background' => ['id' => $this->wide( 'flatlay' ), 'type' => 'file'],
            ]],
            ['id' => 'tips-list', 'type' => 'blog', 'group' => 'main', 'data' => [
                'layout' => 'cards',
                'parent-page' => ['value' => $this->tipsId, 'label' => 'Hair tips'],
                'order' => '_lft',
                'limit' => 12,
            ]],
        ], $home );
    }


    /**
     * Returns the before and after transformation element.
     *
     * @param string $title Headline
     * @return array<string, mixed> Before-after content element
     */
    protected function transformation( string $title ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'before-after', 'group' => 'main', 'data' => [
            'title' => $title,
            'before' => ['id' => $this->cropped( 'balayage-before', 1200, 800 ), 'type' => 'file'],
            'after' => ['id' => $this->cropped( 'balayage', 1200, 800 ), 'type' => 'file'],
        ]];
    }


    /**
     * Creates (once) a 16:9 full width hero image and returns its file ID.
     *
     * @param string $key PHOTOS key
     * @return string File ID
     */
    protected function wide( string $key ) : string
    {
        return $this->cropped( $key, 1920, 1080, true, [960, 1920] );
    }
}
