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
 * Journal theme demo for the English language business publication Kontur.
 */
class JournalDemo extends AbstractDemo
{
    /** @var array<string, string> Meta descriptions keyed by page path */
    private const DESCRIPTIONS = [
        'economy' => 'Kontur explains industry, energy, technology, and independent business with careful reporting, clear writing, and none of the market noise.',
        'industry-without-a-blueprint' => 'How industrial companies are rebuilding production while energy costs, supply chains, and skills shortages apply pressure at the same time.',
        'who-owns-the-grid' => 'Networks, storage, and new power generation will shape the energy transition. Kontur follows the missing capital and the models that can work.',
        'money' => 'Analysis of investing, interest rates, retirement planning, and wealth for readers who want to understand decisions instead of chasing trends.',
        'the-portfolio-without-fads' => 'A resilient ETF portfolio needs fewer products, clear rules, and a time horizon that can withstand difficult months in the market.',
        'what-interest-rates-change' => 'What the new interest-rate environment means for savers, borrowers, and long-term investors—and which old habits have become expensive.',
        'property' => 'Kontur reports on housing, construction, renovation, and property markets through the lens of cost, regulation, and liveable cities.',
        'housing-returns-to-the-city' => 'Why mixed neighbourhoods, smaller homes, and converted buildings will shape housing more than another generation of large greenfield projects.',
        'renovating-in-the-right-order' => 'The sequence of energy upgrades that controls costs, limits risk, and improves a home reliably for decades.',
        'work' => 'Work, leadership, and culture beyond quick career advice: Kontur asks which structures genuinely make good work possible.',
        'productivity-needs-quiet' => 'Why focused work comes from protected time and clear responsibility—not another round of meetings, messages, and metrics.',
        'leadership-without-the-stage' => 'Good leadership is visible in decisions, preparation, and responsibility, not constant presence and grand performances.',
        'about-kontur' => 'Kontur is an independent business journal. Meet its editorial team and explore how the publication works.',
        'subscribe' => 'Read Kontur online, as a monthly print magazine, or through a member briefing with additional dossiers and conversations.',
    ];

