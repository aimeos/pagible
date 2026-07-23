<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Database\Seeders;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Validation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


/**
 * Style theme demo for the fictional Veyra independent fashion label.
 */
class StyleDemo extends AbstractDemo
{
    /** @var array<string, string> Meta descriptions keyed by page path */
    private const DESCRIPTIONS = [
        'atelier' => 'Step inside the Veyra atelier in Berlin and see how limited-edition garments move from paper pattern to final hand finish.',
        'collection' => 'Discover Veyra Collection No. 08: limited-edition coats, tailoring, dresses, and separates cut in Berlin from traceable European cloth.',
        'journal' => 'Read Veyra Journal notes from the cutting room on proportion, bias cutting, fittings, undyed wool, and clothes made to stay in rotation.',
        'lookbook' => 'View the Veyra No. 08 lookbook, photographed after dark in Berlin and built around long coats, fluid dresses, and exact tailoring.',
        'a-fitting-is-a-conversation' => 'Veyra founder Mara Vey explains why a fitting is less about correction than learning how a person stands, moves, and wears a garment.',
        'the-coat-begins-on-paper' => 'Follow the making of the Veyra Orbit Coat from the first full-scale paper line through toile, balance, cloth, and hand finishing.',
        'what-a-bias-cut-remembers' => 'Inside the Veyra cutting room, discover how bias-cut cloth stretches, settles, and remembers the body without losing its own line.',
        'why-this-wool-was-left-undyed' => 'Why Veyra chose undyed wool for the Fold Jacket, preserving the natural depth, variation, and character of the fibre.',
        'visit' => 'Book a private Veyra fitting in Berlin, ask about sizing or alterations, and find boutique hours, delivery details, and contact information.',
    ];

    /**
     * Curated Unsplash photos used across the Veyra demo.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const PHOTOS = [
        'atelier' => ['photo-1718184021018-d2158af6b321', 'Veyra cutting room', 'Tailor measuring and cutting dark cloth by hand on the atelier table'],
        'coat' => ['photo-1613915617430-8ab0fd7c6baf', 'Veyra Orbit Coat', 'Monochrome editorial portrait of a model in a structured grey coat'],
        'collection' => ['photo-1613909671501-f9678ffc1d33', 'Veyra Collection No. 08', 'Runway model presenting a pale layered look beneath a single spotlight'],
        'detail' => ['photo-1745095037129-d266d5aa4b48', 'Veyra garment detail', 'Cloth pinned over a paper pattern while an atelier piece is developed'],
        'dress' => ['photo-1743079701830-c4a45ddc11a1', 'Veyra Column Dress', 'Model wearing a long black dress against a weathered stone wall'],
        'fabric' => ['photo-1534639077088-d702bcf685e7', 'Veyra cloth study', 'Rolls of pale natural cloth stored together in a textile workroom'],
        'fitting' => ['photo-1629726343583-d9718b3e8f3f', 'Veyra private fitting', 'Atelier fitter reviewing a garment with measuring tape and pattern notes'],
        'home' => ['photo-1580478491436-fd6a937acc9e', 'Veyra evening campaign', 'Model in a deep red jacket photographed inside a mirrored architectural space'],
        'jacket' => ['photo-1614786269829-d24616faf56d', 'Veyra Fold Jacket', 'Model in precise black tailoring photographed against a neutral studio backdrop'],
        'look-one' => ['photo-1590131222139-91ba5992e4ed', 'Veyra Look 01', 'Model wearing a sculptural black hat and monochrome evening look'],
        'look-two' => ['photo-1657815929003-b97cc426cb3d', 'Veyra Look 02', 'Close view of fluid white tailoring moving across a dark runway'],
        'pattern' => ['photo-1558303522-d7a2bdfdbd82', 'Veyra pattern archive', 'Dress form and line drawing prepared for a new atelier pattern'],
        'portrait' => ['photo-1524504388940-b1c1722653e1', 'Mara Vey portrait', 'Portrait of the fictional Veyra founder and creative director'],
        'rail' => ['photo-1558769132-cb1aea458c5e', 'Veyra boutique rail', 'A restrained rail of cream, camel, and black garments in a quiet boutique'],
        'studio' => ['photo-1764298493231-59ae059cdc7e', 'Veyra studio', 'Cutter working over dark cloth on a long table inside a bright atelier'],
        'wool' => ['photo-1675503861565-c5364a67356b', 'Undyed wool', 'Close view of mottled grey wool showing its natural fibre and tonal variation'],
    ];

    private string $element;
    private string $logoFile;
    /** @var array<string, string> File IDs for fixed-ratio portrait images */
    private array $portraitImages = [];
    /** @var array<string, string> File IDs for fixed-ratio pricing images */
    private array $pricingImages = [];
    /** @var array<string, string> File IDs for fixed-ratio slideshow images */
    private array $slideImages = [];


