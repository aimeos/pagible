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
 * Grove theme demo for the fictional Bramble & Oak garden company in Guildford.
 */
class GroveDemo extends AbstractDemo
{
    /** @var array<string, string> Meta descriptions keyed by page path */
    protected const DESCRIPTIONS = [
        'about' => 'Meet the Bramble & Oak team in Guildford: garden designers, landscapers and gardeners caring for Surrey gardens since 2009.',
        'autumn-checklist' => 'Ten autumn jobs that make your garden look better next spring: lawn care, leaves, pruning, planting bulbs and protecting tender plants.',
        'care-plans' => 'Regular garden maintenance plans in Surrey from £45 a visit: lawn care, borders, hedges and seasonal tidy-ups by the same gardener every time.',
        'contact' => 'Contact Bramble & Oak in Guildford: office hours, the areas we cover across Surrey and a form for your garden enquiry.',
        'garden-design' => 'Garden design in Surrey: consultations, scaled plans, 3D visuals and planting schemes for gardens that suit the way you live.',
        'garden-maintenance' => 'Garden maintenance in Guildford and Surrey: weekly, fortnightly or monthly visits for borders, lawns, hedges and pruning.',
        'imprint' => 'Legal notice of Bramble & Oak Gardens Ltd, Guildford.',
        'journal' => 'Seasonal garden advice from the Bramble & Oak gardeners: lawns, borders, wildlife and low-maintenance planting.',
        'landscaping' => 'Landscaping in Surrey: patios, paths, decking, walls, raised beds, ponds and garden lighting built to last by BALI registered contractors.',
        'lawn-care' => 'How to get a green, healthy lawn without chemicals: mowing height, feeding, scarifying, aerating and watering through the seasons.',
        'lawns-hedges' => 'Lawn care, new turf, hedge cutting and tree work in Guildford and Surrey at fair, fixed prices.',
        'privacy' => 'Privacy policy of Bramble & Oak Gardens Ltd, Guildford.',
        'projects' => 'Garden design and landscaping projects by Bramble & Oak across Surrey: patios, kitchen gardens, ponds, courtyards and planting.',
        'quote' => 'Ask for a free, no-obligation garden quote from Bramble & Oak. We visit your garden and send a fixed price within a week.',
        'services' => 'Garden design, landscaping, garden maintenance, lawn care and hedge cutting across Guildford, Woking, Godalming and Surrey.',
        'wildlife-garden' => 'Six simple ways to make your garden friendlier for bees, birds and hedgehogs without letting it look untidy.',
    ];

    /**
     * Curated Unsplash photos used by the garden demo.
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3?: string}>
     */
    protected const PHOTOS = [
        'after' => ['photo-1779565145536-ccdf57517151', 'Tidy garden', 'A neat, well-kept garden with a green lawn, a paved path, clipped shrubs, lavender, ornamental grasses and an insect hotel'],
        'autumn' => ['photo-1605774057585-8ef87911e02c', 'Autumn colours', 'A red Japanese maple in the foreground with golden autumn trees and fallen leaves behind'],
        'before' => ['photo-1595797623546-5a85a990ca6c', 'Overgrown garden', 'An overgrown garden with tall grass, rampant shrubs and a weathered wooden shed half hidden behind the greenery'],
        'border' => ['photo-1728667088041-ee116cf57c6c', 'Flower border', 'A colourful flower border of pink begonias, purple salvia, silver foliage and red zinnias in front of a lawn'],
        'decking' => ['photo-1656646549607-8fda5837a4ca', 'Garden decking', 'A multi-level timber deck with steps, outdoor furniture and built-in planters behind a green clapboard house'],
        'fence' => ['photo-1521401707913-31dbbe09ca2c', 'Fence and trellis', 'A white wooden fence with lattice trellis panels and a climbing plant with white blossoms, above a strip of lawn'],
        'gravel' => ['photo-1762876244984-f9d493cf4aa1', 'Gravel garden', 'A raked white gravel garden with a large rock on a mossy island in front of a low earthen wall and trees'],
        'greenhouse' => ['photo-1508857650881-64475119d798', 'Greenhouse', 'Inside a long glass greenhouse with rows of young green plants on either side of a central path and a hose'],
        'hedge-trimming' => ['photo-1543309959-4d45288d1629', 'Hedge trimming', 'Close-up of a hedge trimmer blade cutting across the top of a rounded conifer shrub, with clippings flying'],
        'hero-garden' => ['photo-1668120089662-42642838cfef', 'Landscaped garden', 'A landscaped garden with sweeping lawns, rounded shrubs and a curving gravel path leading towards a planted hillside'],
        'lavender' => ['photo-1595562161314-4ed3f3503826', 'Lavender', 'Close-up of flowering purple lavender spikes with a softly blurred lavender background'],
        'lawn' => ['photo-1625766924175-4c010046038d', 'Striped lawn', 'A large, freshly mown lawn with neat stripes, framed by mature trees and shrubs'],
        'lawn-mowing' => ['photo-1731082686849-d2e0a4d2c70c', 'Lawn mowing', 'A smiling man in a cap pushing a red lawn mower across long grass beneath old fruit trees on a sunny day'],
        'leaves' => ['photo-1634081727680-fa43e3237d5a', 'Leaf clearing', 'A red rake lying on a pile of yellow and brown autumn leaves on paving'],
        'lighting' => ['photo-1786520632611-40a0f4856f1b', 'Garden lighting', 'Glowing spherical garden lights on a dark lawn at night beside a tree wrapped in string lights'],
        'path' => ['photo-1663185776834-0c86f6ced17b', 'Stone path', 'A winding gravel and flagstone path through a rock garden planted with shrubs, perennials and conifers'],
        'patio' => ['photo-1719324923613-ff0884b031ed', 'Paved patio', 'A pale stone-paved patio with grey rattan furniture and a parasol, bordered by lawn, shrubs and a grey painted fence'],
        'pergola' => ['photo-1696846912973-3233cc80bf86', 'Pergola', 'A modern dark metal louvred pergola over rattan lounge furniture in a contemporary garden with lawn, grasses and pale paving'],
        'planting' => ['photo-1611843467160-25afb8df1074', 'Planting', 'Soil-covered hands pressing small seedlings into dark earth in a wooden raised bed'],
        'pond' => ['photo-1556010117-29c6a0a69e0f', 'Garden pond', 'A wildlife pond with water lilies and reeds, surrounded by roses and planting and edged with timber decking and railings'],
        'pruning' => ['photo-1680124744736-859f16257ef0', 'Pruning', 'A hand holding green-handled secateurs to cut a leafy shrub stem'],
        'raised-beds' => ['photo-1788538812851-9aacd4189fa8', 'Raised beds', 'Wooden raised vegetable beds full of squash and greens on a lawn in front of an old brick wall, with two people tending them'],
        'roses' => ['photo-1530906622963-8a60586a49c7', 'Roses', 'A shrub covered in deep red roses in full bloom against dark green foliage'],
        'seedlings' => ['photo-1466692476868-aef1dfb1e735', 'Seedling trays', 'Tiny green seedlings sprouting from a dark module seed tray'],
        'small-garden' => ['photo-1615478494918-1a49c8ecd568', 'Courtyard garden', 'A small urban courtyard garden with a flagstone path, low box hedging, potted plants, a blue shed and a painted bench'],
        'soil' => ['photo-1649577193391-f13d769d011d', 'Soil and compost', 'Two cupped hands holding dark soil and crumbly compost above a bed of young plants'],
        'spring-bulbs' => ['photo-1721436116304-8ba5e9f9f2f8', 'Spring tulips', 'Rows of pink, yellow and red tulips blooming above blue forget-me-nots'],
        'tools' => ['photo-1537877853655-34bdcda5e833', 'Garden tools', 'Spades, shovels and a digging fork laid out side by side on long grass'],
        'tree-surgery' => ['photo-1709560723503-538158e48db8', 'Tree surgery', 'An arborist in an orange hi-vis jacket and helmet, roped in among freshly pollarded branches in front of a brick house'],
        'watering' => ['photo-1747339385292-d58fdff13938', 'Watering', 'A metal watering can sprinkling water over young strawberry plants in a bed'],
        'wheelbarrow' => ['photo-1715274036728-4385d95a5e9f', 'Wheelbarrow', 'A green metal wheelbarrow standing on a lawn in front of a dense hedge'],
        'wildflowers' => ['photo-1592290435338-682c400cb6f8', 'Wildflower meadow', 'A wildflower meadow full of blue cornflowers, red poppies and yellow flowers'],
        'winter' => ['photo-1674471653794-e00e8382b296', 'Frosty garden', 'Close-up of a garden shrub whose leaves are edged with white hoar frost'],
        'team-1' => ['photo-1562037655-eed9f891eb4f', 'Tom Ashworth', 'A cheerful middle-aged man with a beard and braces, smiling broadly outdoors against greenery', 'faces'],
        'team-2' => ['photo-1781366636398-51bdc1f75970', 'Hannah Price', 'A young woman in a straw hat and denim dungarees standing among plants in a greenhouse', 'faces'],
        'team-3' => ['photo-1655564224525-c852fc679ce6', 'Geoff Marlow', 'A smiling older man with white hair and a beard in a blue cardigan, in front of a flowering bush', 'faces'],
        'team-4' => ['photo-1728706613021-e447801e1ea6', 'Ellie Brook', 'A young woman in a straw hat and apron tending orange marigolds in a garden', 'faces'],
        'review-1' => ['photo-1742971098464-02429d5fb87a', 'Claire', 'A dark-haired woman in a light blouse smiling softly in front of an ivy-covered wall', 'faces'],
        'review-2' => ['photo-1547544998-6fe24777d37e', 'James', 'A smiling bearded man in a dark shirt leaning into the frame in front of a green hedge', 'faces'],
        'review-3' => ['photo-1613394242132-1268854bde44', 'Peter', 'A laughing older man with grey hair wearing a chunky brown knitted scarf', 'faces'],
    ];