    /**
     * Curated Unsplash photos used across the Kontur demo.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const PHOTOS = [
        'architecture' => ['photo-1486406146926-c627a92ad1ab', 'A changing city', 'Modern office architecture with a gridded glass facade'],
        'boardroom' => ['photo-1497366754035-f200968a6e72', 'Editorial conference', 'Bright office with a long table prepared for an editorial conference'],
        'city' => ['photo-1449824913935-59a10b8d2000', 'Metropolis in transition', 'Broad avenue running between dense towers in an international city'],
        'construction' => ['photo-1504307651254-35680f356dfd', 'Building renovation', 'Large building site during an extensive renovation'],
        'contract' => ['photo-1450101499163-c8848c66ca85', 'Financial planning', 'Documents, a pen, and a calculator arranged on a desk'],
        'desk' => ['photo-1497215728101-856f4ea42174', 'Focused work', 'Quiet workspace with desks and large windows'],
        'factory' => ['photo-1565793298595-6a879b1d9492', 'Industrial logistics', 'Lorries and loading bays within a large industrial supply chain'],
        'home' => ['photo-1560518883-ce09059eeffa', 'Home ownership', 'Modern residential building with a clean facade and front garden'],
        'market' => ['photo-1611974789855-9c2a0a7236a3', 'Capital markets', 'Digital market display showing price movements and financial figures'],
        'portrait' => ['photo-1556761175-b413da4baf72', 'Business conversation', 'Executives in conversation around a bright meeting table'],
        'savings' => ['photo-1579621970795-87facc2f976d', 'Long-term saving', 'Coins and a small plant representing long-term wealth building'],
        'team' => ['photo-1521737711867-e3b97375f902', 'Working together', 'Team holding a focused discussion around a wooden table'],
        'technology' => ['photo-1535378917042-10a22c95931a', 'Automation', 'Humanoid robot representing new automation technology'],
        'wind' => ['photo-1676749979869-81161c8824ee', 'Wind power', 'A row of white wind turbines across a wide green field'],
    ];

    private string $element;
    private string $logoFile;


    /**
     * Creates the publication page below the home page.
     */
    protected function addAbout( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'About us',
            'title' => 'About Kontur',
            'path' => 'about-kontur',
            'tag' => 'page',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Business needs context',
                'subtitle' => 'The editorial team',
                'text' => 'Kontur reports on companies, money, property, and work. We ask not only what happened, but who decided, who pays, and what each development changes in everyday life.',
                'files' => [['id' => $this->img( 'boardroom' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'How we work',
                'columns' => 3,
                'cards' => [
                    ['title' => 'On the ground', 'text' => 'We speak with the people responsible for factories, portfolios, building sites, and teams.'],
                    ['title' => 'With the numbers', 'text' => 'We test scale, time frames, and interests. A number without a comparison rarely explains much.'],
                    ['title' => 'For a second look', 'text' => 'We publish less, edit carefully, and update analysis when the facts change.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'team' ), 'type' => 'file'],
                'position' => 'end',
                'ratio' => '1-1',
                'text' => "## An editorial room built for disagreement\n\nKontur is produced in Hamburg, with correspondents in Berlin, Frankfurt, Munich, Brussels, and Zurich. Expertise sits beside scepticism in every conference. The result is more exact, not more dramatic.\n\nWriters disclose investments and potential conflicts of interest. Companies may check quotations, but they never preview our conclusions.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'The team',
                'header' => 'row+col',
                'table' => [
                    ['Desk', 'Editor', 'Base'],
                    ['Editor in chief', 'Elara Venn', 'Hamburg'],
                    ['Economy', 'Tomas Arden', 'Berlin'],
                    ['Money', 'Nia Calder', 'Frankfurt'],
                    ['Property', 'Mira Solven', 'Hamburg'],
                    ['Work', 'Ivo Maren', 'Munich'],
                ],
            ]],
            ['id' => 'contact', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Write to the editorial team',
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates an editorial section and its stories below the home page.
     *
     * @param Page $home Home page
     * @param string $id Section page ID
     * @param string $name Section name
     * @param string $path Section path
     * @param string $title Section title
     * @param string $intro Section introduction
     * @param string $photo Section photo key
     * @param array<int, array<string, mixed>> $stories Story definitions
     * @return static Same object for fluent calls
     */
    protected function addSection(
        Page $home,
        string $id,
        string $name,
        string $path,
        string $title,
        string $intro,
        string $photo,
        array $stories
    ) : static
    {
        $section = $this->page( [
            'id' => $id,
            'lang' => 'en',
            'name' => $name,
            'title' => $name . ' | Kontur',
            'path' => $path,
            'tag' => 'blog',
            'type' => 'blog',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => $title,
                'subtitle' => 'Kontur | ' . $name,
                'text' => $intro,
                'files' => [['id' => $this->img( $photo ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'blog', 'group' => 'main', 'data' => [
                'title' => 'Latest analysis',
                'layout' => 'default',
                'limit' => 6,
                'order' => '_lft',
                'parent-page' => ['value' => $id, 'label' => $name],
            ]],
        ], $home );

        foreach( $stories as $story )
        {
            $rows = [['Observation', 'Consequence']];

            foreach( $story['points'] as $point ) {
                $rows[] = $point;
            }

            $this->page( [
                'lang' => 'en',
                'name' => $story['name'],
                'title' => $story['title'],
                'path' => $story['path'],
                'tag' => 'article',
                'type' => 'blog',
                'status' => 1,
            ], [
                $this->article( $story['title'], $story['intro'], $this->img( $story['photo'] ) ),
                ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                    'file' => ['id' => $this->img( $story['second'] ), 'type' => 'file'],
                    'position' => 'end',
                    'ratio' => '1-1',
                    'text' => $story['body'],
                ]],
                ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                    'title' => 'What matters',
                    'header' => 'row+col',
                    'table' => $rows,
                ]],
                ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                    'text' => $story['close'],
                ]],
                $this->articleHero( $name, $path ),
            ], $section );
        }

        return $this;
    }


    /**
     * Creates the subscription page below the home page.
     */
    protected function addSubscribe( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Subscribe',
            'title' => 'Subscribe to Kontur',
            'path' => 'subscribe',
            'tag' => 'page',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'More context. Less noise.',
                'subtitle' => 'Kontur subscriptions',
                'text' => 'Read every analysis online, receive the monthly print edition, or add the editorial team’s Friday briefing.',
                'files' => [['id' => $this->img( 'contract' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
                'title' => 'Choose your edition',
                'text' => 'Cancel monthly. No advertising in the member area.',
                'items' => [
                    [
                        'name' => 'Digital',
                        'price' => '7€',
                        'unit' => '/month',
                        'text' => 'For readers who visit every day',
                        'features' => "- Every article and dossier\n- Reading list and audio editions\n- Kontur Morning",
                        'url' => 'mailto:subscriptions@kontur.example?subject=Digital%20subscription',
                        'button' => 'Read digitally',
                    ],
                    [
                        'name' => 'Magazine',
                        'price' => '12€',
                        'unit' => '/month',
                        'text' => 'The monthly print edition',
                        'features' => "- Magazine delivered to your door\n- Full digital access\n- Annual index",
                        'url' => 'mailto:subscriptions@kontur.example?subject=Magazine%20subscription',
                        'button' => 'Order the magazine',
                        'highlight' => true,
                        'badge' => 'Most popular',
                    ],
                    [
                        'name' => 'Briefing',
                        'price' => '18€',
                        'unit' => '/month',
                        'text' => 'For decision-makers with limited time',
                        'features' => "- Magazine and digital access\n- Friday briefing\n- Four editorial conversations each year",
                        'url' => 'mailto:subscriptions@kontur.example?subject=Briefing%20subscription',
                        'button' => 'Choose briefing',
                    ],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Subscription questions',
                'items' => [
                    ['title' => 'Can I cancel monthly?', 'text' => 'Yes. Access continues until the end of the month you have already paid for.'],
                    ['title' => 'Is there a trial?', 'text' => 'The digital edition can be tested free for four weeks. It ends automatically unless you choose to continue.'],
                    ['title' => 'When is the magazine published?', 'text' => 'The new edition appears on the third Thursday of each month and usually arrives on the next working day.'],
                    ['title' => 'Can I give a subscription as a gift?', 'text' => 'Yes. Gift subscriptions run for three, six, or twelve months and do not renew automatically.'],
                ],
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
     * Creates a closing call to action for an article.
     *
     * @param string $section Section name
     * @param string $path Section path
     * @return array<string, mixed> Hero content element
     */
    protected function articleHero( string $section, string $path ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
            'title' => 'Continue reading in ' . $section,
            'subtitle' => 'Kontur',
            'text' => 'Analysis, conversations, and numbers that reveal the wider context.',
            'url' => '/' . $path,
            'button' => 'Visit the section',
            'url-alternative' => '/subscribe',
            'button-alternative' => 'Subscribe to Kontur',
        ]];
    }


    /**
     * Creates the shared Kontur footer and returns its ID.
     *
     * @return string Element ID
     */
    protected function element() : string
    {
        if( !isset( $this->element ) )
        {
            $cards = [
                ['title' => 'Sections', 'text' => "- [Economy](/economy)\n- [Money](/money)\n- [Property](/property)\n- [Work](/work)"],
                ['title' => 'Kontur', 'text' => "- [About the editors](/about-kontur)\n- [Contact](/about-kontur#contact)\n- [Subscribe](/subscribe)"],
                ['title' => 'Briefings', 'text' => "- [Kontur Morning](/subscribe)\n- [Friday Briefing](/subscribe)\n- [Topic dossiers](/economy)"],
                ['title' => 'Editorial', 'text' => "- [editorial@kontur.example](mailto:editorial@kontur.example)\n- Hamburg · Berlin · Frankfurt"],
            ];

            $element = Element::forceCreate( [
                'lang' => 'en',
                'type' => 'cards',
                'name' => 'Kontur Footer',
                'data' => ['type' => 'cards', 'data' => ['cards' => $cards]],
                'editor' => 'demo',
            ] );

            $version = $element->versions()->forceCreate( [
                'lang' => 'en',
                'data' => [
                    'lang' => 'en',
                    'type' => 'cards',
                    'name' => 'Kontur Footer',
                    'data' => ['cards' => $cards],
                ],
                'editor' => 'demo',
            ] );

            $element->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $element->publish( $version );
            $this->element = (string) $element->refresh()->id;
        }

        return $this->element;
    }


    /**
     * Returns the ID of the primary Kontur image.
     *
     * @return string File ID
     */
    protected function file() : string
    {
        return $this->img( 'city' );
    }


    /**
     * Creates the Kontur home page and returns it.
     *
     * @param array<string, string> $sections Section IDs keyed by path
     * @return Page Home page
     */
    protected function home( array $sections ) : Page
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
                'title' => 'Business is changing. We show where it leads.',
                'subtitle' => 'Kontur | Edition 04.26',
                'text' => 'Reporting, analysis, and conversations about companies, money, and work—with time for the facts and attention to the consequences.',
                'url' => '/economy',
                'button' => 'Latest analysis',
                'url-alternative' => '/subscribe',
                'button-alternative' => 'Try an edition',
                'files' => [['id' => $fileId, 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'What matters today',
                'columns' => 2,
                'cards' => [
                    ['title' => 'Industry without a blueprint', 'text' => "Factories must become cleaner, more digital, and more independent at once. The rebuild starts while production continues.\n\n[Read the industrial analysis](/industry-without-a-blueprint)", 'file' => ['id' => $this->img( 'factory' ), 'type' => 'file']],
                    ['title' => 'Who owns the grid?', 'text' => "The energy transition needs networks, storage, and patience. The real conflict begins with the capital behind them.\n\n[Open the energy dossier](/who-owns-the-grid)", 'file' => ['id' => $this->img( 'wind' ), 'type' => 'file']],
                    ['title' => 'The portfolio without fads', 'text' => "A resilient portfolio is often unremarkable. That becomes its strength when markets turn unsettled.\n\n[Review the portfolio rules](/the-portfolio-without-fads)", 'file' => ['id' => $this->img( 'market' ), 'type' => 'file']],
                    ['title' => 'Housing returns to the city', 'text' => "Mixed neighbourhoods and converted offices are changing cities faster than new settlements at the edge.\n\n[Read the housing report](/housing-returns-to-the-city)", 'file' => ['id' => $this->img( 'architecture' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'technology' ), 'type' => 'file'],
                'position' => 'end',
                'ratio' => '1-1',
                'text' => "## The new industrial atlas\n\nWhere are batteries, chips, heat pumps, and data centres being built? Kontur tracks 180 investment projects to show which regions benefit—and where grids, land, or skilled workers are missing.\n\nThe interactive map connects announced billions with visible building progress. A groundbreaking ceremony is not a factory.\n\n[Explore the industrial atlas story](/industry-without-a-blueprint)",
            ]],
            ['id' => Utils::uid(), 'type' => 'blog', 'group' => 'main', 'data' => [
                'title' => 'The economy in context',
                'layout' => 'default',
                'limit' => 2,
                'order' => '_lft',
                'parent-page' => ['value' => $sections['economy'], 'label' => 'Economy'],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Money that fits your life',
                'columns' => 3,
                'cards' => [
                    ['title' => 'Interest rates change the rules', 'text' => "Cash, bonds, loans: the old logic of zero rates no longer applies.\n\n[See what interest rates change](/what-interest-rates-change)", 'file' => ['id' => $this->img( 'contract' ), 'type' => 'file']],
                    ['title' => 'Quiet portfolios outlast trends', 'text' => "Why consistent saving rules matter more than the next popular stock.\n\n[Reset the portfolio](/the-portfolio-without-fads)", 'file' => ['id' => $this->img( 'savings' ), 'type' => 'file']],
                    ['title' => 'Renovation begins with the plan', 'text' => "Measure first, plan second, build last: a sequence that keeps cost and comfort visible.\n\n[Read the renovation plan](/renovating-in-the-right-order)", 'file' => ['id' => $this->img( 'home' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'Voices from business',
                'items' => [
                    ['name' => 'Dr. Sera Vale', 'role' => 'Energy economist', 'text' => 'The difficult question is not whether to invest. It is who carries which risk across the next twenty years.'],
                    ['name' => 'Milo Kern', 'role' => 'Family business owner', 'text' => 'Transformation sounds like a project. In production, it is a series of practical decisions made every Monday morning.'],
                    ['name' => 'Talia Brenn', 'role' => 'Urban planner', 'text' => 'Housing arrives faster when we treat existing buildings as material rather than as obstacles.'],
                ],
            ]],
            ['type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        $meta = [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => 'Kontur is an independent business journal with reporting and analysis on companies, money, property, and work.',
                'keywords' => 'business journal, companies, investing, property, work, analysis',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => 'Kontur | Business in context',
                'description' => 'Reporting, analysis, and conversations about companies, money, and work—carefully researched and clearly written.',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        $page = Page::forceCreate( [
            'lang' => 'en',
            'name' => 'Home',
            'title' => 'Kontur | Business in context',
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
                'title' => 'Kontur | Business in context',
                'path' => '',
                'tag' => 'root',
                'domain' => '',
                'theme' => $this->theme,
                'status' => 1,
                'cache' => 5,
            ],
            'aux' => [
                'config' => $config,
                'meta' => $meta,
                'content' => $content,
            ],
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
        return $this->image( $photo, $name, $desc, 'en' );
    }


    /**
     * Creates the Kontur SVG logo and returns its file ID.
     *
     * @return string File ID
     */
    protected function logoFile() : string
    {
        if( !isset( $this->logoFile ) )
        {
            $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 520 96" role="img" aria-labelledby="title desc">
  <title id="title">Kontur logo</title>
  <desc id="desc">Kontur wordmark with a gold accent line</desc>
  <rect x="2" y="16" width="10" height="64" fill="#9A7112"/>
  <text x="30" y="68" fill="#1F1E1C" font-family="Georgia, 'Times New Roman', serif" font-size="64" font-weight="700" letter-spacing="-3">KONTUR</text>
  <path d="M32 80h354" stroke="#9A7112" stroke-width="3"/>
  <text x="400" y="79" fill="#65615B" font-family="Arial, Helvetica, sans-serif" font-size="10" font-weight="700" letter-spacing="2">JOURNAL</text>
</svg>
SVG;

            $disk = Storage::disk( config( 'cms.disk', 'public' ) );
            $path = rtrim( 'cms/' . $this->tenant, '/' ) . '/kontur-logo.svg';

            if( !$disk->put( $path, $svg ) ) {
                throw new \Aimeos\Cms\Exception( sprintf( 'Unable to store logo "%s"', $path ) );
            }

            $data = [
                'mime' => 'image/svg+xml',
                'lang' => 'en',
                'name' => 'Kontur logo',
                'path' => $path,
                'previews' => ['500' => $path],
                'description' => ['en' => 'Kontur wordmark with a gold accent line'],
            ];

            $file = File::forceCreate( $data + ['editor' => 'demo'] );
            $version = $file->versions()->forceCreate( [
                'lang' => 'en',
                'data' => $data,
                'editor' => 'demo',
            ] );

            $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $file->publish( $version );
            $this->logoFile = (string) $file->refresh()->id;
        }

        return $this->logoFile;
    }


    /**
     * Creates a Journal demo page below the given parent and returns it.
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
        $fileId = $this->file();
        $description = self::DESCRIPTIONS[$data['path'] ?? ''] ?? $data['title'] ?? '';

        $meta = $data['meta'] ?? $meta ?: [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => $description,
                'keywords' => 'Kontur, business journal, companies, investing, property, work',
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
            'editor' => 'demo',
        ] );

        $version->elements()->attach( $elementId );
        $version->files()->attach( array_unique( array_merge( [$fileId], $fileIds, $this->ids( $content ), $this->ids( $meta ) ) ) );

        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->publish( $version );

        return $page;
    }


    /**
     * Builds the Journal business publication demo page tree.
     */
    protected function pages() : void
    {
        $sections = [
            'economy' => (string) Str::uuid7(),
            'money' => (string) Str::uuid7(),
            'property' => (string) Str::uuid7(),
            'work' => (string) Str::uuid7(),
        ];
        $home = $this->home( $sections );

        $this->addSection(
            $home,
            $sections['economy'],
            'Economy',
            'economy',
            'Companies under pressure. Ideas in motion.',
            'We follow the reinvention of industry, new energy markets, and independent companies whose strength does not come from headlines.',
            'factory',
            [
                [
                    'name' => 'Industry without a blueprint',
                    'title' => 'Industry without a blueprint: Rebuilding while production runs',
                    'path' => 'industry-without-a-blueprint',
                    'photo' => 'factory',
                    'second' => 'technology',
                    'intro' => "Industry is expected to become climate-neutral, digital, and more independent—all at once. On the factory floor, that has produced no master plan, only a series of difficult decisions about machines, energy, and people.",
                    'body' => "## New technology meets old dependencies\n\nA production line runs for twenty years. Replacing one today means committing to energy prices, software, and suppliers that nobody can predict with confidence. Strong operators divide the transition into small, measurable steps.\n\nThey begin where consumption data is missing, waste heat is unused, or one component makes the entire line dependent on a single source.",
                    'points' => [
                        ['Energy is measured separately', 'Investment can follow actual consumption rather than estimates'],
                        ['Equipment remains modular', 'Technology can be replaced without shutting down the entire line'],
                        ['Employees test early', 'Problems appear before processes change at full scale'],
                    ],
                    'close' => 'Transformation is not a state reached after a project. It becomes the ability to change technology and operations repeatedly without losing quality or reliability.',
                ],
                [
                    'name' => 'Who owns the grid?',
                    'title' => 'Who owns the grid? The contest for networks and storage',
                    'path' => 'who-owns-the-grid',
                    'photo' => 'wind',
                    'second' => 'architecture',
                    'intro' => "Wind and solar farms increasingly produce inexpensive electricity. Yet the system between production and consumption still lacks lines, storage, and flexible tariffs. That gap will decide who earns from the energy transition.",
                    'body' => "## Infrastructure needs long commitments\n\nA battery facility can be completed in two years. A new transmission line may take a decade. Municipalities, network operators, industry, and investors therefore work to completely different clocks.\n\nNew models combine revenue from easing congestion, trading power, and providing reserve capacity. The essential step is to distribute risk openly before the first hour of shortage.",
                    'points' => [
                        ['Storage responds in seconds', 'It stabilises prices but cannot replace a resilient network'],
                        ['Networks become scarce locally', 'Location and access can matter more than generation capacity'],
                        ['Industry becomes flexible', 'Demand moves towards hours with abundant electricity'],
                    ],
                    'close' => 'The energy transition is not built only on fields and rooftops. Its economic core lies in the invisible connections between them.',
                ],
            ]
        )->addSection(
            $home,
            $sections['money'],
            'Money',
            'money',
            'Wealth needs rules, not predictions.',
            'We explain interest rates, markets, and long-term planning so decisions can endure even when tomorrow’s headline changes.',
            'market',
            [
                [
                    'name' => 'The portfolio without fads',
                    'title' => 'The portfolio without fads',
                    'path' => 'the-portfolio-without-fads',
                    'photo' => 'market',
                    'second' => 'savings',
                    'intro' => "A sound portfolio does not need every bet on the future. It needs a broad foundation, dependable costs, and rules for the moment prices fall and the plan suddenly feels outdated.",
                    'body' => "## The goal comes before the product\n\nSomeone buying a home in ten years needs a different allocation from someone with thirty years until retirement. The right mix starts with time, reserves, and tolerance for loss.\n\nOnly then come ETFs, bonds, or cash accounts. Products are tools. No ticker can repair an unclear decision.",
                    'points' => [
                        ['A global ETF forms the core', 'Regional bets remain small and deliberate'],
                        ['The cash reserve stays separate', 'A market fall does not force sales to cover current costs'],
                        ['The plan is reviewed once a year', 'Action follows a rule instead of a mood'],
                    ],
                    'close' => 'A resilient portfolio can feel dull in good years. Difficult years reveal why that is an advantage.',
                ],
                [
                    'name' => 'What interest rates change',
                    'title' => 'What interest rates really change',
                    'path' => 'what-interest-rates-change',
                    'photo' => 'contract',
                    'second' => 'home',
                    'intro' => "Interest has returned, but not equally for everyone. Savers can earn a return again, borrowing remains expensive, and many contracts react more slowly than central-bank rates.",
                    'body' => "## Safety has a visible price again\n\nEasy-access cash remains flexible, but its yield can fall quickly. Fixed deposits lock capital away. Bond prices move when market rates change. Looking only at the highest percentage often hides the term and the limits on access.\n\nLoans deserve the same attention: overpayments, fixed-rate periods, and the remaining balance matter more than a single comparison rate.",
                    'points' => [
                        ['Liquidity has value', 'Not every euro should be locked away for the highest rate'],
                        ['Long terms create calm', 'They can be costly when personal plans remain uncertain'],
                        ['Debt is repriced', 'Guaranteed repayment competes seriously with investing again'],
                    ],
                    'close' => 'The new rate environment does not demand a spectacular strategy. It rewards anyone who compares contracts, terms, and personal flexibility carefully.',
                ],
            ]
        )->addSection(
            $home,
            $sections['property'],
            'Property',
            'property',
            'Building is expensive. What already exists is becoming precious.',
            'Kontur examines how cities grow, buildings find new uses, and owners organise renovation without losing control of the cost.',
            'architecture',
            [
                [
                    'name' => 'Housing returns to the city',
                    'title' => 'Housing returns to the city',
                    'path' => 'housing-returns-to-the-city',
                    'photo' => 'city',
                    'second' => 'architecture',
                    'intro' => "New homes do not appear only on open land. Empty offices, car parks, and single-storey shops are becoming building sites within the city—when planners and owners can align.",
                    'body' => "## Conversion does not automatically save money\n\nExisting structures, services, and hazardous materials make projects complicated. Roads, schools, and public transport, however, are often already present. The equation improves when planners identify early what can remain and which use suits the building.\n\nSmaller homes, shared rooms, and mixed ground floors create more value than maximising floor area alone.",
                    'points' => [
                        ['Office depths are tested', 'Not every structural grid can provide well-lit homes'],
                        ['Ground floors remain flexible', 'Shops, clinics, and workplaces bring life to the neighbourhood'],
                        ['Parking is shared', 'More space remains available for homes and greenery'],
                    ],
                    'close' => 'The city of the future will rarely be entirely new. It will emerge from today’s gaps, extensions, and conversions.',
                ],
                [
                    'name' => 'Renovating in the right order',
                    'title' => 'Renovating in the right order: Understand first, then build',
                    'path' => 'renovating-in-the-right-order',
                    'photo' => 'home',
                    'second' => 'construction',
                    'intro' => "Windows, heating, roof, facade: considering everything at once quickly becomes overwhelming. A good renovation starts with the building and a sequence that later work will not undo.",
                    'body' => "## Measure before requesting a quote\n\nConsumption data, thermal images, and a building survey reveal where energy is actually lost. Only then is it possible to decide whether a smaller heating system will work, which components belong together, and when residents may need to move out temporarily.\n\nSubsidies belong in the financial plan, but they should not justify a measure with little technical value.",
                    'points' => [
                        ['The envelope comes before heating', 'Future heat demand determines the right capacity'],
                        ['Moisture is considered throughout', 'Airtight windows require a dependable ventilation plan'],
                        ['Stages follow one target', 'Every phase prepares the building for the next'],
                    ],
                    'close' => 'A renovation succeeds when cost, comfort, and technology still work together ten years later.',
                ],
            ]
        )->addSection(
            $home,
            $sections['work'],
            'Work',
            'work',
            'Good work is not produced at a permanent sprint.',
            'We examine leadership, concentration, and organisation without making individuals responsible for broken structures.',
            'team',
            [
                [
                    'name' => 'Productivity needs quiet',
                    'title' => 'Productivity needs quiet',
                    'path' => 'productivity-needs-quiet',
                    'photo' => 'desk',
                    'second' => 'team',
                    'intro' => "Many companies measure activity and hope for performance. Focused work begins only when calendars, responsibilities, and digital tools protect a task instead of interrupting it.",
                    'body' => "## Focus can be organised\n\nTwo meeting-free mornings do more than another time-management course. Clear decision paths stop every question from landing in five chats. Teams with dependable quiet time do not collaborate less—they arrive better prepared.\n\nThe important measure is not the number of messages sent, but the time required to reach a sound decision.",
                    'points' => [
                        ['Calendars share common rules', 'Focused time is not renegotiated with every request'],
                        ['Decisions have owners', 'Questions do not circle between groups without a mandate'],
                        ['Status is shared in writing', 'Meetings remain available for conflict, ideas, and real judgement'],
                    ],
                    'close' => 'Quiet is not a benefit reserved for a few. It is an operating condition for work that requires judgement, care, and responsibility.',
                ],
                [
                    'name' => 'Leadership without the stage',
                    'title' => 'Leadership without the stage',
                    'path' => 'leadership-without-the-stage',
                    'photo' => 'portrait',
                    'second' => 'boardroom',
                    'intro' => "Visibility can provide direction. Constant presence cannot replace a decision. Good leadership often appears where roles become clear, conflicts are addressed, and other people gain room to act.",
                    'body' => "## Clarity before charisma\n\nEmployees do not need a perfect personality at the top. They need understandable priorities, protection from contradictory instructions, and a leader who does not pass mistakes down the hierarchy.\n\nA good leader builds an organisation that can still make sensible decisions in their absence.",
                    'points' => [
                        ['Priorities are explained', 'Teams understand what can wait and why'],
                        ['Conflict is named early', 'Professional differences do not become personal uncertainty'],
                        ['Success is distributed', 'Responsibility and credit stay with the people doing the work'],
                    ],
                    'close' => 'Leadership without a stage is not invisible. It can be seen in the quality of decisions and the independence of the team.',
                ],
            ]
        )->addAbout( $home )
            ->addSubscribe( $home );
    }
}