    /**
     * Creates the atelier page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addAtelier( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Atelier',
            'title' => 'The Veyra Atelier | Cut and Finished in Berlin',
            'path' => 'atelier',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'The work stays close',
                'subtitle' => 'Veyra Atelier — Berlin',
                'text' => 'Every style is developed, fitted, and finished within a short walk of our boutique. Keeping the work close lets us make fewer pieces and know each one properly.',
                'url' => '#process',
                'button' => 'See the process',
                'url-alternative' => '/visit',
                'button-alternative' => 'Book a fitting',
                'files' => [
                    ['id' => $this->img( 'atelier' ), 'type' => 'file'],
                    ['id' => $this->img( 'pattern' ), 'type' => 'file'],
                    ['id' => $this->img( 'detail' ), 'type' => 'file'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'portrait' ), 'type' => 'file'],
                'position' => 'grid-end',
                'ratio' => '1-2',
                'text' => "## Born at the table\n\nMara Vey founded Veyra in 2019 after twelve years cutting patterns for larger houses in Paris and Antwerp. The first collection contained one coat, one trouser, and two dresses. The method has not changed much since.\n\nA line earns its place through repeated fittings, not a seasonal theme. The studio works directly with small mills, cutters, and finishers who can answer a question without a chain of intermediaries.",
            ]],
            ['id' => 'process', 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Four hands on every piece',
                'cards' => [
                    ['title' => 'Pattern & toile', 'text' => 'Every style starts at full scale on paper. Calico then tests balance, reach, and volume through repeated fittings.'],
                    ['title' => 'Cut', 'text' => 'Cloth is rested, matched, and cut in small lays to preserve grain and reduce avoidable waste.'],
                    ['title' => 'Finish', 'text' => 'Buttonholes, hems, linings, and final pressing are checked by the same team that made the sample.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'slideshow', 'group' => 'main', 'data' => [
                'title' => 'Inside the workroom',
                'main' => true,
                'files' => [
                    ['id' => $this->slideImg( 'atelier' ), 'type' => 'file'],
                    ['id' => $this->slideImg( 'pattern' ), 'type' => 'file'],
                    ['id' => $this->slideImg( 'fabric' ), 'type' => 'file'],
                    ['id' => $this->slideImg( 'detail' ), 'type' => 'file'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Material ledger — Edition No. 08',
                'header' => 'row+col',
                'table' => [
                    ['Cloth', 'Origin', 'Used for', 'Why it was chosen'],
                    ['Undyed wool twill', 'Biella, Italy', 'Fold Jacket', 'Dry handle, natural depth, and a firm line without stiffness'],
                    ['Double-faced wool', 'Prato, Italy', 'Orbit Coat', 'Warmth without bulk and clean internal construction'],
                    ['Silk crepe', 'Lyon, France', 'Column Dress', 'Weight, recovery, and movement on the bias'],
                    ['Linen-wool canvas', 'Kortrijk, Belgium', 'Frame Trouser', 'A cool surface with enough memory to hold the crease'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'From the fitting room',
                'items' => [
                    ['name' => 'Nora H.', 'role' => 'Architect, Berlin', 'text' => 'They changed the sleeve, not my posture. The finished coat feels exact when I stand still and completely easy when I move.'],
                    ['name' => 'Leonie M.', 'role' => 'Collector, Vienna', 'text' => 'My first Veyra jacket is five years old. It comes back from repair sharper than many new clothes looked on day one.'],
                    ['name' => 'Sara A.', 'role' => 'Creative director, Copenhagen', 'text' => 'The fitting was quiet and specific. No performance, no pressure—just very good eyes and an honest conversation about cloth.'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the journal and its four stories below the home page.
     *
     * @param Page $home Home page
     * @param string $journalId Journal page ID referenced by listing elements
     * @return static Same object for fluent calls
     */
    protected function addBlog( Page $home, string $journalId ) : static
    {
        $journal = $this->page( [
            'id' => $journalId,
            'lang' => 'en',
            'name' => 'Journal',
            'title' => 'Veyra Journal | Notes from the Cutting Room',
            'path' => 'journal',
            'tag' => 'blog',
            'type' => 'blog',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Notes from the cutting room',
                'subtitle' => 'Veyra Journal',
                'text' => 'On pattern, cloth, fittings, and the small decisions that determine whether a garment stays in your wardrobe.',
                'files' => [['id' => $this->img( 'pattern' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'blog', 'group' => 'main', 'data' => [
                'title' => 'Stories and studio notes',
                'layout' => 'default',
                'limit' => 4,
                'order' => '_lft',
                'parent-page' => ['value' => $journalId, 'label' => 'Journal'],
            ]],
        ], $home );

        $this->page( [
            'lang' => 'en',
            'name' => 'The coat begins on paper',
            'title' => 'The Coat Begins on Paper',
            'path' => 'the-coat-begins-on-paper',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'The coat begins on paper',
                "Before the Orbit Coat has weight, colour, or a pocket, it is a line drawn at full scale. The shoulder begins slightly behind the body. The front closes off-centre. The hem turns forward by less than a centimetre.\n\nNone of those decisions look dramatic on paper. Together, they determine whether the coat holds its shape while the person inside it keeps moving.",
                $this->img( 'coat' )
            ),
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Draw the balance first',
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'pattern' ), 'type' => 'file'],
                'position' => 'grid-end',
                'ratio' => '1-2',
                'text' => "A coat pattern is a set of negotiations. Space across the back must not become bulk at the waist. A strong shoulder must still let the arm reach a bicycle handlebar. A deep pocket must not pull the front out of line.\n\nWe test those relationships in calico before cutting wool. The toile looks plain because it has nowhere to hide. That is its value.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Orbit Coat pattern record',
                'header' => 'row',
                'table' => [
                    ['Stage', 'Question', 'Decision'],
                    ['Paper 01', 'How much ease belongs across the back?', 'Add volume through a curved side-back seam'],
                    ['Toile 02', 'Can the arm rise without lifting the coat?', 'Raise the underarm and rotate the sleeve forward'],
                    ['Cloth 01', 'Does the front stay closed while walking?', 'Shift the inner fastening by eight millimetres'],
                    ['Production', 'Will the edge remain clean after wear?', 'Secure the facing by hand at the shoulder and hem'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "The finished coat carries the evidence quietly. Its ease is there when you reach. Its weight settles when you stop. Good pattern work is not invisible; it is simply experienced before it is noticed.",
            ]],
            $this->articleHero( 'Try the line in motion', 'The Orbit Coat is available in the boutique and through private remote fitting.' ),
        ], $journal );