    private string $element;
    private string $journalId;
    /** @var array<string, string> */
    private array $icons = [];
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
            'name' => 'About us',
            'title' => 'About Bramble & Oak: Garden Designers and Gardeners in Surrey',
            'path' => 'about',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Gardeners who *love* what they do',
                'subtitle' => 'About us',
                'text' => 'Bramble & Oak started with one van, a mower and a promise to turn up when we say we will. Today fourteen designers, landscapers and gardeners look after more than 300 gardens across Surrey.',
                'buttons' => [
                    ['label' => 'Get a free quote', 'url' => '/quote'],
                    ['label' => 'See our projects', 'url' => '/projects'],
                ],
                'files' => [['id' => $this->frame( 'greenhouse' ), 'type' => 'file']],
            ]],
            $this->team(),
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'Our story',
                'layout' => 'vertical',
                'items' => [
                    ['label' => '2009', 'title' => 'One van in Shalford', 'text' => 'Tom Ashworth starts mowing lawns and cutting hedges for his neighbours.'],
                    ['label' => '2013', 'title' => 'First landscaping team', 'text' => 'Patios, paths and fencing join the maintenance rounds, Geoff brings forty years of plant knowledge.'],
                    ['label' => '2017', 'title' => 'Garden design studio', 'text' => 'Hannah Price joins as our garden designer and wins a silver-gilt medal two years later.'],
                    ['label' => '2021', 'title' => 'Peat-free and electric', 'text' => 'We switch to peat-free compost and battery tools for quieter, cleaner visits.'],
                    ['label' => '2025', 'title' => '300 gardens', 'text' => 'Our care plans now cover Guildford, Woking, Godalming and the surrounding villages.'],
                ],
            ]],
            $this->promises(),
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'soil' ), 'type' => 'file'],
                'position' => 'grid-end',
                'ratio' => '1-1',
                'text' => "## Gardening *with nature*\n\nGood gardens start with healthy soil. We use peat-free compost, mulch instead of weedkiller and plants that suit your soil and light, so your garden needs less water and less work.\n\n- **Green waste recycled:** composted locally, never sent to landfill\n- **Battery tools:** quiet visits without fumes\n- **Wildlife-friendly:** hedgehog gaps, bee plants and no slug pellets",
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Want to *join the team*?',
                'text' => 'We are always looking for gardeners and landscapers who take pride in their work. Paid RHS training included.',
                'buttons' => [
                    ['label' => 'Send us your CV', 'url' => 'mailto:jobs@brambleandoak.example'],
                    ['label' => 'Contact us', 'url' => '/contact'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the care plans page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addCarePlans( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Care plans',
            'title' => 'Garden Maintenance Plans from £45 a Visit | Bramble & Oak Surrey',
            'path' => 'care-plans',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'A beautiful garden, *without the weekends*',
                'subtitle' => 'Care plans',
                'text' => 'Choose how often we visit and what we take care of. The same gardener comes every time, knows your plants and leaves your garden tidier than they found it.',
                'buttons' => [
                    ['label' => 'Book a free visit', 'url' => '/quote'],
                    ['label' => 'Questions about plans', 'url' => '#plan-questions'],
                ],
                'files' => [['id' => $this->frame( 'lawn-mowing' ), 'type' => 'file']],
            ]],
            $this->plans(),
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'What happens through the year',
                'header' => 'row+col',
                'table' => [
                    ['', 'Lawn', 'Borders', 'Hedges & shrubs'],
                    ['Spring', 'Scarify, feed, first cuts', 'Weed, mulch, divide perennials', 'Prune roses and late shrubs'],
                    ['Summer', 'Weekly or fortnightly cuts', 'Deadhead, stake, water', 'Trim hedges after nesting'],
                    ['Autumn', 'Aerate, overseed, leaves', 'Cut back, plant bulbs', 'Final hedge trim'],
                    ['Winter', 'Occasional tidy', 'Protect tender plants', 'Prune fruit trees'],
                ],
            ]],
            ['id' => 'plan-questions', 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Questions about care plans',
                'items' => [
                    ['title' => 'Is there a minimum contract?', 'text' => 'No. Plans run month to month and you can pause or cancel with four weeks notice, for example while you are away.'],
                    ['title' => 'Do I need to be at home?', 'text' => 'No. Most customers leave the side gate open. We send a short message with photos after every visit.'],
                    ['title' => 'What happens with the green waste?', 'text' => 'We take it with us and compost it locally. It is included in every plan.'],
                    ['title' => 'What if it rains?', 'text' => 'Light rain doesn\'t stop us. In heavy rain we move your visit to the next dry day and tell you by message.'],
                ],
            ]],
            $this->cta(),
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
            'title' => 'Contact Bramble & Oak: Gardeners in Guildford and Surrey',
            'path' => 'contact',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Let\'s talk about *your garden*',
                'subtitle' => 'Contact',
                'text' => 'Call us, send a message or ask for a free garden visit. We answer every enquiry within one working day.',
                'buttons' => [
                    ['label' => 'Call 01632 960 418', 'url' => 'tel:+441632960418'],
                    ['label' => 'Get a free quote', 'url' => '/quote'],
                ],
                'files' => [['id' => $this->frame( 'wheelbarrow' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Areas we cover',
                'columns' => '3',
                'cards' => [
                    ['title' => 'Guildford & villages', 'text' => "Shalford, Bramley, Merrow, Burpham, Compton and Shere\n\n`No travel charge`"],
                    ['title' => 'Godalming & Waverley', 'text' => "Farncombe, Milford, Witley, Cranleigh and Farnham\n\n`No travel charge`"],
                    ['title' => 'Woking & beyond', 'text' => "Woking, Send, Ripley, West Byfleet and Pirbright\n\n`£10 per visit`"],
                ],
            ]],
            $this->map( 'Visit our yard' ),
            ['id' => Utils::uid(), 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Send us a message',
                'description' => 'For questions, care plans or anything else. Photos of your garden help us to give you a better first answer.',
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
     * Creates the garden design page below the services page.
     *
     * @param Page $parent Services page
     * @return static Same object for fluent calls
     */
    protected function addDesign( Page $parent ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Garden design',
            'title' => 'Garden Design in Guildford and Surrey | Bramble & Oak',
            'path' => 'garden-design',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'A garden designed *around you*',
                'subtitle' => 'Garden design',
                'text' => 'Whether you want a place for long summer dinners, a garden the children can run wild in or borders that look good in every season, we design it to suit your plot, your soil and your budget.',
                'buttons' => [
                    ['label' => 'Book a design consultation', 'url' => '/quote'],
                    ['label' => 'See our projects', 'url' => '/projects'],
                ],
                'files' => [['id' => $this->frame( 'border' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'From idea to finished garden',
                'layout' => 'horizontal',
                'items' => [
                    ['label' => 'Week 1', 'title' => 'Consultation', 'text' => 'Two hours in your garden: what you love, what you need and what you want to change.'],
                    ['label' => 'Week 3', 'title' => 'Survey & concept', 'text' => 'Measured survey, soil test and two layout ideas to choose from.'],
                    ['label' => 'Week 6', 'title' => 'Plans & visuals', 'text' => 'Scaled plan, 3D visuals, planting scheme and a fixed build price.'],
                    ['label' => 'Season', 'title' => 'Build & plant', 'text' => 'Our own team builds and plants, the designer checks every step.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Design packages',
                'columns' => '3',
                'cards' => [
                    ['title' => 'Consultation', 'text' => "**£180** `2 hours`\nIdeas, advice and a written summary with plant suggestions.", 'file' => ['id' => $this->img( 'lavender' ), 'type' => 'file']],
                    ['title' => 'Planting plan', 'text' => "**From £450** `2 weeks`\nA border or bed designed plant by plant, with a care calendar.", 'file' => ['id' => $this->img( 'roses' ), 'type' => 'file']],
                    ['title' => 'Full garden design', 'text' => "**From £1,450** `6 weeks`\nSurvey, layout, 3D visuals, planting and build drawings.", 'file' => ['id' => $this->img( 'pergola' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Garden design questions',
                'items' => [
                    ['title' => 'Do I have to use you to build the design?', 'text' => 'No, the plans are yours. Most customers do, because we credit the design fee against builds over £15,000.'],
                    ['title' => 'What does a new garden cost?', 'text' => 'Most of our gardens cost between £15,000 and £60,000 to build. We give you a fixed price per area, so you can build in stages.'],
                    ['title' => 'Can you work with my existing plants?', 'text' => 'Gladly. Established trees and shrubs are valuable, we lift and replant what we can.'],
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
            'title' => 'Imprint | Bramble & Oak',
            'path' => 'imprint',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "# Imprint\n\n**Bramble & Oak Gardens Ltd**\nUnit 4, Mill Lane Yard\nShalford, Guildford GU4 8BX\nUnited Kingdom\n\nTelephone: +44 1632 960 418\nEmail: hello@brambleandoak.example\n\nDirector: Tom Ashworth\nRegistered in England and Wales, company number 00000000\nVAT number: GB 000 0000 00\n\nBALI registered contractor and member of the Association of Professional Landscapers.\n\nThis is a demo website for the Grove theme. Bramble & Oak is a fictional company and the people named are not real.",
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
        $journal = $this->page( [
            'id' => $this->journalId,
            'lang' => 'en',
            'name' => 'Journal',
            'title' => 'Garden Journal: Seasonal Advice from Our Gardeners | Bramble & Oak',
            'path' => 'journal',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Notes from *the potting shed*',
                'subtitle' => 'Journal',
                'text' => 'What our gardeners do in their own gardens each season, and the advice they give customers over a cup of tea.',
                'files' => [['id' => $this->frame( 'seedlings' ), 'type' => 'file']],
            ]],
            ['id' => 'journal-list', 'type' => 'blog', 'group' => 'main', 'data' => [
                'layout' => 'cards',
                'parent-page' => ['value' => $this->journalId, 'label' => 'Journal'],
                'order' => '_lft',
                'limit' => 12,
            ]],
        ], $home );

        $this->post( $journal, [
            'name' => 'Autumn checklist',
            'title' => 'Your Autumn Garden Checklist: Ten Jobs for a Better Spring',
            'path' => 'autumn-checklist',
        ], 'Your autumn garden checklist',
            "Autumn is the most underrated season in the garden. The soil is still warm, the rain is back and everything you plant now has months to root before it has to grow.\n\nThese are the jobs our gardeners tick off between October and the first frost, and why each of them pays off when spring comes round.",
            'autumn',
            [
                ['title' => '10', 'text' => 'Jobs that make next spring easier'],
                ['title' => '6 °C', 'text' => 'Soil temperature where roots stop growing'],
                ['title' => '15 cm', 'text' => 'Planting depth for tulip bulbs'],
            ],
            [
                ['label' => 'October', 'title' => 'Aerate and overseed', 'text' => 'Spike the lawn and sow seed into bare patches while the soil is warm.'],
                ['label' => 'October', 'title' => 'Plant bulbs', 'text' => 'Daffodils now, tulips in November when it is cooler.'],
                ['label' => 'November', 'title' => 'Collect leaves', 'text' => 'Rake them off the lawn and pile them up for leaf mould.'],
                ['label' => 'November', 'title' => 'Protect tender plants', 'text' => 'Move pots to a sheltered wall and wrap them in fleece.'],
            ],
            [
                ['title' => 'Should I cut back all my perennials?', 'text' => 'No. Leave seed heads of grasses, sedums and echinacea standing, they look beautiful with frost and feed the birds.'],
                ['title' => 'When is the last lawn cut?', 'text' => 'Usually in late October. Raise the mower blades a notch for the last two cuts.'],
                ['title' => 'Is autumn a good time to plant hedges?', 'text' => 'The best. Bare-root hedging from November to March is cheaper and establishes better than potted plants.'],
            ],
        );

        $this->post( $journal, [
            'name' => 'Lawn care',
            'title' => 'A Greener Lawn Without Chemicals: Our Year-Round Guide',
            'path' => 'lawn-care',
        ], 'A greener lawn without chemicals',
            "A great lawn is not about weedkiller and fertiliser bags. It is about mowing at the right height, feeding the soil and letting the grass breathe.\n\nThis is the routine we follow for the lawns in our care plans. You can do most of it yourself with a good mower and a rake.",
            'lawn',
            [
                ['title' => '4 cm', 'text' => 'Mowing height for a family lawn'],
                ['title' => '⅓', 'text' => 'The most you should cut at once'],
                ['title' => '2×', 'text' => 'Feeds a year are enough'],
            ],
            [
                ['label' => 'March', 'title' => 'First cut', 'text' => 'High blades and only a light trim of the tips.'],
                ['label' => 'April', 'title' => 'Scarify and feed', 'text' => 'Rake out moss and thatch, then use an organic spring feed.'],
                ['label' => 'Summer', 'title' => 'Mow high, water deep', 'text' => 'Longer grass shades its roots and stays green in dry spells.'],
                ['label' => 'September', 'title' => 'Aerate and top-dress', 'text' => 'Spike, brush in sandy loam and overseed thin areas.'],
            ],
            [
                ['title' => 'How do I get rid of moss?', 'text' => 'Moss is a sign of shade, compaction or poor drainage. Scarify, aerate and feed, and the grass will push it out over a season.'],
                ['title' => 'Should I water my lawn in summer?', 'text' => 'An established lawn turns brown in a drought but recovers after rain. If you water, do it deeply once a week instead of a little every day.'],
                ['title' => 'Are stripes good for the lawn?', 'text' => 'Stripes come from the roller bending the grass. They don\'t harm it and look lovely.'],
            ],
        );

        $this->post( $journal, [
            'name' => 'Wildlife garden',
            'title' => 'Six Easy Ways to Bring Wildlife into Your Garden',
            'path' => 'wildlife-garden',
        ], 'Six easy ways to bring wildlife into your garden',
            "A wildlife-friendly garden doesn't have to look wild. A few small changes bring bees, birds and hedgehogs into even a tidy town garden.\n\nThese are the changes we suggest most often to our customers, from a corner of long grass to a pond the size of a washing-up bowl.",
            'wildflowers',
            [
                ['title' => '13 cm', 'text' => 'Gap in the fence for hedgehogs'],
                ['title' => '1 m²', 'text' => 'Of meadow is already worth it'],
                ['title' => '0', 'text' => 'Slug pellets needed'],
            ],
            [
                ['label' => 'Water', 'title' => 'Dig a small pond', 'text' => 'With one sloping side so animals can get out again.'],
                ['label' => 'Food', 'title' => 'Plant for pollinators', 'text' => 'Lavender, salvia and open flowers from March to October.'],
                ['label' => 'Shelter', 'title' => 'Leave a wild corner', 'text' => 'Log piles, long grass and a bug hotel behind the shed.'],
                ['label' => 'Paths', 'title' => 'Open the fences', 'text' => 'A hand-sized gap lets hedgehogs travel between gardens.'],
            ],
            [
                ['title' => 'Will a pond attract mosquitoes?', 'text' => 'Not a wildlife pond. Frogs, newts and dragonfly larvae eat the larvae long before they hatch.'],
                ['title' => 'Can I have a meadow on clay soil?', 'text' => 'Yes, choose a mix for clay and remove the topsoil to reduce nutrients. Annual cornfield flowers are easiest for the first year.'],
                ['title' => 'Is it safe for children?', 'text' => 'We add a steel grid just below the water surface for families with small children.'],
            ],
        );

        return $this;
    }


    /**
     * Creates the landscaping page below the services page.
     *
     * @param Page $parent Services page
     * @return static Same object for fluent calls
     */
    protected function addLandscaping( Page $parent ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Landscaping',
            'title' => 'Landscaping, Patios, Decking and Paths in Surrey | Bramble & Oak',
            'path' => 'landscaping',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Built to last, *made to enjoy*',
                'subtitle' => 'Landscaping',
                'text' => 'Patios, paths, decking, walls and ponds, built by our own BALI registered team on proper foundations. Every project comes with a ten year guarantee on hard landscaping.',
                'buttons' => [
                    ['label' => 'Get a free quote', 'url' => '/quote'],
                    ['label' => 'See our projects', 'url' => '/projects'],
                ],
                'files' => [['id' => $this->frame( 'patio' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'What we build',
                'columns' => '3',
                'cards' => [
                    ['title' => 'Patios', 'text' => "**From £120 per m²** `sandstone or porcelain`\nLaid on a full mortar bed with proper falls for drainage.", 'file' => ['id' => $this->img( 'patio' ), 'type' => 'file']],
                    ['title' => 'Decking', 'text' => "**From £150 per m²** `hardwood or composite`\nMulti-level decks with steps, planters and hidden lighting.", 'file' => ['id' => $this->img( 'decking' ), 'type' => 'file']],
                    ['title' => 'Paths & gravel', 'text' => "**From £65 per m²** `stone, gravel, bark`\nPermeable paths that drain well and stay weed-free.", 'file' => ['id' => $this->img( 'path' ), 'type' => 'file']],
                    ['title' => 'Raised beds', 'text' => "**From £380** `oak sleepers`\nKitchen gardens at a comfortable height, filled with good soil.", 'file' => ['id' => $this->img( 'raised-beds' ), 'type' => 'file']],
                    ['title' => 'Ponds', 'text' => "**From £1,800** `wildlife or formal`\nLined, planted and edged so frogs, newts and children are safe.", 'file' => ['id' => $this->img( 'pond' ), 'type' => 'file']],
                    ['title' => 'Fences & pergolas', 'text' => "**From £95 per metre** `timber or metal`\nPrivacy screens, trellis and shade over your seating area.", 'file' => ['id' => $this->img( 'fence' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'before-after', 'group' => 'main', 'data' => [
                'title' => 'From overgrown to easy-care',
                'before' => ['id' => $this->cropped( 'before', 1500, 1000 ), 'type' => 'file'],
                'after' => ['id' => $this->cropped( 'after', 1500, 1000 ), 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Landscaping questions',
                'items' => [
                    ['title' => 'How long does a new patio take?', 'text' => 'A typical 30 m² patio takes five to eight working days, including the removal of the old surface and soil.'],
                    ['title' => 'Do I need planning permission?', 'text' => 'Usually not for patios, paths and fences under two metres. We check permitted development rules and drainage for you.'],
                    ['title' => 'Do you take payments in stages?', 'text' => 'Yes. A 20 percent deposit books your start date, the rest is paid in stages as the work progresses.'],
                ],
            ]],
        ], $parent );

        return $this;
    }


    /**
     * Creates the lawns and hedges page below the services page.
     *
     * @param Page $parent Services page
     * @return static Same object for fluent calls
     */
    protected function addLawns( Page $parent ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Lawns & hedges',
            'title' => 'Lawn Care, New Turf, Hedge Cutting and Tree Work in Surrey | Bramble & Oak',
            'path' => 'lawns-hedges',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Greener lawns, *sharper hedges*',
                'subtitle' => 'Lawns & hedges',
                'text' => 'One-off jobs or regular visits: we mow, feed, scarify and re-turf lawns, cut hedges of every height and care for your trees with qualified arborists.',
                'buttons' => [
                    ['label' => 'Get a free quote', 'url' => '/quote'],
                    ['label' => 'Lawn care guide', 'url' => '/lawn-care'],
                ],
                'files' => [['id' => $this->frame( 'hedge-trimming' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
                'title' => 'Prices for one-off jobs',
                'text' => 'For a typical semi-detached garden, including green waste removal and VAT.',
                'items' => [
                    [
                        'name' => 'Lawns',
                        'prices' => [['id' => 'lawns', 'label' => 'From £35']],
                        'text' => 'Healthy grass all year round.',
                        'features' => "- Mowing and edging `per visit` **£35**\n- Scarifying `up to 100 m²` **£90**\n- Aerating and top dressing `up to 100 m²` **£120**\n- New turf `per m², laid` **£18**",
                    ],
                    [
                        'name' => 'Hedges',
                        'prices' => [['id' => 'hedges', 'label' => 'From £60']],
                        'text' => 'Clean lines, clippings removed.',
                        'features' => "- Hedge up to 2 m `per 10 metres` **£60**\n- Hedge up to 4 m `per 10 metres` **£140**\n- Reshaping overgrown hedges `per day` **£390**\n- New hedge planting `per metre` **£45**",
                        'highlight' => true,
                        'badge' => 'Most booked',
                    ],
                    [
                        'name' => 'Trees',
                        'prices' => [['id' => 'trees', 'label' => 'From £180']],
                        'text' => 'By qualified arborists.',
                        'features' => "- Fruit tree pruning `per tree` **£180**\n- Crown lift or reduction `per tree` **£450**\n- Tree felling `small tree` **£390**\n- Stump grinding `per stump` **£150**",
                    ],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'tree-surgery' ), 'type' => 'file'],
                'position' => 'grid-end',
                'ratio' => '1-1',
                'text' => "## Tree work *done safely*\n\nOur arborists are NPTC qualified and insured for £5 million. We check for tree preservation orders and conservation areas, apply for consent where needed and leave your garden clean.\n\n- **Free tree survey** with every quote\n- **Wood chip** for your borders, if you want it\n- **Logs** cut to length for your fire\n\n[Ask for a tree survey](/quote)",
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Lawn & hedge questions',
                'items' => [
                    ['title' => 'When can hedges be cut?', 'text' => 'Ideally late summer and autumn. Between March and August we check for nesting birds first, as the law protects them.'],
                    ['title' => 'Is new turf better than seed?', 'text' => 'Turf gives you a usable lawn in four weeks and works from autumn to spring. Seed is cheaper and best sown in September.'],
                    ['title' => 'Can you cut very tall hedges?', 'text' => 'Yes, with towers and platforms up to six metres. We protect your borders and lawn while we work.'],
                ],
            ]],
        ], $parent );

        return $this;
    }


    /**
     * Creates the garden maintenance page below the services page.
     *
     * @param Page $parent Services page
     * @return static Same object for fluent calls
     */
    protected function addMaintenance( Page $parent ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Garden maintenance',
            'title' => 'Garden Maintenance in Guildford, Woking and Godalming | Bramble & Oak',
            'path' => 'garden-maintenance',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Your garden, *looked after*',
                'subtitle' => 'Garden maintenance',
                'text' => 'Trained gardeners, not just grass cutters. We weed, prune, plant and feed with care, so your garden gets better with every season instead of just tidier.',
                'buttons' => [
                    ['label' => 'See care plans', 'url' => '/care-plans'],
                    ['label' => 'Book a free visit', 'url' => '/quote'],
                ],
                'files' => [['id' => $this->frame( 'pruning' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'What our gardeners do',
                'columns' => '3',
                'cards' => [
                    ['title' => 'Borders & beds', 'text' => "`Every visit`\nWeeding, deadheading, staking and mulching with peat-free compost.", 'file' => ['id' => $this->img( 'border' ), 'type' => 'file']],
                    ['title' => 'Pruning', 'text' => "`In season`\nRoses, shrubs, climbers and fruit trees, cut at the right time.", 'file' => ['id' => $this->img( 'pruning' ), 'type' => 'file']],
                    ['title' => 'Planting', 'text' => "`Spring & autumn`\nSeasonal colour, gap filling and new plants with a 12 month guarantee.", 'file' => ['id' => $this->img( 'planting' ), 'type' => 'file']],
                    ['title' => 'Watering & feeding', 'text' => "`Summer`\nPots, new plants and holiday watering when you are away.", 'file' => ['id' => $this->img( 'watering' ), 'type' => 'file']],
                    ['title' => 'Leaf clearing', 'text' => "`Autumn`\nLawns, paths and gutters cleared, leaves turned into leaf mould.", 'file' => ['id' => $this->img( 'leaves' ), 'type' => 'file']],
                    ['title' => 'Winter care', 'text' => "`Winter`\nFleece, mulch and pruning to bring your garden safely through the frost.", 'file' => ['id' => $this->img( 'winter' ), 'type' => 'file']],
                ],
            ]],
            $this->plans(),
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Maintenance questions',
                'items' => [
                    ['title' => 'Do you bring your own tools?', 'text' => 'Yes, everything from mowers to hedge trimmers. All our tools are battery powered, so visits are quiet.'],
                    ['title' => 'Can you take over a neglected garden?', 'text' => 'Absolutely. We start with a one-off tidy-up, then a care plan keeps it in shape.'],
                    ['title' => 'Do you work in winter?', 'text' => 'Yes. Winter is the time for pruning, structural work and planting bare-root trees and hedges.'],
                ],
            ]],
        ], $parent );

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
            'title' => 'Privacy Policy | Bramble & Oak',
            'path' => 'privacy',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "# Privacy policy\n\n## Who is responsible\n\nBramble & Oak Gardens Ltd, Unit 4, Mill Lane Yard, Shalford, Guildford GU4 8BX, privacy@brambleandoak.example.\n\n## Quotes and garden visits\n\nWhen you ask for a quote or book a care plan, we use your name, address, contact details and the photos you send us to plan visits, prepare quotes and issue invoices (Article 6(1)(b) UK GDPR). We keep invoices for six years as required by law.\n\n## Photos of your garden\n\nWe take photos before and after our work to document it. We only show them on our website or social media with your written consent, and never show your house number or street.\n\n## This website\n\nThe website doesn't use tracking or advertising cookies. Our server stores technical access data such as the IP address for seven days to protect against attacks. The map is loaded from OpenStreetMap only after you open it.\n\n## Your rights\n\nYou have the right to access, rectification, erasure, restriction of processing and data portability, and you can lodge a complaint with the Information Commissioner's Office (ICO).\n\nThis is a demo website for the Grove theme. Bramble & Oak is a fictional company.",
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the projects page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addProjects( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Projects',
            'title' => 'Garden Design and Landscaping Projects in Surrey | Bramble & Oak',
            'path' => 'projects',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Gardens we are *proud of*',
                'subtitle' => 'Projects',
                'text' => 'From small courtyards to walled kitchen gardens: a selection of gardens we designed, built and still look after across Surrey.',
                'buttons' => [
                    ['label' => 'Start your project', 'url' => '/quote'],
                ],
                'files' => [['id' => $this->frame( 'small-garden' ), 'type' => 'file']],
            ]],
            $this->projects( true ),
            ['id' => Utils::uid(), 'type' => 'before-after', 'group' => 'main', 'data' => [
                'title' => 'Before and after',
                'before' => ['id' => $this->cropped( 'before', 1500, 1000 ), 'type' => 'file'],
                'after' => ['id' => $this->cropped( 'after', 1500, 1000 ), 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'What our clients say',
                'items' => $this->reviews(),
            ]],
            $this->cta(),
        ], $home );

        return $this;
    }


    /**
     * Creates the hidden quote request page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addQuote( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Free quote',
            'title' => 'Ask for a Free Garden Quote | Bramble & Oak Surrey',
            'path' => 'quote',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Your free *garden visit*',
                'subtitle' => 'Free quote',
                'text' => 'Tell us about your garden and what you would like to change. We visit you within a week and send a fixed, no-obligation quote afterwards.',
                'files' => [['id' => $this->frame( 'tools' ), 'type' => 'file']],
            ]],
            ['id' => 'quote-form', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'About your garden',
                'description' => 'Not sure what you need? Choose "Not sure yet" and describe what bothers you about your garden. Photos are welcome by email.',
                'inputs' => [
                    ['field' => 'name', 'required' => true, 'input' => 'text'],
                    ['field' => 'email', 'required' => true, 'input' => 'text'],
                    ['field' => 'telephone', 'required' => true, 'input' => 'text'],
                    ['field' => 'Postcode', 'required' => true, 'input' => 'text'],
                    ['field' => 'Service', 'required' => true, 'input' => 'select', 'options' => "Garden design\nLandscaping\nRegular maintenance\nOne-off tidy-up\nLawn or hedge work\nTree work\nNot sure yet"],
                    ['field' => 'Garden size', 'required' => false, 'input' => 'select', 'options' => "Small, up to 50 m²\nMedium, 50 to 200 m²\nLarge, over 200 m²"],
                    ['field' => 'Budget', 'required' => false, 'input' => 'select', 'options' => "Under £5,000\n£5,000 to £15,000\n£15,000 to £40,000\nOver £40,000\nRegular plan"],
                    ['field' => 'Tell us about your garden', 'required' => false, 'input' => 'textarea'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Before we visit',
                'items' => [
                    ['title' => 'Is the visit really free?', 'text' => 'Yes, for gardens in our area. Design consultations with a full written summary cost £180.'],
                    ['title' => 'How long does the visit take?', 'text' => 'About 45 minutes. We look at the garden, measure, talk about your ideas and take a few photos.'],
                    ['title' => 'How soon can you start?', 'text' => 'Care plans usually start within two weeks. Landscaping projects are booked about six to ten weeks ahead.'],
                ],
            ]],
        ], $home );

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
            'title' => 'Garden Design, Landscaping and Garden Maintenance in Surrey | Bramble & Oak',
            'path' => 'services',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Everything your garden needs, *from one team*',
                'subtitle' => 'Services',
                'text' => 'Designers, landscapers, gardeners and arborists working together. You have one contact from the first sketch to the weekly mow.',
                'buttons' => [
                    ['label' => 'Get a free quote', 'url' => '/quote'],
                    ['label' => 'Care plans', 'url' => '/care-plans'],
                ],
                'files' => [['id' => $this->frame( 'path' ), 'type' => 'file']],
            ]],
            $this->services(),
            $this->process(),
            $this->promises(),
            $this->cta(),
        ], $home );

        return $this->addDesign( $services )
            ->addLandscaping( $services )
            ->addMaintenance( $services )
            ->addLawns( $services );
    }


    /**
     * Returns the quote call to action element.
     *
     * @return array<string, mixed> CTA content element
     */
    protected function cta() : array
    {
        return ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
            'title' => 'Ready for a garden you *love to come home to*?',
            'text' => 'Book a free garden visit. We listen, measure and send you a fixed, no-obligation quote within a week.',
            'buttons' => [
                ['label' => 'Get a free quote', 'url' => '/quote'],
                ['label' => 'Call 01632 960 418', 'url' => 'tel:+441632960418'],
            ],
        ]];
    }


    /**
     * Creates the shared Bramble & Oak footer and returns its ID.
     *
     * @return string Element ID
     */
    protected function element() : string
    {
        return $this->element ??= $this->saveElement( 'cards', 'Bramble & Oak footer', ['columns' => '4', 'cards' => [
            ['title' => 'Services', 'text' => "- [Garden design](/garden-design)\n- [Landscaping](/landscaping)\n- [Garden maintenance](/garden-maintenance)\n- [Lawns & hedges](/lawns-hedges)"],
            ['title' => 'Customers', 'text' => "- [Care plans](/care-plans)\n- [Projects](/projects)\n- [Free quote](/quote)\n- [Contact](/contact)"],
            ['title' => 'Bramble & Oak', 'text' => "- [About us](/about)\n- [Journal](/journal)\n- [Imprint](/imprint)\n- [Privacy](/privacy)"],
            ['title' => 'Seasonal notes', 'text' => "Four short emails a year with what to do in your garden this season. No advertising, promised.\n\n[Sign up by email](mailto:hello@brambleandoak.example?subject=Seasonal%20notes)"],
        ]] );
    }


    /**
     * Returns the ID of the primary garden image.
     *
     * @return string File ID
     */
    protected function file() : string
    {
        return $this->img( 'hero-garden' );
    }


    /**
     * Creates (once) a 4:5 portrait image for the arched hero frames and returns its file ID.
     *
     * @param string $key PHOTOS key
     * @return string File ID
     */
    protected function frame( string $key ) : string
    {
        return $this->cropped( $key, 960, 1200, true, [480, 960] );
    }


    /**
     * Returns the Bramble & Oak home page.
     *
     * @return Page Home page
     */
    protected function home() : Page
    {
        $elementId = $this->element();
        $fileId = $this->file();

        $config = [
            'website' => Validation::entry( 'website', ['title' => 'Bramble & Oak'], 'config' ),
        ] + $this->logos( $this->logoFile() ) + [
            'grove::garden' => [
                'type' => 'grove::garden',
                'files' => [],
                'data' => [
                    'name' => 'Bramble & Oak Gardens',
                    'business-type' => 'HomeAndConstructionBusiness',
                    'street-address' => 'Unit 4, Mill Lane Yard',
                    'postal-code' => 'GU4 8BX',
                    'locality' => 'Guildford',
                    'country' => 'GB',
                    'telephone' => '+44 1632 960 418',
                    'areas' => 'Guildford, Godalming, Woking, Farnham, Cranleigh, Shalford, Bramley, Send',
                    'email' => 'hello@brambleandoak.example',
                    'announcement' => 'Autumn tidy-ups and bulb planting: book your visit now',
                    'quote' => '/quote',
                    'plans' => '/care-plans',
                    'price-range' => '££',
                    'notice' => 'Fully insured, BALI registered, and all our planting comes with a 12 month guarantee.',
                    'action-bar' => true,
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
                'title' => 'Gardens that *grow* with you',
                'subtitle' => 'Garden design, landscaping and care in Surrey',
                'text' => 'We design, build and look after gardens across Guildford, Woking and Godalming. One friendly team, honest fixed prices and plants that thrive long after we have packed up.',
                'buttons' => [
                    ['label' => 'Get a free quote', 'url' => '/quote'],
                    ['label' => 'See our projects', 'url' => '/projects'],
                ],
                'files' => [['id' => $this->frame( 'hero-garden' ), 'type' => 'file']],
                'main' => true,
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'layout' => 'figures',
                'cards' => [
                    ['title' => '4.9', 'text' => 'Average rating from 410 reviews'],
                    ['title' => '300+', 'text' => 'Gardens in our regular care'],
                    ['title' => '2009', 'text' => 'Gardening in Surrey since'],
                    ['title' => '12 mo', 'text' => 'Guarantee on all our planting'],
                ],
            ]],
            $this->services(),
            $this->process(),
            $this->projects( false ),
            ['id' => Utils::uid(), 'type' => 'before-after', 'group' => 'main', 'data' => [
                'title' => 'From overgrown to easy-care',
                'before' => ['id' => $this->cropped( 'before', 1500, 1000 ), 'type' => 'file'],
                'after' => ['id' => $this->cropped( 'after', 1500, 1000 ), 'type' => 'file'],
            ]],
            $this->promises(),
            $this->plans(),
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'Kind words from our clients',
                'items' => $this->reviews(),
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Frequently asked questions',
                'items' => [
                    ['title' => 'Which areas do you cover?', 'text' => 'Guildford, Godalming, Woking, Farnham, Cranleigh and the villages in between, about 15 miles around our yard in Shalford.'],
                    ['title' => 'Are your quotes really free?', 'text' => 'Yes. We visit your garden, listen to your ideas and send a fixed price within a week, without any obligation.'],
                    ['title' => 'Are you insured?', 'text' => 'We carry £5 million public liability insurance and are registered with BALI, the British Association of Landscape Industries.'],
                    ['title' => 'Do you take away the green waste?', 'text' => 'Always. It is included in our prices and composted locally, never sent to landfill.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'blog', 'group' => 'main', 'data' => [
                'title' => 'From our garden journal',
                'layout' => 'cards',
                'parent-page' => ['value' => $this->journalId, 'label' => 'Journal'],
                'order' => '_lft',
                'limit' => 3,
            ]],
            $this->map( 'Find our yard' ),
            $this->cta(),
            ['id' => Utils::uid(), 'type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        $meta = [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => 'Bramble & Oak: garden design, landscaping, garden maintenance, lawn care and hedge cutting in Guildford, Woking, Godalming and across Surrey. Free quotes, fixed prices, fully insured.',
                'keywords' => 'gardener Guildford, landscaper Surrey, garden design Surrey, garden maintenance Woking, hedge cutting Godalming, patio installation Guildford',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => 'Bramble & Oak | Garden Design, Landscaping and Care in Surrey',
                'description' => 'Gardens that grow with you.',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        return $this->saveRoot( 'Bramble & Oak | Gardeners and Landscapers in Guildford and Surrey', $config, $meta, $content, $elementId, $fileId );
    }


    /**
     * Creates a white line icon once and returns its file reference.
     *
     * @param string $name Icon name: calendar, check, droplet, leaf, shield, shovel or sun
     * @return array<string, string> File reference
     */
    protected function icon( string $name ) : array
    {
        $paths = [
            'calendar' => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/>',
            'check' => '<circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/>',
            'droplet' => '<path d="M12 3c3 4.5 6 7.5 6 11a6 6 0 0 1-12 0c0-3.5 3-6.5 6-11z"/>',
            'leaf' => '<path d="M5 19c0-8 5-14 15-15-1 10-7 15-15 15z"/><path d="M5 19l8-8"/>',
            'shield' => '<path d="M12 3l7 3v5c0 4.5-3 8.2-7 10-4-1.8-7-5.5-7-10V6z"/><path d="M9 12l2 2 4-4"/>',
            'shovel' => '<path d="M17 3l4 4-3 1-6 6"/><path d="M12 14l-2-2-5 5a2 2 0 0 0 3 3z"/>',
            'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
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
     * Creates the Bramble & Oak SVG logo and returns its file ID.
     *
     * @return string File ID
     */
    protected function logoFile() : string
    {
        if( !isset( $this->logoFile ) )
        {
            $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 280 64" role="img" aria-labelledby="title desc">
  <title id="title">Bramble &amp; Oak logo</title>
  <desc id="desc">Green circle with a white leaf beside the serif wordmark Bramble &amp; Oak and the word Gardens</desc>
  <circle cx="30" cy="32" r="26" fill="#3F6B3A"/>
  <path d="M18 44c0-14 8-23 24-25-1.5 15-10 24-24 25z" fill="#FFFFFF"/>
  <path d="M18 44l14-14" stroke="#3F6B3A" stroke-width="2" stroke-linecap="round"/>
  <text x="66" y="36" fill="#1F3A2C" font-family="'Hoefler Text', 'Iowan Old Style', Constantia, Georgia, serif" font-size="27" font-weight="500">Bramble <tspan fill="#A0532D" font-style="italic">&amp;</tspan> Oak</text>
  <text x="67" y="54" fill="#3F6B3A" font-family="system-ui, -apple-system, 'Segoe UI', sans-serif" font-size="10" letter-spacing="3.6">GARDENS</text>
</svg>
SVG;

            $this->logoFile = $this->svgFile(
                $svg,
                'bramble-oak-logo.svg',
                'Bramble & Oak logo',
                'Green circle with a white leaf beside the serif wordmark Bramble & Oak and the word Gardens',
                true,
            );
        }

        return $this->logoFile;
    }


    /**
     * Returns the map element with address, office hours and directions.
     *
     * @param string $title Map headline
     * @return array<string, mixed> Map content element
     */
    protected function map( string $title ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'map', 'group' => 'main', 'data' => [
            'title' => $title,
            'text' => "**Bramble & Oak Gardens**\nUnit 4, Mill Lane Yard · Shalford, Guildford GU4 8BX\n\n**Office hours**\nMonday to Friday 08:00–17:30\nSaturday 09:00–13:00\n\n**Telephone**\n+44 1632 960 418\n\n**Getting here**\nA281 from Guildford towards Shalford, turn left after the mill. Visits by appointment, we are out in gardens most of the day.",
            'location' => [
                'latitude' => 51.2149,
                'longitude' => -0.5671,
                'zoom' => 15,
            ],
            'button' => 'Open in OpenStreetMap',
        ]];
    }


    /**
     * Creates a Grove demo page below the given parent and returns it.
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

        return $this->savePage( $data, $content, $parent, $elementId, $fileId, $footer, 'Bramble & Oak, gardener Guildford, landscaper Surrey, garden design, garden maintenance, hedge cutting, patios' );
    }


    /**
     * Builds the Grove demo page tree.
     */
    protected function pages() : void
    {
        $this->journalId = (string) Str::uuid7();
        $home = $this->home();

        $this->addServices( $home )
            ->addProjects( $home )
            ->addCarePlans( $home )
            ->addAbout( $home )
            ->addJournal( $home )
            ->addContact( $home )
            ->addQuote( $home )
            ->addImprint( $home )
            ->addPrivacy( $home );
    }


    /**
     * Returns the garden care plans element.
     *
     * @return array<string, mixed> Pricing content element
     */
    protected function plans() : array
    {
        return ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
            'title' => 'Garden care plans',
            'text' => 'Prices per visit for a typical garden, including green waste removal and VAT. No contract, pause any time.',
            'items' => [
                [
                    'name' => 'Lawn care',
                    'prices' => [['id' => 'lawn', 'amount' => 45, 'label' => '£45']],
                    'text' => 'per visit, fortnightly',
                    'features' => "- Mowing and edging `every visit`\n- Leaf clearing `autumn`\n- Spring and autumn feed `twice a year`\n- Scarifying and aerating `once a year`",
                    'url' => '/quote',
                    'button' => 'Choose lawn care',
                ],
                [
                    'name' => 'Garden care',
                    'prices' => [['id' => 'garden', 'amount' => 95, 'label' => '£95']],
                    'text' => 'per visit, fortnightly',
                    'features' => "- Everything in lawn care\n- Weeding and deadheading `every visit`\n- Pruning and hedge trimming `in season`\n- Mulching with peat-free compost `spring`\n- Same gardener every time",
                    'url' => '/quote',
                    'button' => 'Choose garden care',
                    'highlight' => true,
                    'badge' => 'Most popular',
                ],
                [
                    'name' => 'Estate care',
                    'prices' => [['id' => 'estate', 'amount' => 125, 'label' => '£125']],
                    'text' => 'per visit, weekly',
                    'features' => "- Everything in garden care\n- Seasonal planting `spring and autumn`\n- Pots and holiday watering `summer`\n- Pond and path care `monthly`\n- Yearly garden review with our designer",
                    'url' => '/quote',
                    'button' => 'Choose estate care',
                ],
            ],
        ]];
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
                'title' => 'Questions from our clients',
                'items' => $questions,
            ]],
            $this->cta(),
        ], $parent );
    }


    /**
     * Returns the garden process timeline element.
     *
     * @return array<string, mixed> Timeline content element
     */
    protected function process() : array
    {
        return ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
            'title' => 'How we work',
            'layout' => 'horizontal',
            'items' => [
                ['label' => '01', 'title' => 'Free garden visit', 'text' => 'We walk round your garden with you and listen to your ideas.'],
                ['label' => '02', 'title' => 'Plan & fixed price', 'text' => 'You get a clear plan and a quote that doesn\'t change.'],
                ['label' => '03', 'title' => 'Build & plant', 'text' => 'Our own team does the work, tidy and on schedule.'],
                ['label' => '04', 'title' => 'Care & enjoy', 'text' => 'We keep your garden at its best, season after season.'],
            ],
        ]];
    }


    /**
     * Returns the project cards element.
     *
     * @param bool $all Whether to list all projects or only the first three
     * @return array<string, mixed> Cards content element
     */
    protected function projects( bool $all ) : array
    {
        $projects = [
            ['Walled kitchen garden', 'raised-beds', 'Shalford · 2025', 'Oak raised beds, a brick path and fruit trees trained along a Victorian wall.'],
            ['Family patio', 'patio', 'Woking · 2024', 'A sandstone terrace for twelve, framed by evergreen shrubs and a new fence.'],
            ['Wildlife pond', 'pond', 'Cranleigh · 2024', 'A planted pond with a timber deck, water lilies and a beach for hedgehogs.'],
            ['Town courtyard', 'small-garden', 'Guildford · 2025', 'A tiny courtyard turned into a green room with box hedging and pots.'],
            ['Garden decking', 'decking', 'Godalming · 2023', 'Three levels of hardwood deck stepping down to the lawn, with built-in planters.'],
            ['Rock garden path', 'path', 'Farnham · 2024', 'A winding flagstone path through alpines, grasses and dwarf conifers.'],
            ['Japanese gravel garden', 'gravel', 'Bramley · 2023', 'Raked gravel, moss and a single boulder for a calm view from the study.'],
            ['Garden lighting', 'lighting', 'Send · 2025', 'Glowing spheres and warm string lights for long summer evenings.'],
            ['Pergola terrace', 'pergola', 'Merrow · 2025', 'A louvred pergola over the lounge area, softened by grasses and lavender.'],
        ];

        return ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
            'title' => $all ? 'Recent projects' : 'Recent gardens',
            'columns' => '3',
            'cards' => array_map( fn( $project ) => array_filter( [
                'title' => $project[0],
                'text' => '`' . $project[2] . "`\n" . $project[3],
                'file' => ['id' => $this->img( $project[1] ), 'type' => 'file'],
                'url' => $all ? null : '/projects',
            ] ), $all ? $projects : array_slice( $projects, 0, 3 ) ),
        ]];
    }


    /**
     * Returns the Bramble & Oak promise badges element.
     *
     * @return array<string, mixed> Cards content element
     */
    protected function promises() : array
    {
        return ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
            'title' => 'Why choose Bramble & Oak',
            'layout' => 'badges',
            'cards' => [
                ['title' => 'Fully insured', 'text' => '£5 million public liability', 'file' => $this->icon( 'shield' )],
                ['title' => 'BALI registered', 'text' => 'Accredited landscape contractor', 'file' => $this->icon( 'leaf' )],
                ['title' => 'Plant guarantee', 'text' => '12 months on everything we plant', 'file' => $this->icon( 'check' )],
                ['title' => 'Same gardener', 'text' => 'Who knows your garden', 'file' => $this->icon( 'calendar' )],
                ['title' => 'Peat-free', 'text' => 'And battery tools only', 'file' => $this->icon( 'droplet' )],
            ],
        ]];
    }


    /**
     * Returns the customer reviews.
     *
     * @return array<int, array<string, mixed>> Testimonial items
     */
    protected function reviews() : array
    {
        return [
            ['name' => 'Claire', 'role' => 'Garden design, Godalming', 'text' => 'Hannah understood exactly what we wanted before we did. Two years on, the borders look better every summer.', 'file' => ['id' => $this->cropped( 'review-1', 200, 200 ), 'type' => 'file']],
            ['name' => 'James', 'role' => 'New patio and lawn, Woking', 'text' => 'They turned up every morning at eight, kept the site tidy and finished a day early. The patio is perfect.', 'file' => ['id' => $this->cropped( 'review-2', 200, 200 ), 'type' => 'file']],
            ['name' => 'Peter', 'role' => 'Garden care plan, Cranleigh', 'text' => 'Ellie has looked after our garden for three years now. We just enjoy it, she does the hard work.', 'file' => ['id' => $this->cropped( 'review-3', 200, 200 ), 'type' => 'file']],
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
            'title' => 'What we do',
            'columns' => '3',
            'cards' => [
                ['title' => 'Garden design', 'url' => '/garden-design', 'text' => "**Design**\nPlans, 3D visuals and planting schemes that suit your plot and the way you live.", 'file' => ['id' => $this->img( 'border' ), 'type' => 'file']],
                ['title' => 'Landscaping', 'url' => '/landscaping', 'text' => "**Build**\nPatios, paths, decking, ponds and raised beds with a ten year guarantee.", 'file' => ['id' => $this->img( 'patio' ), 'type' => 'file']],
                ['title' => 'Garden maintenance', 'url' => '/garden-maintenance', 'text' => "**Care**\nBorders, pruning and planting by trained gardeners, weekly to monthly.", 'file' => ['id' => $this->img( 'lawn-mowing' ), 'type' => 'file']],
                ['title' => 'Lawn care', 'url' => '/lawns-hedges', 'text' => "**Care**\nMowing, feeding, scarifying and new turf for a lawn to be proud of.", 'file' => ['id' => $this->img( 'lawn' ), 'type' => 'file']],
                ['title' => 'Hedge cutting', 'url' => '/lawns-hedges', 'text' => "**Care**\nClean lines for hedges of every height, clippings taken away.", 'file' => ['id' => $this->img( 'hedge-trimming' ), 'type' => 'file']],
                ['title' => 'Tree work', 'url' => '/lawns-hedges', 'text' => "**Care**\nPruning, crown reduction and felling by qualified arborists.", 'file' => ['id' => $this->img( 'tree-surgery' ), 'type' => 'file']],
            ],
        ]];
    }


    /**
     * Returns the team cards element.
     *
     * @return array<string, mixed> Cards content element
     */
    protected function team() : array
    {
        $people = [
            ['Tom Ashworth', 'team-1', "**Founder & head of landscaping**\nStarted with one van in 2009. Still lays the first slab of every patio himself."],
            ['Hannah Price', 'team-2', "**Garden designer**\nRHS medal-winning designer with a soft spot for cottage gardens and edible planting."],
            ['Geoff Marlow', 'team-3', "**Head gardener**\nForty years of plant knowledge. Knows the Latin name of everything in your border."],
            ['Ellie Brook', 'team-4', "**Care plan lead**\nRuns our maintenance rounds and makes sure every garden gets the same gardener."],
        ];

        return ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
            'title' => 'Meet the gardeners',
            'columns' => '4',
            'cards' => array_map( fn( $person ) => [
                'title' => $person[0],
                'text' => $person[2],
                'file' => ['id' => $this->cropped( $person[1], 800, 1000 ), 'type' => 'file'],
            ], $people ),
        ]];
    }
}
