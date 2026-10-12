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
 * Glow theme demo for the fictional Lumi Beauty Studio in Hamburg.
 */
class GlowDemo extends AbstractDemo
{
    /** @var array<string, string> Meta descriptions keyed by page path */
    protected const DESCRIPTIONS = [
        'book' => 'Book your treatment at Lumi Beauty Studio in Hamburg-Eppendorf online. Choose your treatment and therapist, we confirm within two hours.',
        'careers' => 'Beauty therapist, nail artist and lash artist jobs in Hamburg: work at Lumi with paid training, a four-day week and your own clients.',
        'facials' => 'Facials in Hamburg: signature glow facial, hydra deep cleanse, microneedling and peels, always with a skin analysis first.',
        'gift-cards' => 'Lumi gift cards for facials, nails, lashes and massages in Hamburg, valid for three years.',
        'imprint' => 'Legal notice of Lumi Beauty Studio GmbH, Hamburg.',
        'journal' => 'The Lumi journal: skin care guides, lash and nail aftercare and seasonal routines from our therapists in Hamburg.',
        'lash-lift-aftercare' => 'Lash lift aftercare: what to avoid in the first 48 hours, how to make your lift last and when to book the next one.',
        'lashes-brows' => 'Lash lift, lash extensions and brow lamination in Hamburg-Eppendorf, with a patch test before your first treatment.',
        'membership' => 'The Lumi Glow Club: a monthly facial or massage, member prices on all treatments and unused credits that roll over.',
        'nails' => 'Manicure, gel nails and spa pedicure in Hamburg-Eppendorf with vegan polishes and single-use files.',
        'new-clients' => 'Your first visit at Lumi: skin analysis, patch tests, what to prepare and our booking and cancellation policy.',
        'privacy' => 'Privacy policy of Lumi Beauty Studio GmbH, Hamburg.',
        'team' => 'Meet the therapists of Lumi Beauty Studio in Hamburg: facial, lash, brow, nail and massage specialists.',
        'treatments' => 'Treatments and prices at Lumi in Hamburg: facials, skin treatments, nails, lashes and brows, waxing and massage.',
        'visit' => 'Visit Lumi Beauty Studio on Eppendorfer Landstraße in Hamburg: opening hours, directions, parking and accessibility.',
        'which-facial' => 'Which facial is right for your skin? How hydra, signature and microneedling facials differ, what they cost and how often to come.',
        'winter-skin' => 'Winter skin care: five habits that keep your skin calm, hydrated and glowing from November to March.',
    ];

    /**
     * Curated Unsplash photos used by the beauty studio demo.
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3?: string}>
     */
    protected const PHOTOS = [
        'body-brush' => ['photo-1706448703260-2ad13853602c', 'Body treatment', 'Dry-brushing an arm with a natural bristle brush beside gua sha and cupping tools'],
        'brows' => ['photo-1718720410649-7524fcb0f0a5', 'Brow styling', 'Beautician working on a client\'s brows with a fine brush', 'faces'],
        'candles' => ['photo-1620733723572-11c53f73a416', 'Candles and diffuser', 'Reed diffuser, rolled white towel and two lit candles on a pale background'],
        'facial' => ['photo-1552693673-1bf958298935', 'Facial treatment', 'Relaxed woman receiving a facial from gloved hands', 'faces'],
        'facial-cream' => ['photo-1616394584738-fc6e612e71b9', 'Facial mask', 'Woman with a white facial mask while the therapist holds a bowl of cream', 'faces'],
        'facial-mask' => ['photo-1570172619644-dfd03ed5d881', 'Clay mask', 'Therapist brushing a clay mask onto a relaxed woman\'s face', 'faces'],
        'facial-massage' => ['photo-1706795034830-de41aee06afa', 'Facial massage', 'Woman lying on a rolled towel while hands massage her temples', 'faces'],
        'facial-massage-2' => ['photo-1706795033728-9232ef548a16', 'Face and neck massage', 'Therapist massaging the face of a client lying under a soft towel'],
        'gift-box' => ['photo-1549465220-1a8b9238cd48', 'Gift box', 'Gift box tied with a gold satin ribbon on a blush background'],
        'glowing-skin' => ['photo-1581182800629-7d90925ad072', 'Glowing skin', 'Woman with dewy, glowing skin and closed eyes in warm sunlight', 'faces'],
        'gua-sha' => ['photo-1643379855889-850035817d24', 'Gua sha', 'Woman using rose quartz and jade gua sha stones on her face', 'faces'],
        'happy-client' => ['photo-1728727267814-792db55ce678', 'Happy client', 'Laughing woman with clear skin touching her cheek', 'faces'],
        'hot-stones' => ['photo-1600334089648-b0d9d3028eb2', 'Hot stones', 'Row of warm basalt stones along a woman\'s back with white orchids'],
        'lashes' => ['photo-1674049406467-824ea37c7184', 'Lash extensions', 'Lash artist applying eyelash extensions with tweezers', 'faces'],
        'makeup' => ['photo-1709477542170-f11ee7d471a0', 'Makeup application', 'Makeup artist applying eyeshadow with a fine brush against a peach background', 'faces'],
        'makeup-eye' => ['photo-1709477542149-f4e0e21d590b', 'Eye makeup', 'Brush applying shimmering bronze eyeshadow to an eyelid', 'faces'],
        'manicure' => ['photo-1632345031435-8727f6897d53', 'Manicure', 'Nail artist shaping a client\'s nails with a cuticle tool'],
        'massage' => ['photo-1544161515-4ab6ce6db874', 'Back massage', 'Therapist warming massage oil in her hands above a relaxed client'],
        'nails-closeup' => ['photo-1610992015732-2449b76344bc', 'Nude nails', 'Hands with glossy nude-pink almond nails on a fluffy white rug'],
        'nails-pastel' => ['photo-1607779097040-26e80aa78e66', 'Pastel nails', 'Hand with a lilac, white and silver glitter manicure on a cream knit sweater'],
        'pedicure' => ['photo-1519415510236-718bdfcd89c8', 'Pedicure', 'Feet soaking in a pedicure bowl with pink and green flowers'],
        'serum-dropper' => ['photo-1617897903246-719242758050', 'Facial serum', 'Glass dropper bottle of golden serum with eucalyptus and white linen'],
        'skin-towel' => ['photo-1544717304-a2db4a7b16ee', 'Smooth skin', 'Woman in a white towel touching her chin in profile', 'faces'],
        'skincare-flatlay' => ['photo-1600428877878-1a0fd85beda8', 'Skincare tools', 'Rose quartz face roller, gua sha stone and a dropper serum bottle on white'],
        'skincare-products' => ['photo-1597931752949-98c74b5b159f', 'Body lotion', 'Plain white pump bottle on draped taupe linen'],
        'skincare-texture' => ['photo-1694147185194-f91cd8a50680', 'Skincare textures', 'Swatches of pink gel, cream and clear serum on a beige surface'],
        'spa-still-life' => ['photo-1540555700478-4be289fbecef', 'Treatment table', 'Rolled towel, pump bottle, tea light and pink tulips on a white table'],
        'spa-towels' => ['photo-1706795033855-eee02f726868', 'Spa towels', 'Two rolled beige towels with a tea light on a treatment bed'],
        'team-1' => ['photo-1580489944761-15a19d654956', 'Mila Brandt', 'Portrait of a smiling woman with dark hair in a bun', 'faces'],
        'team-2' => ['photo-1685688739798-bce206ab6b42', 'Hannah Weiss', 'Portrait of a woman with long brown hair in a white blouse', 'faces'],
        'team-3' => ['photo-1710196598824-ca4ca5fefae4', 'Zoe Asante', 'Portrait of a smiling woman with hoop earrings', 'faces'],
        'team-4' => ['photo-1507003211169-0a1dd7228f2d', 'Lukas Petersen', 'Portrait of a smiling man with short dark hair and a beard', 'faces'],
        'treatment-room' => ['photo-1745327883290-1e9c6447b938', 'Treatment room', 'Bright treatment room with a made-up treatment bed and a warm floor lamp'],
    ];