        $this->page( [
            'lang' => 'en',
            'name' => 'What a bias cut remembers',
            'title' => 'What a Bias Cut Remembers',
            'path' => 'what-a-bias-cut-remembers',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'What a bias cut remembers',
                "Turn woven cloth forty-five degrees and its behaviour changes. The stable grid begins to yield. A straight length becomes responsive, following the body without darts or rigid shaping.\n\nThat movement gives the Column Dress its ease. It also means the cloth has to be listened to before it can be finished.",
                $this->img( 'dress' )
            ),
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Let the cloth settle',
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'detail' ), 'type' => 'file'],
                'position' => 'grid-start',
                'ratio' => '1-2',
                'text' => "Once cut, each dress hangs for forty-eight hours. The silk lengthens where gravity finds the most give. Only then is the hem marked on the stand and levelled by hand.\n\nRushing that pause produces a dress that changes after its first evening out. Waiting lets the fabric find the line it intends to keep.",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Three rules for bias',
                'cards' => [
                    ['title' => 'Cut one by one', 'text' => 'Every layer is cut separately so the silk cannot shift against itself.'],
                    ['title' => 'Handle lightly', 'text' => 'Seams are supported without removing the natural yield that gives the dress its movement.'],
                    ['title' => 'Finish last', 'text' => 'The final length is set only after the garment has rested and been fitted on the body.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "The result should never feel fragile. The Column Dress is made to be packed, worn for hours, and hung overnight. Its luxury is not delicacy; it is the way the line returns.",
            ]],
            $this->articleHero( 'Meet the Column Dress', 'See the silk, compare lengths, and arrange a fitting for Collection No. 08.' ),
        ], $journal );

        $this->page( [
            'lang' => 'en',
            'name' => 'A fitting starts with you',
            'title' => 'A Fitting Starts With You',
            'path' => 'a-fitting-is-a-conversation',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'A fitting starts with you',
                "A fitting is often described as correction: lift this shoulder, narrow that waist, shorten this side. We begin elsewhere. How does the client stand when nobody arranges them? Where do they put a phone? Which shoes change the proportion?\n\nThe garment has a point of view, but it also has to enter a real life.",
                $this->img( 'fitting' )
            ),
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Watch before pinning',
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'rail' ), 'type' => 'file'],
                'position' => 'grid-end',
                'ratio' => '1-2',
                'text' => "We ask a client to walk, sit, fasten the garment, and reach into its pockets. Those ordinary gestures show more than a still mirror can. A sleeve may be the correct length and still catch at the elbow. A waist may fit and still ask to be adjusted all day.\n\nAlterations should remove those interruptions without sanding away the design.",
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Fitting questions',
                'items' => [
                    ['title' => 'Must I know my Veyra size?', 'text' => 'No. We begin with the garments and proportions you want to explore, then take measurements only where they help.'],
                    ['title' => 'What should I bring?', 'text' => 'Bring the shoes or foundation layer you expect to wear, especially for trousers, long dresses, and occasion pieces.'],
                    ['title' => 'Can you fit a remote order?', 'text' => 'Yes. A video fitting and a few guided measurements are enough for most pieces; complex alterations may require a local tailor.'],
                    ['title' => 'How long does a fitting take?', 'text' => 'Allow about an hour for a first fitting. Follow-up appointments are usually shorter and concentrate on the pinned adjustments.'],
                    ['title' => 'Will alterations change the design?', 'text' => 'They should refine how the garment sits without weakening its line. Any change that affects the design is discussed before work begins.'],
                    ['title' => 'What happens after the fitting?', 'text' => 'You receive a written summary of the garment, cloth, measurements, alterations, final price, and expected completion date.'],
                ],
            ]],
            $this->articleHero( 'Make time for the fitting', 'Private appointments are available Tuesday through Saturday in Berlin or by video.' ),
        ], $journal );

        $this->page( [
            'lang' => 'en',
            'name' => 'Why this wool was left undyed',
            'title' => 'Why This Wool Was Left Undyed',
            'path' => 'why-this-wool-was-left-undyed',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'Why this wool was left undyed',
                "The Fold Jacket appears charcoal from across the room. Up close, the cloth is made from brown, grey, and cream fibres blended before spinning. The colour belongs to the fleece rather than a dye bath.\n\nWe chose it for the depth of the surface, not to turn a material decision into a slogan.",
                $this->img( 'wool' )
            ),
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'jacket' ), 'type' => 'file'],
                'position' => 'grid-start',
                'ratio' => '1-2',
                'text' => "## Colour with its own variation\n\nUndyed yarn refuses perfect uniformity. One bolt runs a little warmer; another carries more silver through the face. We place matching pieces beside one another before cutting and keep each garment within a single shade group.\n\nThe variation is controlled, but never erased. It makes a dark jacket respond to daylight in a way a flat black cannot.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'From fleece to jacket',
                'header' => 'row',
                'table' => [
                    ['Step', 'Place', 'Purpose'],
                    ['Sorting', 'Alentejo, Portugal', 'Separate natural shades and remove coarse fibre'],
                    ['Spinning', 'Biella, Italy', 'Blend tone while preserving visible depth'],
                    ['Weaving', 'Biella, Italy', 'Build a compact twill with a dry, clean face'],
                    ['Making', 'Berlin, Germany', 'Cut, assemble, fit, and finish in limited runs'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Leaving the wool undyed does not make the jacket impact-free, and we do not describe it that way. It removes one process, gives the fibre's existing colour a purpose, and produces a cloth we would choose on appearance alone.",
            ]],
            $this->articleHero( 'See the cloth in daylight', 'The Fold Jacket is available in three natural shade groups, each cut in a numbered edition.' ),
        ], $journal );

        return $this;
    }


    /**
     * Creates the collection page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addCollection( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Collection',
            'title' => 'Veyra Collection No. 08 | Limited Edition Womenswear',
            'path' => 'collection',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'No. 08 — After dark',
                'subtitle' => 'Veyra Autumn / Winter 2026',
                'text' => 'Long lines, exact shoulders, and cloth that catches the last available light. Cut in numbered editions in Berlin.',
                'url' => '#pieces',
                'button' => 'View the pieces',
                'url-alternative' => '/lookbook',
                'button-alternative' => 'Open the lookbook',
                'files' => [
                    ['id' => $this->img( 'collection' ), 'type' => 'file'],
                    ['id' => $this->img( 'coat' ), 'type' => 'file'],
                    ['id' => $this->img( 'dress' ), 'type' => 'file'],
                ],
            ]],
            ['id' => 'pieces', 'type' => 'pricing', 'group' => 'main', 'data' => [
                'title' => 'Signature pieces',
                'text' => 'Each style is cut in one small edition. Prices include a personal fitting and standard alterations before delivery.',
                'items' => [
                    ['name' => 'Orbit Coat', 'price' => '1.680€', 'unit' => '/double-faced wool', 'text' => 'A long wrap coat with an off-centre close, curved back seam, and hand-finished internal edges.', 'features' => "- Ink or mineral\n- Sizes 32–46\n- Edition of 36\n- Four-week delivery", 'file' => ['id' => $this->priceImg( 'coat' ), 'type' => 'file'], 'url' => '/visit', 'button' => 'Request a fitting', 'highlight' => true, 'badge' => 'Atelier signature'],
                    ['name' => 'Fold Jacket', 'price' => '940€', 'unit' => '/undyed wool', 'text' => 'Compact tailoring with a folded front, soft internal construction, and a clean sculpted shoulder.', 'features' => "- Natural charcoal or silver\n- Sizes 32–46\n- Edition of 48\n- Three-week delivery", 'file' => ['id' => $this->priceImg( 'jacket' ), 'type' => 'file'], 'url' => '/visit', 'button' => 'Request a fitting'],
                    ['name' => 'Column Dress', 'price' => '1.120€', 'unit' => '/silk crepe', 'text' => 'A full-length bias-cut dress with a high back, narrow straps, and a hem levelled after the cloth has settled.', 'features' => "- Ink, oxblood, or pearl\n- Sizes 32–46\n- Edition of 42\n- Three-week delivery", 'file' => ['id' => $this->priceImg( 'dress' ), 'type' => 'file'], 'url' => '/visit', 'button' => 'Request a fitting'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Fewer pieces, more ways to wear them',
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'detail' ), 'type' => 'file'],
                'position' => 'grid-start',
                'ratio' => '1-2',
                'text' => "No. 08 contains eleven garments. Each one was fitted with the others, so the coat clears the jacket shoulder, the long shirt sits beneath the dress, and every trouser works with both boots and a low shoe.\n\nThe point is not a prescribed wardrobe. It is a collection with enough agreement between pieces that getting dressed remains open.",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Finish the look',
                'cards' => [
                    ['title' => 'Frame Trouser', 'text' => "High rise, straight leg, and a deep single pleat in linen-wool canvas. 620 €.\n\n[Ask about availability](/visit)", 'file' => ['id' => $this->img( 'look-two' ), 'type' => 'file']],
                    ['title' => 'Night Shirt', 'text' => "A long silk shirt with a wrapped cuff and side vents cut to move over trousers. 540 €.\n\n[Ask about availability](/visit)", 'file' => ['id' => $this->img( 'look-one' ), 'type' => 'file']],
                    ['title' => 'Line Skirt', 'text' => "An ankle-length skirt with a split back panel and an internal grosgrain waist. 580 €.\n\n[Ask about availability](/visit)", 'file' => ['id' => $this->img( 'home' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Veyra sizing',
                'header' => 'row+col',
                'table' => [
                    ['Veyra', 'EU', 'Bust', 'Waist', 'Hip'],
                    ['32', '32', '76 cm', '60 cm', '84 cm'],
                    ['36', '36', '84 cm', '68 cm', '92 cm'],
                    ['40', '40', '92 cm', '76 cm', '100 cm'],
                    ['44', '44', '100 cm', '84 cm', '108 cm'],
                    ['46', '46', '104 cm', '88 cm', '112 cm'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Ordering the collection',
                'items' => [
                    ['title' => 'Can I order without visiting Berlin?', 'text' => 'Yes. We arrange a video fitting, send cloth and finish samples, and guide you through the measurements needed for the piece.'],
                    ['title' => 'Which alterations are included?', 'text' => 'Sleeve and trouser length, dress hem, waist placement, and minor balance adjustments are included in the listed price.'],
                    ['title' => 'What if my size is sold out?', 'text' => 'Contact the boutique. Reserved cloth occasionally allows one final piece, but editions are never repeated once the bolt is finished.'],
                    ['title' => 'May I return an altered garment?', 'text' => 'Unaltered pieces may be returned within fourteen days. Altered pieces receive a final approval fitting before completion and cannot be returned.'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the campaign lookbook page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addLookbook( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Lookbook',
            'title' => 'Veyra No. 08 Lookbook | After Dark',
            'path' => 'lookbook',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'After dark',
                'subtitle' => 'Lookbook No. 08',
                'text' => 'Berlin, 20:47–23:16. Photographed between the last office lights and the first train home.',
                'url' => '#looks',
                'button' => 'View the story',
                'url-alternative' => '/collection',
                'button-alternative' => 'Shop the collection',
                'files' => [
                    ['id' => $this->img( 'look-one' ), 'type' => 'file'],
                    ['id' => $this->img( 'home' ), 'type' => 'file'],
                    ['id' => $this->img( 'look-two' ), 'type' => 'file'],
                ],
            ]],
            ['id' => 'looks', 'type' => 'slideshow', 'group' => 'main', 'data' => [
                'title' => 'No. 08 in motion',
                'main' => true,
                'files' => [
                    ['id' => $this->slideImg( 'home' ), 'type' => 'file'],
                    ['id' => $this->slideImg( 'coat' ), 'type' => 'file'],
                    ['id' => $this->slideImg( 'look-one' ), 'type' => 'file'],
                    ['id' => $this->slideImg( 'dress' ), 'type' => 'file'],
                    ['id' => $this->slideImg( 'jacket' ), 'type' => 'file'],
                    ['id' => $this->slideImg( 'look-two' ), 'type' => 'file'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'studio' ), 'type' => 'file'],
                'position' => 'grid-end',
                'ratio' => '1-2',
                'text' => "## The city off duty\n\nWe photographed No. 08 on a cold evening without closing streets or building a set. The collection moved through the same doorways, platforms, and pools of light that shaped it in the studio.\n\nThe clothes are formal without waiting for an occasion. A coat crosses the city. Silk sits under wool. Tailoring loosens when the night does.",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Three key looks',
                'cards' => [
                    ['title' => 'Look 01 — The long line', 'text' => "Orbit Coat, Night Shirt, and Frame Trouser in ink.\n\n[View collection details](/collection)", 'file' => ['id' => $this->img( 'coat' ), 'type' => 'file']],
                    ['title' => 'Look 04 — Fold and fall', 'text' => "Fold Jacket in natural charcoal over the Column Dress in oxblood.\n\n[View collection details](/collection)", 'file' => ['id' => $this->img( 'jacket' ), 'type' => 'file']],
                    ['title' => 'Look 07 — Last light', 'text' => "Column Dress in pearl with the Line Skirt worn open as an evening layer.\n\n[View collection details](/collection)", 'file' => ['id' => $this->img( 'dress' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'Campaign credits',
                'items' => [
                    ['name' => 'Iris Hahn', 'role' => 'Photography', 'text' => 'We worked with the light that was already there. The clothes changed as the street changed, which felt more truthful than holding them in one perfect frame.'],
                    ['name' => 'Mara Vey', 'role' => 'Creative direction', 'text' => 'The collection began with movement after dark: a coat opening on a stair, silk under a station light, a shoulder caught in profile.'],
                    ['name' => 'Nao Berens', 'role' => 'Styling', 'text' => 'Nothing was added only for the photograph. Every layer had to make sense on the body for the rest of the night.'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the boutique visit and appointment page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addVisit( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Visit',
            'title' => 'Visit Veyra | Berlin Boutique and Private Fittings',
            'path' => 'visit',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Take time with the clothes',
                'subtitle' => 'Veyra Boutique — Berlin',
                'text' => 'Visit without an appointment during boutique hours, or reserve the fitting room for an unhurried look at the full collection.',
                'url' => '#appointment',
                'button' => 'Request an appointment',
                'url-alternative' => 'mailto:boutique@veyra.example',
                'button-alternative' => 'Email the boutique',
                'files' => [
                    ['id' => $this->img( 'rail' ), 'type' => 'file'],
                    ['id' => $this->img( 'fitting' ), 'type' => 'file'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Plan your visit',
                'cards' => [
                    ['title' => 'Boutique', 'text' => "Berlin-Mitte\nTuesday–Friday, 11:00–19:00\nSaturday, 10:00–18:00\nSunday–Monday, closed"],
                    ['title' => 'Private fitting', 'text' => 'A 60-minute appointment with the collection, cloth options, styling, and any alterations considered from the start.'],
                    ['title' => 'Remote appointment', 'text' => 'A video fitting for clients outside Berlin, followed by cloth samples and a clear written order summary.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'fitting' ), 'type' => 'file'],
                'position' => 'grid-start',
                'ratio' => '1-2',
                'text' => "## What happens at a fitting\n\nWe begin with the pieces you came to see, then adjust the selection once proportion and movement become clear. There is no expectation to build a complete look or decide in the room.\n\nIf a garment needs work, the atelier pins it with the shoes and layers you plan to wear. You receive a written record of the cloth, size, changes, price, and expected completion date before confirming.",
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Boutique questions',
                'items' => [
                    ['title' => 'Do I need an appointment?', 'text' => 'No. Walk-ins are welcome during boutique hours. An appointment reserves the main fitting room and time with an atelier fitter.'],
                    ['title' => 'How long do alterations take?', 'text' => 'Standard alterations take seven to ten days. More involved work is quoted after the first fitting.'],
                    ['title' => 'Can I bring a companion?', 'text' => 'Of course. The private fitting room comfortably seats two guests; mention larger parties when booking.'],
                    ['title' => 'Where do you deliver?', 'text' => 'Veyra ships across Europe, the United Kingdom, United States, Canada, Japan, South Korea, Australia, and New Zealand.'],
                ],
            ]],
            ['id' => 'appointment', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Request a private fitting',
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates an article lead element with the file reference used by previews.
     *
     * @param string $title Article title
     * @param string $text Article introduction
     * @param string $fileId Cover file ID
     * @return array<string, mixed> Article content element
     */
    protected function article( string $title, string $text, string $fileId ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'article', 'group' => 'main', 'files' => [$fileId], 'data' => [
            'title' => $title,
            'file' => ['id' => $fileId, 'type' => 'file'],
            'text' => $text,
        ]];
    }


    /**
     * Creates a closing collection call to action for a journal article.
     *
     * @param string $title Hero title
     * @param string $text Hero text
     * @return array<string, mixed> Hero content element
     */
    protected function articleHero( string $title, string $text ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
            'title' => $title,
            'subtitle' => 'Veyra — Collection No. 08',
            'text' => $text,
            'url' => '/collection',
            'button' => 'View the collection',
            'url-alternative' => '/journal',
            'button-alternative' => 'Back to the journal',
        ]];
    }


    /**
     * Creates the shared Veyra footer and returns its ID.
     *
     * @return string Element ID
     */
    protected function element() : string
    {
        if( !isset( $this->element ) )
        {
            $cards = [
                ['title' => 'Collection', 'text' => "- [Collection No. 08](/collection)\n- [After Dark lookbook](/lookbook)\n- [Book a fitting](/visit)"],
                ['title' => 'The house', 'text' => "- [Atelier](/atelier)\n- [Journal](/journal)\n- [Visit the boutique](/visit)"],
                ['title' => 'Client care', 'text' => "- [Sizing and alterations](/collection#pieces)\n- [Remote appointments](/visit)\n- [Delivery questions](/visit)"],
                ['title' => 'Contact', 'text' => "- [boutique@veyra.example](mailto:boutique@veyra.example)\n- [atelier@veyra.example](mailto:atelier@veyra.example)\n- +49 30 0000 0000\n- Berlin-Mitte"],
            ];

            $element = Element::forceCreate( [
                'lang' => 'en',
                'type' => 'cards',
                'name' => 'Veyra footer',
                'data' => ['type' => 'cards', 'data' => ['title' => 'Veyra', 'cards' => $cards]],
                'editor' => 'demo',
            ] );

            $version = $element->versions()->forceCreate( [
                'lang' => 'en',
                'data' => [
                    'lang' => 'en',
                    'type' => 'cards',
                    'name' => 'Veyra footer',
                    'data' => ['title' => 'Veyra', 'cards' => $cards],
                ],
                'published' => true,
                'editor' => 'demo',
            ] );

            $element->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $element->publish( $version );
            $this->element = (string) $element->refresh()->id;
        }

        return $this->element;
    }


    /**
     * Returns the ID of the primary Veyra image.
     *
     * @return string File ID
     */
    protected function file() : string
    {
        return $this->img( 'home' );
    }


    /**
     * Creates the Veyra home page and returns it.
     *
     * @param string $journalId Journal page ID referenced by listing elements
     * @return Page Home page
     */
    protected function home( string $journalId ) : Page
    {
        $elementId = $this->element();
        $fileId = $this->file();
        $logoId = $this->logoFile();

        $config = [
            'logo' => [
                'type' => 'logo',
                'files' => [$logoId],
                'data' => ['file' => ['id' => $logoId, 'type' => 'file']],
            ],
            'logo-alternative' => [
                'type' => 'logo-alternative',
                'files' => [$logoId],
                'data' => ['file' => ['id' => $logoId, 'type' => 'file']],
            ],
        ];

        $content = [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Dress for what follows',
                'subtitle' => 'Veyra — Collection No. 08',
                'text' => 'An independent Berlin label cutting limited editions in wool, silk, and linen. Strong lines, measured volume, and clothes made to move beyond one evening.',
                'url' => '/collection',
                'button' => 'View No. 08',
                'url-alternative' => '/lookbook',
                'button-alternative' => 'Open the lookbook',
                'files' => [
                    ['id' => $this->img( 'home' ), 'type' => 'file'],
                    ['id' => $this->img( 'coat' ), 'type' => 'file'],
                    ['id' => $this->img( 'dress' ), 'type' => 'file'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'The shape of No. 08',
                'cards' => [
                    ['title' => 'Orbit Coat', 'text' => "Double-faced wool, an off-centre close, and room through the back for the way a city moves—from early meetings to the last train home.\n\n[See the Orbit Coat](/collection#pieces)", 'file' => ['id' => $this->img( 'coat' ), 'type' => 'file']],
                    ['title' => 'Fold Jacket', 'text' => "Undyed wool shaped through one continuous front fold and a shoulder that stays clean without feeling rigid.\n\n[See the Fold Jacket](/collection#pieces)", 'file' => ['id' => $this->img( 'jacket' ), 'type' => 'file']],
                    ['title' => 'Column Dress', 'text' => "Weighted silk cut on the bias, allowed to settle, then levelled by hand for a line that returns after movement.\n\n[See the Column Dress](/collection#pieces)", 'file' => ['id' => $this->img( 'dress' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->portraitImg( 'atelier' ), 'type' => 'file'],
                'position' => 'grid-start',
                'ratio' => '1-2',
                'text' => "## Cut close to home\n\nVeyra develops every pattern in its Berlin atelier and produces in numbered runs with two specialist workrooms nearby. A style is released when its line, movement, and construction survive repeated fittings—not because the calendar asks for another delivery.\n\nThe result is a smaller collection with a clear purpose for every piece, enough cloth held for alterations, and a team that can repair what it made.\n\n[Enter the atelier](/atelier)",
            ]],
            ['id' => Utils::uid(), 'type' => 'slideshow', 'group' => 'main', 'data' => [
                'title' => 'After Dark — campaign frames',
                'main' => true,
                'files' => [
                    ['id' => $this->slideImg( 'look-one' ), 'type' => 'file'],
                    ['id' => $this->slideImg( 'home' ), 'type' => 'file'],
                    ['id' => $this->slideImg( 'look-two' ), 'type' => 'file'],
                    ['id' => $this->slideImg( 'dress' ), 'type' => 'file'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'Clothes in real wardrobes',
                'items' => [
                    ['name' => 'Elisa W.', 'role' => 'Curator, Basel', 'text' => 'The coat has presence without deciding the whole room for me. I wear it over tailoring, denim, and the dress I bought it for.'],
                    ['name' => 'Mei L.', 'role' => 'Designer, Amsterdam', 'text' => 'The trousers are exact through the waist and relaxed everywhere else. Nothing needs adjusting when I sit or walk.'],
                    ['name' => 'Frida S.', 'role' => 'Writer, Stockholm', 'text' => 'Veyra repaired a torn pocket and pressed the jacket back into shape. It returned looking familiar, not artificially new.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'blog', 'group' => 'main', 'data' => [
                'title' => 'From the cutting room',
                'layout' => 'cards',
                'limit' => 2,
                'order' => '_lft',
                'parent-page' => ['value' => $journalId, 'label' => 'Journal'],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Before choosing a piece',
                'items' => [
                    ['title' => 'Where are Veyra garments made?', 'text' => 'Patterns, samples, fittings, and finishing stay in the Berlin atelier. Small production runs are sewn by two specialist workrooms in Berlin and Brandenburg.'],
                    ['title' => 'Can a piece be altered?', 'text' => 'Yes. Standard length and balance alterations are included. More involved changes are discussed and priced before work begins.'],
                    ['title' => 'Are collections restocked?', 'text' => 'No full edition is repeated. If cloth remains after fulfilment and alterations, the atelier may cut a small final release.'],
                    ['title' => 'Do you repair older Veyra pieces?', 'text' => 'Yes. The atelier repairs and refreshes every Veyra garment, regardless of season or original place of purchase.'],
                ],
            ]],
            ['id' => 'home-contact', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Ask the boutique',
            ]],
            ['type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        $meta = [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => 'Veyra is an independent Berlin fashion label making limited-edition womenswear with exact tailoring, traceable European cloth, and personal fittings.',
                'keywords' => 'Veyra, Berlin fashion label, independent designer, womenswear, limited edition clothing, designer boutique',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => 'Veyra | Limited Editions, Cut in Berlin',
                'description' => 'Strong lines, measured volume, and limited-edition womenswear developed and fitted in the Veyra Berlin atelier.',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        $page = Page::forceCreate( [
            'lang' => 'en',
            'name' => 'Home',
            'title' => 'Veyra | Independent Berlin Fashion Label',
            'path' => '',
            'tag' => 'root',
            'theme' => $this->theme,
            'status' => 1,
            'cache' => 5,
            'editor' => 'demo',
            'config' => $config,
            'meta' => $meta,
            'content' => $content,
        ] );

        $version = $page->versions()->forceCreate( [
            'lang' => 'en',
            'data' => [
                'name' => 'Home',
                'title' => 'Veyra | Independent Berlin Fashion Label',
                'path' => '',
                'tag' => 'root',
                'domain' => '',
                'theme' => $this->theme,
                'status' => 1,
                'cache' => 5,
            ],
            'aux' => ['config' => $config, 'meta' => $meta, 'content' => $content],
            'published' => true,
            'editor' => 'demo',
        ] );

        $version->files()->attach( array_unique( array_merge( [$fileId], $this->ids( $config ), $this->ids( $content ), $this->ids( $meta ) ) ) );
        $version->elements()->attach( $elementId );
        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->publish( $version );

        return $page;
    }


    /**
     * Returns file IDs referenced anywhere in the given data.
     *
     * @param mixed $value Content or metadata
     * @return array<int, string> File IDs
     */
    protected function ids( mixed $value ) : array
    {
        $ids = [];

        if( is_array( $value ) )
        {
            if( ( $value['type'] ?? null ) === 'file' && is_string( $value['id'] ?? null )
                && !isset( $value['data'] ) && !isset( $value['group'] )
            ) {
                $ids[] = $value['id'];
            }

            foreach( $value as $item ) {
                $ids = array_merge( $ids, $this->ids( $item ) );
            }
        }

        return $ids;
    }


    /**
     * Returns the file ID for a curated demo photo.
     *
     * @param string $key Photo key from self::PHOTOS
     * @return string File ID
     */
    protected function img( string $key ) : string
    {
        [$photo, $name, $desc] = self::PHOTOS[$key];
        return $this->image( $photo, $name, $desc );
    }


    /**
     * Creates the Veyra SVG logo and returns its file ID.
     *
     * @return string File ID
     */
    protected function logoFile() : string
    {
        if( !isset( $this->logoFile ) )
        {
            $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 430 92" role="img" aria-labelledby="title desc">
  <title id="title">Veyra logo</title>
  <desc id="desc">Veyra wordmark with an open V monogram</desc>
  <g fill="none" fill-rule="evenodd">
    <path d="M12 16h15l19 53 19-53h15L53 80H39L12 16Z" fill="#FFFFFF"/>
    <path d="M27 16h13l6 18 6-18h13L46 62 27 16Z" fill="#ABADC7" opacity=".9"/>
    <text x="101" y="64" fill="#FFFFFF" font-family="ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace" font-size="55" font-weight="700" letter-spacing="8">VEYRA</text>
    <path d="M102 76h254" stroke="#ABADC7" stroke-width="2"/>
  </g>
</svg>
SVG;

            $disk = Storage::disk( config( 'cms.disk', 'public' ) );
            $path = rtrim( 'cms/' . $this->tenant, '/' ) . '/veyra-logo.svg';

            if( !$disk->put( $path, $svg ) ) {
                throw new \Aimeos\Cms\Exception( sprintf( 'Unable to store logo "%s"', $path ) );
            }

            $data = [
                'mime' => 'image/svg+xml',
                'lang' => 'en',
                'name' => 'Veyra logo',
                'path' => $path,
                'previews' => ['500' => $path],
                'description' => ['en' => 'Veyra wordmark with an open V monogram'],
            ];

            $file = File::forceCreate( $data + ['editor' => 'demo'] );
            $version = $file->versions()->forceCreate( [
                'lang' => 'en',
                'data' => $data,
                'published' => true,
                'editor' => 'demo',
            ] );

            $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $file->publish( $version );
            $this->logoFile = (string) $file->refresh()->id;
        }

        return $this->logoFile;
    }


    /**
     * Creates a Style demo page below the given parent and returns it.
     *
     * @param array<string, mixed> $data Page attributes
     * @param array<int, array<string, mixed>> $content Content elements
     * @param Page $parent Parent page
     * @param array<int, string> $fileIds Additional file IDs to attach
     * @param array<string, array<string, mixed>|object> $meta Meta entries keyed by type
     * @return Page Created page
     */
    protected function page( array $data, array $content, Page $parent, array $fileIds = [], array $meta = [] ) : Page
    {
        $elementId = $this->element();
        $contentIds = $this->ids( $content );
        $fileId = $contentIds[0] ?? $this->file();
        $description = self::DESCRIPTIONS[$data['path'] ?? ''] ?? $data['title'] ?? '';

        $meta = $data['meta'] ?? $meta ?: [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => $description,
                'keywords' => 'Veyra, Berlin fashion label, independent designer, womenswear, limited edition clothing',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => $data['title'] ?? '',
                'description' => $description,
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        $content[] = ['type' => 'reference', 'refid' => $elementId, 'group' => 'footer'];

        $page = Page::forceCreate( $data + [
            'theme' => $this->theme,
            'editor' => 'demo',
            'meta' => $meta,
            'content' => $content,
        ] );
        $page->appendToNode( $parent )->save();

        $version = $page->versions()->forceCreate( [
            'lang' => $data['lang'] ?? 'en',
            'data' => array_diff_key( $data, ['content' => 1, 'meta' => 1, 'id' => 1] ) + [
                'domain' => '',
                'theme' => $this->theme,
            ],
            'aux' => ['meta' => $meta, 'content' => $content],
            'published' => true,
            'editor' => 'demo',
        ] );

        $version->elements()->attach( $elementId );
        $version->files()->attach( array_unique( array_merge( [$fileId], $fileIds, $contentIds, $this->ids( $meta ) ) ) );

        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->publish( $version );

        return $page;
    }


    /**
     * Builds the Style fashion-label demo page tree.
     */
    protected function pages() : void
    {
        $journalId = (string) Str::uuid7();
        $home = $this->home( $journalId );

        $this->addCollection( $home )
            ->addLookbook( $home )
            ->addAtelier( $home )
            ->addBlog( $home, $journalId )
            ->addVisit( $home );
    }


    /**
     * Creates a fixed 2:3 portrait image and returns its file ID.
     *
     * @param string $key Photo key from self::PHOTOS
     * @return string File ID
     */
    protected function portraitImg( string $key ) : string
    {
        if( !isset( $this->portraitImages[$key] ) )
        {
            [$photo, $name, $desc] = self::PHOTOS[$key];
            $base = 'https://images.unsplash.com/' . $photo;
            $url = fn( int $w, int $h ) => $base . '?w=' . $w . '&h=' . $h . '&q=80&fm=jpg&fit=crop';

            $data = [
                'mime' => 'image/jpeg',
                'lang' => 'en',
                'name' => $name,
                'path' => $url( 1200, 1800 ),
                'previews' => ['500' => $url( 500, 750 ), '1000' => $url( 1000, 1500 )],
                'description' => ['en' => $desc],
            ];

            $file = File::forceCreate( $data + ['editor' => 'demo'] );
            $version = $file->versions()->forceCreate( [
                'lang' => 'en',
                'data' => $data,
                'published' => true,
                'editor' => 'demo',
            ] );

            $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $file->publish( $version );
            $this->portraitImages[$key] = (string) $file->refresh()->id;
        }

        return $this->portraitImages[$key];
    }


    /**
     * Creates a fixed 3:2 pricing image and returns its file ID.
     *
     * @param string $key Photo key from self::PHOTOS
     * @return string File ID
     */
    protected function priceImg( string $key ) : string
    {
        if( !isset( $this->pricingImages[$key] ) )
        {
            [$photo, $name, $desc] = self::PHOTOS[$key];
            $base = 'https://images.unsplash.com/' . $photo;
            $url = fn( int $w, int $h ) => $base . '?w=' . $w . '&h=' . $h . '&q=80&fm=jpg&fit=crop';

            $data = [
                'mime' => 'image/jpeg',
                'lang' => 'en',
                'name' => $name,
                'path' => $url( 1500, 1000 ),
                'previews' => ['500' => $url( 500, 333 ), '1000' => $url( 1000, 667 )],
                'description' => ['en' => $desc],
            ];

            $file = File::forceCreate( $data + ['editor' => 'demo'] );
            $version = $file->versions()->forceCreate( [
                'lang' => 'en',
                'data' => $data,
                'published' => true,
                'editor' => 'demo',
            ] );

            $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $file->publish( $version );
            $this->pricingImages[$key] = (string) $file->refresh()->id;
        }

        return $this->pricingImages[$key];
    }


    /**
     * Creates a fixed 2:1 slideshow image and returns its file ID.
     *
     * @param string $key Photo key from self::PHOTOS
     * @return string File ID
     */
    protected function slideImg( string $key ) : string
    {
        if( !isset( $this->slideImages[$key] ) )
        {
            [$photo, $name, $desc] = self::PHOTOS[$key];
            $base = 'https://images.unsplash.com/' . $photo;
            $url = fn( int $w, int $h ) => $base . '?w=' . $w . '&h=' . $h . '&q=80&fm=jpg&fit=crop';

            $data = [
                'mime' => 'image/jpeg',
                'lang' => 'en',
                'name' => $name,
                'path' => $url( 1500, 750 ),
                'previews' => ['500' => $url( 500, 250 ), '1000' => $url( 1000, 500 )],
                'description' => ['en' => $desc],
            ];

            $file = File::forceCreate( $data + ['editor' => 'demo'] );
            $version = $file->versions()->forceCreate( [
                'lang' => 'en',
                'data' => $data,
                'published' => true,
                'editor' => 'demo',
            ] );

            $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $file->publish( $version );
            $this->slideImages[$key] = (string) $file->refresh()->id;
        }

        return $this->slideImages[$key];
    }
}