    private string $element;
    private string $journalId;
    /** @var array<string, string> */
    private array $icons = [];
    private string $logoFile;


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
            'name' => 'Book now',
            'title' => 'Book a Treatment | Lumi Beauty Studio Hamburg',
            'path' => 'book',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Time for *you*',
                'subtitle' => 'Book a treatment',
                'text' => 'Tell us which treatment you would like and when you can come. We confirm your appointment within two hours during opening times.',
                'files' => [['id' => $this->capsule( 'skincare-products' ), 'type' => 'file']],
            ]],
            ['id' => 'booking-form', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Your appointment',
                'description' => 'Not sure which treatment suits you? Choose "Skin consultation" and we take 20 minutes to look at your skin first, free of charge.',
                'inputs' => [
                    ['field' => 'name', 'required' => true, 'input' => 'text'],
                    ['field' => 'email', 'required' => true, 'input' => 'text'],
                    ['field' => 'telephone', 'required' => true, 'input' => 'text'],
                    ['field' => 'Treatment', 'required' => true, 'input' => 'select', 'options' => "Skin consultation\nFacial\nMicroneedling or peel\nManicure or pedicure\nLash lift\nLash extensions\nBrows\nWaxing\nMassage"],
                    ['field' => 'Therapist', 'required' => false, 'input' => 'select', 'options' => "No preference\nMila\nHannah\nZoe\nLukas"],
                    ['field' => 'Preferred date', 'required' => true, 'input' => 'text'],
                    ['field' => 'Allergies, sensitive skin or wishes', 'required' => false, 'input' => 'textarea'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Booking questions',
                'items' => [
                    ['title' => 'Can I cancel or move my appointment?', 'text' => 'Yes, free of charge up to 24 hours before. Later cancellations and no-shows are charged at 50 percent of the treatment.'],
                    ['title' => 'Do I need a patch test?', 'text' => 'Before your first lash lift, lash extensions or tint, yes. It takes five minutes and has to be done at least 48 hours before.'],
                    ['title' => 'Can I book two treatments in a row?', 'text' => 'Of course. Book your facial before lashes or brows, so the products don\'t touch freshly lifted or tinted hair.'],
                ],
            ]],
        ], $home );

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
            'title' => 'Beauty Therapist Jobs in Hamburg | Lumi Beauty Studio',
            'path' => 'careers',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Do what you love, *with time to do it well*',
                'subtitle' => 'Careers',
                'text' => 'We are nine therapists, nail and lash artists. Everyone works four days a week, gets enough time for every client and paid days for training.',
                'buttons' => [
                    ['label' => 'Send your application', 'url' => 'mailto:jobs@lumibeauty.example'],
                ],
                'files' => [['id' => $this->capsule( 'makeup' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'spa-still-life' ), 'type' => 'file'],
                'position' => 'grid-end',
                'ratio' => '1-1',
                'text' => "## What you get\n\n- A salary above the industry average and 10 percent of your product sales\n- A four-day week with two days off in a row\n- Ten paid training days a year for new techniques and devices\n- At least 15 minutes between two appointments\n- Free treatments for you, every month",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Open positions',
                'columns' => '3',
                'cards' => [
                    ['title' => 'Beauty therapist (m/f/d)', 'text' => "**Full time**\nTrained cosmetician with experience in facials, peels and microneedling."],
                    ['title' => 'Lash & brow artist (m/f/d)', 'text' => "**Full or part time**\nLash lifts, extensions and brow lamination are your favourite part of the day."],
                    ['title' => 'Nail artist (m/f/d)', 'text' => "**Part time**\nClean gel work, a steady hand and a love for small details."],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Come by for a *tea*',
                'text' => 'Send us a few lines and photos of your work. A CV is welcome but not required, and every application gets an answer within a week.',
                'buttons' => [
                    ['label' => 'Send your application', 'url' => 'mailto:jobs@lumibeauty.example'],
                    ['label' => 'Meet the team', 'url' => '/team'],
                ],
            ]],
        ], $parent );

        return $this;
    }


    /**
     * Creates the facials page below the treatments page.
     *
     * @param Page $parent Treatments page
     * @return static Same object for fluent calls
     */
    protected function addFacials( Page $parent ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Facials',
            'title' => 'Facials in Hamburg: Signature Glow, Hydra and Microneedling | Lumi',
            'path' => 'facials',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Facials for *your* skin, not for a skin type',
                'subtitle' => 'Facials',
                'text' => 'Every facial starts with a skin analysis under a magnifying lamp. Then we choose the cleanse, the active ingredients and the massage that your skin needs today.',
                'buttons' => [
                    ['label' => 'Book a facial', 'url' => '/book'],
                    ['label' => 'See all prices', 'url' => '/treatments'],
                ],
                'files' => [['id' => $this->capsule( 'facial' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Our facials',
                'columns' => '3',
                'cards' => [
                    ['title' => 'Signature glow facial', 'text' => "**€119** `60 min`\nDouble cleanse, enzyme peel, extractions, mask and our lymphatic face massage.", 'file' => ['id' => $this->img( 'facial-cream' ), 'type' => 'file']],
                    ['title' => 'Hydra deep cleanse', 'text' => "**€139** `75 min`\nVacuum cleansing and hyaluronic infusion for congested or dull skin.", 'file' => ['id' => $this->img( 'facial-mask' ), 'type' => 'file']],
                    ['title' => 'Microneedling', 'text' => "**€189** `75 min`\nFine needles boost collagen for scars, pores and first lines. Best as a series of three.", 'file' => ['id' => $this->img( 'glowing-skin' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'Your signature facial',
                'layout' => 'vertical',
                'items' => [
                    ['label' => '10 min', 'title' => 'Skin analysis', 'text' => 'Under the magnifying lamp, with questions about your routine.'],
                    ['label' => '15 min', 'title' => 'Cleanse & peel', 'text' => 'A double cleanse and a gentle enzyme peel.'],
                    ['label' => '15 min', 'title' => 'Extractions & serum', 'text' => 'Only where needed, followed by a serum for your skin.'],
                    ['label' => '20 min', 'title' => 'Massage & mask', 'text' => 'Face, neck and shoulders while the mask works.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Facial questions',
                'items' => [
                    ['title' => 'How should I prepare?', 'text' => 'No retinol, peels or scrubs for three days before, and no waxing or shaving of the face the day before. Come without make-up if you can.'],
                    ['title' => 'How often should I have a facial?', 'text' => 'Every four to six weeks, which is about one cycle of skin renewal.'],
                    ['title' => 'Will my skin be red afterwards?', 'text' => 'After a signature facial, rarely. After microneedling, plan a quiet evening, the redness is usually gone the next morning.'],
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
            'title' => 'Gift Cards for Facials, Nails and Massages | Lumi Hamburg',
            'path' => 'gift-cards',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
                'title' => 'Give an hour of *glow*',
                'text' => 'Our gift cards come in a blush envelope, by post or to pick up at the studio. They are valid for three years and can be used for every treatment and product.',
                'items' => [
                    [
                        'name' => 'Glow hour',
                        'file' => ['id' => $this->img( 'skincare-flatlay' ), 'type' => 'file'],
                        'prices' => [['id' => 'glow', 'kind' => 'once', 'currency' => 'EUR', 'amount' => 119, 'label' => '€119']],
                        'text' => 'Our signature facial.',
                        'features' => "- Skin analysis\n- Signature glow facial\n- Face and neck massage\n- Valid for three years",
                        'url' => 'mailto:hello@lumibeauty.example?subject=Gift%20card',
                        'button' => 'Order the glow hour',
                    ],
                    [
                        'name' => 'Lumi day',
                        'file' => ['id' => $this->img( 'gift-box' ), 'type' => 'file'],
                        'prices' => [['id' => 'day', 'kind' => 'once', 'currency' => 'EUR', 'amount' => 229, 'label' => '€229']],
                        'text' => 'Three hours just for her or him.',
                        'features' => "- Signature glow facial\n- Relax massage, 60 minutes\n- Classic manicure\n- Tea and a small care set",
                        'url' => 'mailto:hello@lumibeauty.example?subject=Gift%20card',
                        'button' => 'Order the Lumi day',
                        'highlight' => true,
                        'badge' => 'Most given',
                    ],
                    [
                        'name' => 'Open value',
                        'file' => ['id' => $this->img( 'candles' ), 'type' => 'file'],
                        'prices' => [['id' => 'value', 'kind' => 'once', 'currency' => 'EUR', 'amount' => 25, 'label' => 'from €25']],
                        'text' => 'You choose the amount.',
                        'features' => "- In steps of €25\n- For treatments and products\n- Partial use possible\n- Valid for three years",
                        'url' => 'mailto:hello@lumibeauty.example?subject=Gift%20card',
                        'button' => 'Order a gift card',
                    ],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Gift card questions',
                'items' => [
                    ['title' => 'How fast do you send them?', 'text' => 'Orders before noon go out the same day. You can also pick them up at the reception.'],
                    ['title' => 'Is there a digital version?', 'text' => 'Yes. We email you a PDF that you can print or forward.'],
                    ['title' => 'Can the gift card be used for a membership?', 'text' => 'Yes, for the first months of the Glow Club or for any treatment and product.'],
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
            'title' => 'Imprint | Lumi Beauty Studio',
            'path' => 'imprint',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "# Imprint\n\n**Lumi Beauty Studio GmbH**\nEppendorfer Landstraße 61\n20249 Hamburg\nGermany\n\nTelephone: +49 40 4689 2210\nEmail: hello@lumibeauty.example\n\nManaging director: Mila Brandt\nCommercial register: Hamburg Local Court, HRB 00000\nVAT ID: DE000000000\n\nState-certified cosmetician.\n\n## Consumer dispute resolution\n\nWe are neither willing nor obliged to take part in dispute resolution proceedings before a consumer arbitration board.\n\nThis is a demo website for the Glow theme. Lumi Beauty Studio is a fictional studio and the people named are not real.",
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
            'name' => 'Which facial?',
            'title' => 'Which Facial Is Right for Your Skin?',
            'path' => 'which-facial',
        ], 'Which facial is right for your skin?',
            "Signature, hydra or microneedling: the names sound alike, but the treatments do very different things. A signature facial calms and brightens. A hydra facial cleans pores deeply and floods the skin with moisture. Microneedling works below the surface and builds new collagen over weeks.\n\nWhich one suits you depends on what bothers you most, how sensitive your skin is and how much time you have afterwards. Here is how we decide together in the skin analysis.",
            'facial-mask',
            [
                ['title' => '28 days', 'text' => 'One cycle of skin renewal'],
                ['title' => '3×', 'text' => 'Microneedling sessions in a series'],
                ['title' => '10 min', 'text' => 'Skin analysis before every facial'],
            ],
            [
                ['label' => 'Step 1', 'title' => 'Name your concern', 'text' => 'Dullness, blemishes, pores, dryness or first lines.'],
                ['label' => 'Step 2', 'title' => 'Check your skin', 'text' => 'Sensitive or robust, oily or dry, calm or reactive.'],
                ['label' => 'Step 3', 'title' => 'Choose the facial', 'text' => 'Signature to calm, hydra to clean, microneedling to renew.'],
                ['label' => 'Step 4', 'title' => 'Plan your rhythm', 'text' => 'Every four to six weeks keeps the results.'],
            ],
            [
                ['title' => 'Is microneedling painful?', 'text' => 'We apply a numbing cream first. Most clients feel a light scratching, nothing more.'],
                ['title' => 'Can I have a facial with acne?', 'text' => 'Yes. We avoid heavy massage on inflamed areas and focus on gentle cleansing and calming.'],
                ['title' => 'When can I wear make-up again?', 'text' => 'After a signature or hydra facial, the next morning. After microneedling, wait 24 hours.'],
            ],
        );

        $this->post( $journal, [
            'name' => 'Lash lift aftercare',
            'title' => 'Lash Lift Aftercare: The First 48 Hours and Beyond',
            'path' => 'lash-lift-aftercare',
        ], 'Lash lift aftercare: the first 48 hours',
            "A lash lift curls your natural lashes from the root for six to eight weeks, without extensions and without glue. Whether it lasts six weeks or eight depends mostly on the first two days, when the new shape is still setting.\n\nOur lash artist Hannah gives every client the same short list to take home. These are the habits that make the biggest difference.",
            'lashes',
            [
                ['title' => '48 h', 'text' => 'Without water, steam or mascara'],
                ['title' => '6–8 wks', 'text' => 'Until your lashes grow out'],
                ['title' => '60 min', 'text' => 'For a lift with tint'],
            ],
            [
                ['label' => 'Day 1–2', 'title' => 'Keep them dry', 'text' => 'No water, sauna, steam or eye make-up while the shape sets.'],
                ['label' => 'Night', 'title' => 'Sleep on your back', 'text' => 'Or on a silk pillow, so the lashes don\'t get pressed flat.'],
                ['label' => 'Daily', 'title' => 'Brush and nourish', 'text' => 'A clean spoolie and a drop of lash serum in the evening.'],
                ['label' => 'Week 6', 'title' => 'Book the next lift', 'text' => 'Not earlier, so your lashes stay healthy.'],
            ],
            [
                ['title' => 'Can I wear mascara after a lash lift?', 'text' => 'Yes, after 48 hours. Choose a water-based mascara and remove it gently with an oil-free cleanser.'],
                ['title' => 'Is a lash lift bad for my lashes?', 'text' => 'Not if you keep six weeks between lifts. We use a gentle, keratin-based lotion.'],
                ['title' => 'Why do I need a patch test?', 'text' => 'Lift lotion and tint can cause reactions. The patch test 48 hours before shows if your skin tolerates them.'],
            ],
        );

        $this->post( $journal, [
            'name' => 'Winter skin',
            'title' => 'Winter Skin: Five Habits for a Healthy Glow',
            'path' => 'winter-skin',
        ], 'Five habits for winter skin',
            "Cold air outside, heating inside: in winter, your skin loses moisture faster than it can replace it. The result is tightness, redness and that dull, grey look that no highlighter can hide.\n\nYou don't need a new shelf of products. Five small changes in your routine are usually enough to keep your skin calm and glowing until spring.",
            'skincare-texture',
            [
                ['title' => '40 %', 'text' => 'Ideal humidity in your bedroom'],
                ['title' => 'SPF 30', 'text' => 'Also on grey winter days'],
                ['title' => '1×', 'text' => 'Gentle exfoliation per week'],
            ],
            [
                ['label' => 'Morning', 'title' => 'Cleanse with water', 'text' => 'Just lukewarm water and a moisturiser, the night cleanse is enough.'],
                ['label' => 'Day', 'title' => 'Richer cream, daily SPF', 'text' => 'Switch to a cream with ceramides and keep your sun protection.'],
                ['label' => 'Evening', 'title' => 'Oil cleanse', 'text' => 'A cleansing oil removes make-up without stripping the skin.'],
                ['label' => 'Monthly', 'title' => 'Hydra facial', 'text' => 'Deep moisture and a fresh start for the next four weeks.'],
            ],
            [
                ['title' => 'Should I stop exfoliating in winter?', 'text' => 'No, but reduce it to once a week and choose an enzyme peel instead of a scrub.'],
                ['title' => 'Why is my skin oily and dry at the same time?', 'text' => 'Dehydrated skin often makes more oil. It needs water-binding ingredients, not stronger cleansers.'],
                ['title' => 'Do I really need sunscreen in winter?', 'text' => 'Yes. UVA rays pass through clouds and windows and are the main reason for early lines.'],
            ],
        );

        return $this;
    }


    /**
     * Creates the lashes and brows page below the treatments page.
     *
     * @param Page $parent Treatments page
     * @return static Same object for fluent calls
     */
    protected function addLashes( Page $parent ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Lashes & brows',
            'title' => 'Lash Lift, Lash Extensions and Brow Lamination in Hamburg | Lumi',
            'path' => 'lashes-brows',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Wake up with *open eyes*',
                'subtitle' => 'Lashes & brows',
                'text' => 'Lifted lashes, soft extensions and brows that frame your face, designed for your eye shape and done with gentle, vegan products.',
                'buttons' => [
                    ['label' => 'Book lashes or brows', 'url' => '/book'],
                    ['label' => 'Read the aftercare guide', 'url' => '/lash-lift-aftercare'],
                ],
                'files' => [['id' => $this->capsule( 'lashes' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Our lash & brow treatments',
                'columns' => '3',
                'cards' => [
                    ['title' => 'Lash lift & tint', 'text' => "**€75** `60 min`\nYour own lashes, curled from the root and tinted, for six to eight weeks.", 'file' => ['id' => $this->img( 'makeup-eye' ), 'type' => 'file']],
                    ['title' => 'Lash extensions', 'text' => "**From €149** `120 min`\nClassic or soft volume, always matched to the strength of your natural lashes.", 'file' => ['id' => $this->img( 'lashes' ), 'type' => 'file']],
                    ['title' => 'Brow lamination', 'text' => "**€65** `45 min`\nFuller, brushed-up brows with shape and tint, for up to six weeks.", 'file' => ['id' => $this->img( 'brows' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'Before and after your appointment',
                'layout' => 'horizontal',
                'items' => [
                    ['label' => '48 h before', 'title' => 'Patch test', 'text' => 'Five minutes at the studio for every new client.'],
                    ['label' => 'On the day', 'title' => 'No mascara', 'text' => 'Come with clean lashes and without contact lenses.'],
                    ['label' => '48 h after', 'title' => 'Keep it dry', 'text' => 'No water, steam or sauna while the shape sets.'],
                    ['label' => '6 weeks', 'title' => 'Refresh', 'text' => 'Book your next lift or refill.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Lash & brow questions',
                'items' => [
                    ['title' => 'Lash lift or extensions?', 'text' => 'A lash lift if you like a natural look and little upkeep. Extensions if you want more length and density and are happy with a refill every three weeks.'],
                    ['title' => 'Can I combine treatments?', 'text' => 'Yes, a lash lift and brow lamination fit into 90 minutes. Facials always come first.'],
                    ['title' => 'Do you remove extensions from other studios?', 'text' => 'Yes, gently and with a cream remover, for €25 or free with a new set.'],
                ],
            ]],
        ], $parent );

        return $this;
    }


    /**
     * Creates the membership page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addMembership( Page $home ) : static
    {
        $sub = fn( string $id, int $amount ) => [
            'id' => $id, 'kind' => 'subscription', 'interval' => 30, 'currency' => 'EUR', 'amount' => $amount, 'label' => '€' . $amount, 'unit' => 'per month',
        ];

        $this->page( [
            'lang' => 'en',
            'name' => 'Membership',
            'title' => 'Glow Club Membership: Monthly Facials and Member Prices | Lumi Hamburg',
            'path' => 'membership',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Good skin is a *habit*',
                'subtitle' => 'Glow Club',
                'text' => 'One treatment every month, member prices on everything else and no contract longer than three months. Credits you don\'t use roll over.',
                'buttons' => [
                    ['label' => 'Choose your plan', 'url' => '#plans'],
                    ['label' => 'Book a skin consultation', 'url' => '/book'],
                ],
                'files' => [['id' => $this->capsule( 'gua-sha' ), 'type' => 'file']],
            ]],
            ['id' => 'plans', 'type' => 'pricing', 'group' => 'main', 'data' => [
                'title' => 'Choose your plan',
                'text' => 'All plans start with a free skin consultation. You can pause for up to two months a year and cancel with one month\'s notice after the first three months.',
                'items' => [
                    [
                        'name' => 'Essential',
                        'prices' => [$sub( 'essential', 79 )],
                        'text' => 'For a monthly reset.',
                        'features' => "- One express facial or back massage per month\n- 15 % off all other treatments\n- 10 % off skin care products\n- Unused credits roll over",
                        'url' => '/book',
                        'button' => 'Start Essential',
                    ],
                    [
                        'name' => 'Signature',
                        'prices' => [$sub( 'signature', 109 )],
                        'text' => 'Our favourite rhythm.',
                        'features' => "- One signature facial or relax massage per month\n- 20 % off all other treatments\n- 10 % off skin care products\n- A birthday treat on us",
                        'url' => '/book',
                        'button' => 'Start Signature',
                        'highlight' => true,
                        'badge' => 'Most chosen',
                    ],
                    [
                        'name' => 'Total glow',
                        'prices' => [$sub( 'total', 169 )],
                        'text' => 'Skin, lashes and nails.',
                        'features' => "- One signature facial per month\n- Plus a gel manicure or lash lift\n- 25 % off all other treatments\n- 15 % off skin care products",
                        'url' => '/book',
                        'button' => 'Start Total glow',
                    ],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'What members pay',
                'header' => 'row+col',
                'table' => [
                    ['', 'Regular', 'Essential', 'Signature'],
                    ['Hydra deep cleanse', '€139', '€118', '€111'],
                    ['Microneedling', '€189', '€161', '€151'],
                    ['Lash lift & tint', '€75', '€64', '€60'],
                    ['Gel manicure', '€52', '€44', '€42'],
                    ['Hot stone massage', '€109', '€93', '€87'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Membership questions',
                'items' => [
                    ['title' => 'What happens to credits I don\'t use?', 'text' => 'They roll over for up to three months, so a busy month never goes to waste.'],
                    ['title' => 'Can I share my membership?', 'text' => 'You can give a monthly treatment to a friend twice a year. The discounts are for you only.'],
                    ['title' => 'How do I pause or cancel?', 'text' => 'Write us an email. Pausing works from the next month, cancelling with one month\'s notice after the first three months.'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the nails page below the treatments page.
     *
     * @param Page $parent Treatments page
     * @return static Same object for fluent calls
     */
    protected function addNails( Page $parent ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Nails',
            'title' => 'Manicure, Gel Nails and Pedicure in Hamburg-Eppendorf | Lumi',
            'path' => 'nails',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Nails that *last* and look effortless',
                'subtitle' => 'Nails',
                'text' => 'Careful cuticle work, a perfect shape and a finish that stays glossy for weeks. With vegan polishes, single-use files and sterilised tools for every client.',
                'buttons' => [
                    ['label' => 'Book your nails', 'url' => '/book'],
                    ['label' => 'See all prices', 'url' => '/treatments'],
                ],
                'files' => [['id' => $this->capsule( 'nails-pastel' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Our nail treatments',
                'columns' => '3',
                'cards' => [
                    ['title' => 'Gel manicure', 'text' => "**€52** `60 min`\nShape, cuticle care and a gel finish in your colour for up to three weeks.", 'file' => ['id' => $this->img( 'nails-closeup' ), 'type' => 'file']],
                    ['title' => 'Classic manicure', 'text' => "**€39** `45 min`\nFiling, cuticle care, a hand massage and a classic polish.", 'file' => ['id' => $this->img( 'manicure' ), 'type' => 'file']],
                    ['title' => 'Spa pedicure', 'text' => "**€55** `60 min`\nFoot bath, callus care, a peeling and massage, finished with polish.", 'file' => ['id' => $this->img( 'pedicure' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'nails-pastel' ), 'type' => 'file'],
                'position' => 'grid-start',
                'ratio' => '1-1',
                'text' => "## Clean, safe and *gentle*\n\n- **Single-use files** and buffers for every client\n- **Sterilised tools** from our medical-grade autoclave\n- **Vegan, HEMA-free gel** for sensitive nails\n- **Gentle removal** by soaking, never by drilling off the gel",
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Nail questions',
                'items' => [
                    ['title' => 'How long does gel polish last?', 'text' => 'Two to three weeks on fingers and four to six weeks on toes.'],
                    ['title' => 'Do you do nail art?', 'text' => 'Yes, from a single accent nail at €4 to full designs. Bring photos of what you like.'],
                    ['title' => 'Can you remove gel from another studio?', 'text' => 'Of course, for €15 or free with a new gel manicure.'],
                ],
            ]],
        ], $parent );

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
            'title' => 'Your First Visit: Skin Analysis, Patch Tests and Policies | Lumi',
            'path' => 'new-clients',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Welcome to *Lumi*',
                'subtitle' => 'New clients',
                'text' => 'Your first facial starts with a free skin analysis. We look at your skin, listen to what bothers you and only then talk about treatments and prices.',
                'buttons' => [
                    ['label' => 'Book a free consultation', 'url' => '/book'],
                ],
                'files' => [['id' => $this->capsule( 'spa-towels' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'Your first visit',
                'layout' => 'horizontal',
                'items' => [
                    ['label' => 'Arrive', 'title' => 'Tea and a short form', 'text' => 'Come ten minutes early and tell us about allergies and medication.'],
                    ['label' => 'Talk', 'title' => 'Skin analysis', 'text' => 'Your routine, your concerns and what you would like to change.'],
                    ['label' => 'Relax', 'title' => 'Your treatment', 'text' => 'Warm towels, soft light and no rush.'],
                    ['label' => 'Leave', 'title' => 'Home care plan', 'text' => 'Written tips and products that suit your skin.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'serum-dropper' ), 'type' => 'file'],
                'position' => 'grid-start',
                'ratio' => '1-1',
                'text' => "## How to prepare\n\n- No retinol, acids or scrubs three days before a facial\n- No waxing, shaving or tanning of the treated area the day before\n- Come without eye make-up for lashes and brows\n- Bring a list of the products you use, photos are fine",
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Our policies',
                'items' => [
                    ['title' => 'Cancellations', 'text' => 'Free of charge up to 24 hours before. Later cancellations and no-shows are charged at 50 percent of the booked treatment.'],
                    ['title' => 'Running late', 'text' => 'Please call us. If you are more than 15 minutes late, we may have to shorten your treatment to keep the next client\'s time.'],
                    ['title' => 'Patch tests', 'text' => 'Required 48 hours before your first lash lift, lash extensions or tint, and again after a break of six months.'],
                    ['title' => 'Pregnancy', 'text' => 'Please tell us when you book. Some peels and devices are not suitable, but we have gentle alternatives.'],
                    ['title' => 'Age', 'text' => 'Clients under 16 can book manicures and facials with a parent or guardian.'],
                    ['title' => 'Payment', 'text' => 'Cards, phones and cash. Gift cards and membership credits can be combined with any payment.'],
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
            'title' => 'Privacy Policy | Lumi Beauty Studio',
            'path' => 'privacy',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "# Privacy policy\n\n## Who is responsible\n\nLumi Beauty Studio GmbH, Eppendorfer Landstraße 61, 20249 Hamburg, privacy@lumibeauty.example.\n\n## Appointments\n\nWhen you book a treatment, we use your name, contact details and the details you give us to plan and carry out the treatment (Art. 6 (1) (b) GDPR). Your skin analysis and the products used are stored in your client card so we can continue your treatment plan. You can ask us to delete your client card at any time.\n\n## Health information\n\nIf you tell us about allergies, medication, skin conditions or a pregnancy, we store this information with your explicit consent (Art. 9 (2) (a) GDPR) only to protect your health during treatments.\n\n## This website\n\nThe website doesn't use tracking or advertising cookies. Our server stores technical access data such as the IP address for seven days to protect against attacks. The map is loaded from OpenStreetMap only after you open it.\n\n## Your rights\n\nYou have the right to access, rectification, erasure, restriction of processing and data portability, and you can lodge a complaint with the Hamburg Commissioner for Data Protection and Freedom of Information.\n\nThis is a demo website for the Glow theme. Lumi Beauty Studio is a fictional studio.",
            ]],
        ], $home );

        return $this;
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
            'title' => 'Our Therapists: Facial, Lash, Nail and Massage Specialists | Lumi Hamburg',
            'path' => 'team',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Gentle hands, *trained eyes*',
                'subtitle' => 'Our team',
                'text' => 'Nine therapists, nail and lash artists, each with a speciality. Since 2016, Mila Brandt has built a team that takes time for every client.',
                'buttons' => [
                    ['label' => 'Book with your therapist', 'url' => '/book'],
                ],
                'files' => [['id' => $this->capsule( 'facial-massage-2' ), 'type' => 'file']],
            ]],
            $this->team(),
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'Our story',
                'layout' => 'vertical',
                'items' => [
                    ['label' => '2016', 'title' => 'One room', 'text' => 'Mila opens a single treatment room above a flower shop in Eppendorf.'],
                    ['label' => '2018', 'title' => 'Eppendorfer Landstraße', 'text' => 'Three treatment rooms, a nail bar and a quiet tea lounge.'],
                    ['label' => '2020', 'title' => 'Lashes & brows', 'text' => 'Hannah joins and starts our lash and brow studio.'],
                    ['label' => '2023', 'title' => 'Glow Club', 'text' => 'Our membership for everyone who wants a monthly ritual.'],
                    ['label' => '2025', 'title' => 'Recognised', 'text' => 'Named one of the best beauty studios in Hamburg by a city magazine.'],
                ],
            ]],
            $this->ethos(),
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Want to *join us*?',
                'text' => 'We are always looking for therapists and artists who care about their craft and their clients.',
                'buttons' => [
                    ['label' => 'Open positions', 'url' => '/careers'],
                    ['label' => 'Visit the studio', 'url' => '/visit'],
                ],
            ]],
        ], $home );

        return $this->addCareers( $team );
    }


    /**
     * Creates the treatments and prices page and its sub-pages below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addTreatments( Page $home ) : static
    {
        $treatments = $this->page( [
            'lang' => 'en',
            'name' => 'Treatments',
            'title' => 'Treatments and Prices: Facials, Nails, Lashes, Waxing and Massage | Lumi Hamburg',
            'path' => 'treatments',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Treatments & *prices*',
                'subtitle' => 'Our menu',
                'text' => 'Clear prices and honest durations. Every treatment includes a short consultation, warm towels and a few minutes to arrive.',
                'buttons' => [
                    ['label' => 'Book now', 'url' => '/book'],
                    ['label' => 'Member prices', 'url' => '/membership'],
                ],
                'files' => [['id' => $this->capsule( 'serum-dropper' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
                'title' => 'The full menu',
                'text' => 'All prices in euros including VAT. Glow Club members save 15 to 25 percent on every treatment.',
                'items' => [
                    [
                        'name' => 'Facials',
                        'prices' => [['id' => 'facials', 'label' => 'From €59']],
                        'text' => 'With skin analysis and massage.',
                        'features' => "- Express glow `30 min` **59**\n- Signature glow `60 min` **119**\n- Hydra deep cleanse `75 min` **139**\n- Calming facial `60 min` **109**\n- LED add-on `20 min` **29**",
                    ],
                    [
                        'name' => 'Skin treatments',
                        'prices' => [['id' => 'skin', 'label' => 'From €75']],
                        'text' => 'Results in a series.',
                        'features' => "- Dermaplaning `45 min` **75**\n- Enzyme peel `45 min` **85**\n- Chemical peel `45 min` **95**\n- Microneedling `75 min` **189**\n- Series of three `save 15 %` **480**",
                        'highlight' => true,
                        'badge' => 'Our speciality',
                    ],
                    [
                        'name' => 'Nails',
                        'prices' => [['id' => 'nails', 'label' => 'From €39']],
                        'text' => 'Vegan, HEMA-free gel.',
                        'features' => "- Classic manicure `45 min` **39**\n- Gel manicure `60 min` **52**\n- Gel removal `20 min` **15**\n- Spa pedicure `60 min` **55**\n- Gel pedicure `75 min` **65**",
                    ],
                    [
                        'name' => 'Lashes & brows',
                        'prices' => [['id' => 'lashes', 'label' => 'From €35']],
                        'text' => 'Patch test 48 h before.',
                        'features' => "- Brow shape & tint `30 min` **35**\n- Brow lamination `45 min` **65**\n- Lash lift & tint `60 min` **75**\n- Lash extensions `120 min` **149**\n- Refill `60 min` **69**",
                    ],
                    [
                        'name' => 'Waxing',
                        'prices' => [['id' => 'waxing', 'label' => 'From €12']],
                        'text' => 'Gentle warm wax.',
                        'features' => "- Upper lip **12**\n- Brows **16**\n- Underarms **22**\n- Bikini **35**\n- Brazilian `bestseller` **55**\n- Full legs **59**",
                    ],
                    [
                        'name' => 'Massage',
                        'prices' => [['id' => 'massage', 'label' => 'From €45']],
                        'text' => 'With warm organic oils.',
                        'features' => "- Back & neck `30 min` **45**\n- Gua sha face ritual `30 min` **49**\n- Relax massage `60 min` **85**\n- Hot stone `75 min` **109**",
                    ],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Every first facial starts with a free skin analysis, and a patch test is required 48 hours before your first lash lift, lash extensions or tint.\n\n**Durations** include a few minutes to arrive and to talk about your aftercare. If you want to combine treatments, book the facial first.",
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Good to know',
                'items' => [
                    ['title' => 'Which treatment should I start with?', 'text' => 'If you are unsure, book a signature glow facial. The skin analysis shows what your skin needs next.'],
                    ['title' => 'Will I know the price before you start?', 'text' => 'Always. If we recommend something extra, we tell you the price first and you decide.'],
                    ['title' => 'Do you use vegan products?', 'text' => 'Yes. All our skin care, polishes and lash products are vegan and not tested on animals.'],
                ],
            ]],
        ], $home );

        return $this->addFacials( $treatments )
            ->addNails( $treatments )
            ->addLashes( $treatments );
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
            'title' => 'Visit Us: Opening Hours and Directions | Lumi Beauty Studio Hamburg',
            'path' => 'visit',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'A quiet hour *in Eppendorf*',
                'subtitle' => 'Visit us',
                'text' => 'Our studio is on the first floor of an old town house on Eppendorfer Landstraße, a few minutes from the Isebek canal. Three treatment rooms, a nail bar and a tea lounge.',
                'buttons' => [
                    ['label' => 'Get directions', 'url' => 'https://www.openstreetmap.org/?mlat=53.5868&mlon=9.9849#map=17/53.5868/9.9849'],
                    ['label' => 'Call us', 'url' => 'tel:+494046892210'],
                ],
                'files' => [['id' => $this->capsule( 'candles' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Opening hours',
                'header' => 'row+col',
                'table' => [
                    ['', 'Studio', 'Last appointment'],
                    ['Monday', '12:00 – 20:00', '18:30'],
                    ['Tuesday – Friday', '09:00 – 20:00', '18:30'],
                    ['Saturday', '09:00 – 16:00', '14:30'],
                    ['Sunday', 'closed', '–'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Inside the studio',
                'columns' => '3',
                'cards' => [
                    ['title' => 'Treatment rooms', 'text' => 'Three quiet rooms with heated beds, soft light and your choice of music.', 'file' => ['id' => $this->img( 'treatment-room' ), 'type' => 'file']],
                    ['title' => 'Nail bar', 'text' => 'Four seats at the window, with good light for precise work.', 'file' => ['id' => $this->img( 'manicure' ), 'type' => 'file']],
                    ['title' => 'Tea lounge', 'text' => 'Herbal tea, warm towels and a few minutes for yourself after your treatment.', 'file' => ['id' => $this->img( 'spa-towels' ), 'type' => 'file']],
                ],
            ]],
            $this->map( 'Find us' ),
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Before you come',
                'items' => [
                    ['title' => 'Where can I park?', 'text' => 'There are public parking spaces in the side streets and a car park at Eppendorfer Marktplatz, four minutes on foot.'],
                    ['title' => 'How do I get there by public transport?', 'text' => 'U1 to Klosterstern or U3 to Eppendorfer Baum, both about seven minutes on foot. Bus 20 and 25 stop right outside.'],
                    ['title' => 'Is the studio accessible?', 'text' => 'Yes. The building has a lift and the treatment rooms have no steps. Please tell us when you book if you need help.'],
                    ['title' => 'Can I bring my child?', 'text' => 'For your own treatment, please come alone so you can relax. Teens are welcome for manicures and facials.'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates (once) a 2:3 portrait image for capsule-shaped hero frames and returns its file ID.
     *
     * @param string $key PHOTOS key
     * @return string File ID
     */
    protected function capsule( string $key ) : string
    {
        return $this->cropped( $key, 800, 1200, true, [400, 800] );
    }


    /**
     * Creates the shared Lumi footer and returns its ID.
     *
     * @return string Element ID
     */
    protected function element() : string
    {
        return $this->element ??= $this->saveElement( 'cards', 'Lumi footer', ['columns' => '4', 'cards' => [
            ['title' => 'Treatments', 'text' => "- [Facials](/facials)\n- [Nails](/nails)\n- [Lashes & brows](/lashes-brows)\n- [All prices](/treatments)"],
            ['title' => 'Clients', 'text' => "- [New clients](/new-clients)\n- [Glow Club](/membership)\n- [Gift cards](/gift-cards)\n- [Visit us](/visit)"],
            ['title' => 'Lumi', 'text' => "- [Our team](/team)\n- [Careers](/careers)\n- [Journal](/journal)\n- [Imprint](/imprint)\n- [Privacy](/privacy)"],
            ['title' => 'Newsletter', 'text' => "Skin tips, new treatments and last-minute appointments, once a month.\n\n[Sign up by email](mailto:hello@lumibeauty.example?subject=Newsletter)"],
        ]] );
    }


    /**
     * Returns the studio promise badges element.
     *
     * @return array<string, mixed> Cards content element
     */
    protected function ethos() : array
    {
        return ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
            'title' => 'The Lumi promise',
            'layout' => 'badges',
            'cards' => [
                ['title' => 'Skin analysis first', 'text' => 'Free before every first facial', 'file' => $this->icon( 'eye' )],
                ['title' => 'Vegan products', 'text' => 'Cruelty-free skin care and polishes', 'file' => $this->icon( 'leaf' )],
                ['title' => 'Clear prices', 'text' => 'You know the price before we start', 'file' => $this->icon( 'tag' )],
                ['title' => 'Strict hygiene', 'text' => 'Single-use files, sterilised tools', 'file' => $this->icon( 'shield' )],
                ['title' => 'Time for you', 'text' => 'Never two clients in one hour', 'file' => $this->icon( 'heart' )],
            ],
        ]];
    }


    /**
     * Returns the ID of the primary studio image.
     *
     * @return string File ID
     */
    protected function file() : string
    {
        return $this->img( 'treatment-room' );
    }


    /**
     * Returns the Lumi home page.
     *
     * @return Page Home page
     */
    protected function home() : Page
    {
        $elementId = $this->element();
        $fileId = $this->file();

        $config = [
            'website' => Validation::entry( 'website', ['title' => 'Lumi Beauty'], 'config' ),
        ] + $this->logos( $this->logoFile() ) + [
            'glow::salon' => [
                'type' => 'glow::salon',
                'files' => [],
                'data' => [
                    'name' => 'Lumi Beauty Studio',
                    'business-type' => 'BeautySalon',
                    'street-address' => 'Eppendorfer Landstraße 61',
                    'postal-code' => '20249',
                    'locality' => 'Hamburg',
                    'country' => 'DE',
                    'telephone' => '+49 40 4689 2210',
                    'email' => 'hello@lumibeauty.example',
                    'announcement' => 'Your first facial includes a free skin analysis',
                    'booking' => '/book',
                    'prices' => '/treatments',
                    'price-range' => '€€',
                    'notice' => 'Please cancel or move your appointment at least 24 hours before.',
                    'action-bar' => true,
                    'hours' => [
                        ['id' => 'mon', 'day' => 'Monday', 'opens' => '12:00', 'closes' => '20:00'],
                        ['id' => 'tue', 'day' => 'Tuesday', 'opens' => '09:00', 'closes' => '20:00'],
                        ['id' => 'wed', 'day' => 'Wednesday', 'opens' => '09:00', 'closes' => '20:00'],
                        ['id' => 'thu', 'day' => 'Thursday', 'opens' => '09:00', 'closes' => '20:00'],
                        ['id' => 'fri', 'day' => 'Friday', 'opens' => '09:00', 'closes' => '20:00'],
                        ['id' => 'sat', 'day' => 'Saturday', 'opens' => '09:00', 'closes' => '16:00'],
                    ],
                ],
            ],
        ];

        $content = [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Skin that *glows*, hands that care',
                'subtitle' => 'Beauty studio in Hamburg-Eppendorf',
                'text' => 'Facials, nails, lashes and massages in a calm studio full of daylight. We look at your skin first and only then choose your treatment.',
                'buttons' => [
                    ['label' => 'Book now', 'url' => '/book'],
                    ['label' => 'Treatments & prices', 'url' => '/treatments'],
                ],
                'files' => [['id' => $this->capsule( 'facial-massage' ), 'type' => 'file']],
                'main' => true,
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'layout' => 'figures',
                'cards' => [
                    ['title' => '4.9', 'text' => 'Average rating from 860 reviews'],
                    ['title' => '2016', 'text' => 'Caring for skin in Eppendorf'],
                    ['title' => '9', 'text' => 'Therapists, nail and lash artists'],
                    ['title' => '100 %', 'text' => 'Vegan and cruelty-free products'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Find your treatment',
                'columns' => '3',
                'cards' => [
                    ['title' => 'Facials', 'url' => '/facials', 'text' => "**From €59** `30–75 min`\nCleanse, calm and brighten, always after a skin analysis.", 'file' => ['id' => $this->img( 'facial-mask' ), 'type' => 'file']],
                    ['title' => 'Skin treatments', 'url' => '/treatments', 'text' => "**From €75** `45–75 min`\nPeels, dermaplaning and microneedling for lasting results.", 'file' => ['id' => $this->img( 'glowing-skin' ), 'type' => 'file']],
                    ['title' => 'Nails', 'url' => '/nails', 'text' => "**From €39** `45–75 min`\nManicures and pedicures with vegan, HEMA-free gel.", 'file' => ['id' => $this->img( 'manicure' ), 'type' => 'file']],
                    ['title' => 'Lashes & brows', 'url' => '/lashes-brows', 'text' => "**From €35** `30–120 min`\nLash lifts, extensions and brow lamination.", 'file' => ['id' => $this->img( 'brows' ), 'type' => 'file']],
                    ['title' => 'Waxing', 'url' => '/treatments', 'text' => "**From €12** `10–45 min`\nGentle warm wax for smooth skin for up to four weeks.", 'file' => ['id' => $this->img( 'skin-towel' ), 'type' => 'file']],
                    ['title' => 'Massage', 'url' => '/treatments', 'text' => "**From €45** `30–75 min`\nRelax, hot stone and gua sha with warm organic oils.", 'file' => ['id' => $this->img( 'hot-stones' ), 'type' => 'file']],
                ],
            ]],
            $this->signatures(),
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'facial-cream' ), 'type' => 'file'],
                'position' => 'grid-end',
                'ratio' => '1-1',
                'text' => "## We look *before* we treat\n\nEvery first facial starts with ten minutes under the magnifying lamp. We check moisture, oil, pores and sensitivity, ask about your routine and only then choose the cleanse, the peel and the active ingredients.\n\n- **Skin analysis:** free with your first facial\n- **Treatment plan:** for the next three months\n- **Home care:** written tips, no sales pressure\n\n[Which facial is right for you?](/which-facial)",
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'gua-sha' ), 'type' => 'file'],
                'position' => 'grid-start',
                'ratio' => '1-1',
                'text' => "## Join the *Glow Club*\n\nOne treatment every month, 15 to 25 percent off everything else and credits that roll over when life gets busy. From €79 a month, cancel after three months.\n\n[Discover the Glow Club membership](/membership)",
            ]],
            $this->team(),
            $this->ethos(),
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'Kind words from our clients',
                'items' => $this->reviews(),
            ]],
            ['id' => Utils::uid(), 'type' => 'blog', 'group' => 'main', 'data' => [
                'title' => 'From the journal',
                'layout' => 'cards',
                'parent-page' => ['value' => $this->journalId, 'label' => 'Journal'],
                'order' => '_lft',
                'limit' => 3,
            ]],
            $this->map( 'Visit the studio' ),
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Ready for *your glow*?',
                'text' => 'Book online in two minutes. New to Lumi? Your first facial starts with a free skin analysis.',
                'buttons' => [
                    ['label' => 'Book now', 'url' => '/book'],
                    ['label' => 'Gift cards', 'url' => '/gift-cards'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        $meta = [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => 'Lumi Beauty Studio in Hamburg-Eppendorf: facials, microneedling, gel nails, lash lifts, waxing and massages with vegan products. Free skin analysis, book online.',
                'keywords' => 'beauty salon Hamburg, Kosmetikstudio Eppendorf, facial Hamburg, lash lift Hamburg, gel nails Hamburg, microneedling Hamburg',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => 'Lumi Beauty Studio | Facials, Nails and Lashes in Hamburg',
                'description' => 'Skin that glows, hands that care.',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        return $this->saveRoot( 'Lumi Beauty Studio | Facials, Nails and Lashes in Hamburg-Eppendorf', $config, $meta, $content, $elementId, $fileId );
    }


    /**
     * Creates a berry line icon once and returns its file reference.
     *
     * @param string $name Icon name: eye, heart, leaf, shield or tag
     * @return array<string, string> File reference
     */
    protected function icon( string $name ) : array
    {
        $paths = [
            'eye' => '<path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6S2 12 2 12z"/><circle cx="12" cy="12" r="2.5"/>',
            'heart' => '<path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/>',
            'leaf' => '<path d="M5 19c0-9 6-14 15-14 0 9-5 15-14 15"/><path d="M5 19c3-4 6-7 10-9"/>',
            'shield' => '<path d="M12 3l7 3v5c0 4.5-3 8.2-7 10-4-1.8-7-5.5-7-10V6z"/><path d="M9 12l2 2 4-4"/>',
            'tag' => '<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="8.5" r="1.2"/>',
        ];

        $this->icons[$name] ??= $this->svgFile(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#A2456B" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">' . $paths[$name] . '</svg>',
            'icon-' . $name . '.svg',
            ucfirst( $name ) . ' icon',
            'Berry line icon: ' . $name,
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
            'title' => 'Journal: Skin Care Guides, Lash and Nail Aftercare | Lumi',
            'path' => 'journal',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Notes for *the days in between*',
                'subtitle' => 'Journal',
                'text' => 'Skin care routines, aftercare tips and seasonal advice from our therapists, written for the weeks between your appointments.',
                'files' => [['id' => $this->capsule( 'skincare-flatlay' ), 'type' => 'file']],
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
     * Creates the Lumi SVG logo and returns its file ID.
     *
     * @return string File ID
     */
    protected function logoFile() : string
    {
        if( !isset( $this->logoFile ) )
        {
            $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 64" role="img" aria-labelledby="title desc">
  <title id="title">Lumi Beauty Studio logo</title>
  <desc id="desc">Four-pointed sparkle with a small second sparkle beside the italic Lumi wordmark</desc>
  <path d="M24 6c1.6 9.6 5.4 13.4 15 15-9.6 1.6-13.4 5.4-15 15-1.6-9.6-5.4-13.4-15-15 9.6-1.6 13.4-5.4 15-15z" fill="#A2456B"/>
  <path d="M42 36c.8 4.6 2.4 6.2 7 7-4.6.8-6.2 2.4-7 7-.8-4.6-2.4-6.2-7-7 4.6-.8 6.2-2.4 7-7z" fill="#E4A9B4"/>
  <text x="64" y="40" fill="#2E2230" font-family="'Bodoni Moda', 'Bodoni 72', Didot, 'Playfair Display', Georgia, serif" font-size="38" font-style="italic">Lumi</text>
  <text x="66" y="57" fill="#A2456B" font-family="system-ui, -apple-system, 'Segoe UI', sans-serif" font-size="10.5" letter-spacing="3.6">BEAUTY STUDIO</text>
</svg>
SVG;

            $this->logoFile = $this->svgFile(
                $svg,
                'lumi-beauty-logo.svg',
                'Lumi Beauty Studio logo',
                'Four-pointed sparkle with a small second sparkle beside the italic Lumi wordmark',
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
            'text' => "**Lumi Beauty Studio**\nEppendorfer Landstraße 61 · 20249 Hamburg-Eppendorf\n\n**Opening hours**\nMonday 12:00–20:00\nTuesday to Friday 09:00–20:00\nSaturday 09:00–16:00\n\n**Call**\n+49 40 4689 2210\n\n**Getting here**\nU1 to Klosterstern or U3 to Eppendorfer Baum, about seven minutes on foot. Bus 20 and 25 stop right outside.",
            'location' => [
                'latitude' => 53.5868,
                'longitude' => 9.9849,
                'zoom' => 16,
            ],
            'button' => 'Open in OpenStreetMap',
        ]];
    }


    /**
     * Creates a Glow demo page below the given parent and returns it.
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

        return $this->savePage( $data, $content, $parent, $elementId, $fileId, $footer, 'Lumi Beauty Studio, beauty salon Hamburg, Kosmetikstudio Eppendorf, facials, gel nails, lash lift, waxing, massage' );
    }


    /**
     * Builds the Glow demo page tree.
     */
    protected function pages() : void
    {
        $this->journalId = (string) Str::uuid7();
        $home = $this->home();

        $this->addTreatments( $home )
            ->addMembership( $home )
            ->addTeam( $home )
            ->addNewClients( $home )
            ->addGifts( $home )
            ->addJournal( $home )
            ->addVisit( $home )
            ->addBook( $home )
            ->addImprint( $home )
            ->addPrivacy( $home );
    }


    /**
     * Creates a post below the journal page.
     *
     * @param Page $parent Journal page
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
                'text' => 'Book a free skin analysis and we find the right treatments and routine for your skin together.',
                'buttons' => [
                    ['label' => 'Book now', 'url' => '/book'],
                    ['label' => 'Treatments & prices', 'url' => '/treatments'],
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
            ['name' => 'Julia', 'role' => 'Signature facial with Mila', 'text' => 'Mila explained every step and never tried to sell me anything. My skin has not looked this calm in years.'],
            ['name' => 'Aylin', 'role' => 'Lash lift with Hannah', 'text' => 'Eight weeks later my lashes are still curled. I have stopped using an eyelash curler and barely need mascara.'],
            ['name' => 'Sarah', 'role' => 'Gel manicure with Zoe', 'text' => 'Three weeks without a single chip, and the nicest hand massage. Zoe\'s nail bar is my monthly hour off.'],
        ];
    }


    /**
     * Returns the signature treatments element.
     *
     * @return array<string, mixed> Pricing content element
     */
    protected function signatures() : array
    {
        return ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
            'title' => 'Signature treatments',
            'text' => 'The treatments our clients come back for. Each one includes a short consultation and aftercare tips for home.',
            'items' => [
                [
                    'name' => 'Lash lift & tint',
                    'file' => ['id' => $this->img( 'makeup-eye' ), 'type' => 'file'],
                    'prices' => [['id' => 'lash', 'amount' => 75, 'label' => '€75']],
                    'text' => 'Curled and tinted for six weeks.',
                    'features' => "- Patch test 48 hours before\n- Keratin lift and tint\n- Lasts six to eight weeks",
                    'url' => '/lashes-brows',
                    'button' => 'Discover lashes & brows',
                ],
                [
                    'name' => 'Signature glow facial',
                    'file' => ['id' => $this->img( 'facial' ), 'type' => 'file'],
                    'prices' => [['id' => 'facial', 'amount' => 119, 'label' => '€119']],
                    'text' => 'Sixty minutes of calm and glow.',
                    'features' => "- Skin analysis and double cleanse\n- Enzyme peel and extractions\n- Lymphatic face and neck massage",
                    'url' => '/facials',
                    'button' => 'Discover facials',
                    'highlight' => true,
                    'badge' => 'Most booked',
                ],
                [
                    'name' => 'Gel manicure',
                    'file' => ['id' => $this->img( 'nails-closeup' ), 'type' => 'file'],
                    'prices' => [['id' => 'nails', 'amount' => 52, 'label' => '€52']],
                    'text' => 'Glossy for up to three weeks.',
                    'features' => "- Shape and cuticle care\n- Vegan, HEMA-free gel\n- Hand massage with warm oil",
                    'url' => '/nails',
                    'button' => 'Discover nails',
                ],
            ],
        ]];
    }


    /**
     * Returns the therapist card element.
     *
     * @return array<string, mixed> Cards content element
     */
    protected function team() : array
    {
        $people = [
            ['Mila Brandt', 'team-1', "**Founder & facialist**\nFacials, peels and microneedling. Fifteen years of experience with sensitive skin."],
            ['Hannah Weiss', 'team-2', "**Lash & brow artist**\nLash lifts, soft volume extensions and brows that suit your face."],
            ['Zoe Asante', 'team-3', "**Nail artist**\nClean gel work, natural shapes and fine nail art."],
            ['Lukas Petersen', 'team-4', "**Massage therapist**\nRelax, hot stone and deep tissue massage for tense shoulders."],
        ];

        return ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
            'title' => 'Meet your therapists',
            'columns' => '4',
            'cards' => array_map( fn( $person ) => [
                'title' => $person[0],
                'text' => $person[2],
                'file' => ['id' => $this->cropped( $person[1], 800, 800 ), 'type' => 'file'],
            ], $people ),
        ]];
    }
}
