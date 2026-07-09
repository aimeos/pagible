<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Database\Seeders;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Utils;
use Illuminate\Support\Str;


/**
 * Glass theme demo content for a fictional analytics cloud product.
 */
class GlassDemo extends AbstractDemo
{
    /**
     * Curated Unsplash photos used across the Glass demo.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const PHOTOS = [
        'board' => ['photo-1551434678-e076c223a692', 'Planning session', 'Team reviewing analytics priorities on a glass wall'],
        'city' => ['photo-1494526585095-c41746248156', 'Market model', 'City grid at dusk representing market coverage'],
        'cloud' => ['photo-1451187580459-43490279c0fa', 'Cloud network', 'Global network of connected analytics systems'],
        'control' => ['photo-1504384308090-c894fdcc538d', 'Operations dashboard', 'Analytics dashboard with live business metrics'],
        'data' => ['photo-1551288049-bebda4e38f71', 'Metric workspace', 'Data workspace with dashboards and charts'],
        'finance' => ['photo-1460925895917-afdab827c52f', 'Revenue dashboard', 'Laptop showing revenue and performance charts'],
        'infra' => ['photo-1558494949-ef010cbdcc31', 'Cloud infrastructure', 'Server infrastructure for real-time analytics'],
        'meeting' => ['photo-1521737711867-e3b97375f902', 'Executive review', 'Business team reviewing quarterly metrics'],
        'pipeline' => ['photo-1516321318423-f06f85e504b3', 'Data pipeline', 'Modern workspace with connected screens'],
    ];

    private string $audioFile;
    private string $briefFile;
    private string $element;
    private string $videoFile;


    /**
     * Creates the blog section below the home page.
     *
     * @param Page $home Home page
     * @param string $blogId Blog page ID referenced by listing elements
     * @return static Same object for fluent calls
     */
    protected function addBlog( Page $home, string $blogId ) : static
    {
        $cover = $this->img( 'control' );

        $blog = $this->page( [
            'id' => $blogId,
            'lang' => 'en',
            'name' => 'Field Notes',
            'title' => 'Field Notes | SignalLake',
            'path' => 'blog',
            'tag' => 'blog',
            'type' => 'blog',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Field Notes',
                'subtitle' => 'Analytics practice',
                'text' => 'Short essays from the SignalLake team on metric design, data freshness, executive reporting, and the habits that keep reporting trusted.',
                'background' => ['id' => $this->img( 'data' ), 'type' => 'file'],
                'files' => [
                    ['id' => $cover, 'type' => 'file'],
                    ['id' => $this->img( 'meeting' ), 'type' => 'file'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'blog', 'group' => 'main', 'data' => [
                'title' => 'Latest articles',
                'layout' => 'list',
                'limit' => 4,
                'order' => '_lft',
                'parent-page' => ['value' => $blogId, 'label' => 'Field Notes'],
            ]],
        ], $home, [], [
            ['type' => 'meta-tags', 'data' => [
                'description' => 'Articles from SignalLake on metric governance, data freshness, analytics operations, and shared revenue reporting.',
                'keywords' => 'analytics cloud blog, metric governance, data freshness, revenue analytics',
            ]],
            ['type' => 'social-media', 'data' => [
                'title' => 'Field Notes | SignalLake',
                'description' => 'Practical writing for teams that depend on shared business metrics.',
                'file' => ['id' => $cover, 'type' => 'file'],
            ]],
        ] );

        $this->page( [
            'lang' => 'en',
            'name' => 'Why weekly dashboards lose trust',
            'title' => 'Why weekly dashboards lose trust | SignalLake',
            'path' => 'why-weekly-dashboards-lose-trust',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'Why weekly dashboards lose trust',
                "A dashboard can be technically correct and still fail the room.\n\nThe usual problem is not chart choice. It is timing, ownership, and a quiet drift between the number on the screen and the decision people are about to make.",
                $cover
            ),
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'meeting' ), 'type' => 'file'],
                'position' => 'end',
                'ratio' => '1-2',
                'text' => "### The report arrives after the question\n\nMost weekly packs describe a week that already happened. By the time managers read them, the pipeline has moved, campaigns have shifted, and support queues have changed shape.\n\nSignalLake keeps operating metrics close to the systems that create them, then marks each number with its source and freshness window.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Dashboard trust checks',
                'header' => 'row',
                'table' => [
                    ['Check', 'Poor practice', 'SignalLake practice'],
                    ['Freshness', 'Updated sometime this week', 'Timestamp and source shown beside each metric'],
                    ['Ownership', 'Nobody named', 'Metric owner and approval status recorded'],
                    ['Context', 'Standalone chart', 'Trend, cohort, and exception notes grouped together'],
                    ['Action', 'Read-only slide', 'Follow-up assigned from the metric detail'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Dashboards earn trust when people can answer three questions quickly: where did this number come from, when did it change, and who can explain it?\n\nSignalLake puts those answers in the same view as the metric. That keeps weekly reviews from turning into archaeology.",
            ]],
        ], $blog );

        $this->page( [
            'lang' => 'en',
            'name' => 'Metric ownership is a product decision',
            'title' => 'Metric ownership is a product decision | SignalLake',
            'path' => 'metric-ownership-is-a-product-decision',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'Metric ownership is a product decision',
                "A metric without an owner becomes a rumor with a chart.\n\nOwnership should be visible, reviewable, and boring. The best analytics teams treat definitions the way engineering teams treat interfaces: small, named, versioned, and hard to change by accident.",
                $this->img( 'board' )
            ),
            ['id' => Utils::uid(), 'type' => 'code', 'group' => 'main', 'data' => [
                'language' => ['value' => 'json'],
                'text' => "{\n  \"metric\": \"net_revenue_retention\",\n  \"owner\": \"Revenue Operations\",\n  \"source\": \"warehouse.finance.nrr_monthly\",\n  \"grain\": \"account_month\",\n  \"freshness\": \"24h\",\n  \"approved_by\": \"Finance\",\n  \"version\": \"2026-07-01\"\n}",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'What belongs in the metric record',
                'cards' => [
                    ['title' => 'A named owner', 'text' => 'The person or team that can approve a definition change and explain the result in plain language.', 'file' => ['id' => $this->img( 'board' ), 'type' => 'file']],
                    ['title' => 'A source contract', 'text' => 'The table, stream, or application event that feeds the metric, with expected update timing.', 'file' => ['id' => $this->img( 'pipeline' ), 'type' => 'file']],
                    ['title' => 'A change trail', 'text' => 'Every material definition change, visible beside the metric instead of buried in a ticket.', 'file' => ['id' => $this->img( 'data' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "SignalLake keeps metric definitions close to the data product, not hidden in a spreadsheet tab. Teams can still move quickly, but they move with a record.",
            ]],
        ], $blog );

        $this->page( [
            'lang' => 'en',
            'name' => 'Freshness is more than a timestamp',
            'title' => 'Freshness is more than a timestamp | SignalLake',
            'path' => 'freshness-is-more-than-a-timestamp',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'Freshness is more than a timestamp',
                "A timestamp tells you when a job finished. It does not tell you whether the number is safe to use.\n\nGood freshness design compares the data to the pace of the business process it describes.",
                $this->img( 'pipeline' )
            ),
            ['id' => Utils::uid(), 'type' => 'video', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->videoFile(), 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'audio', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->audioFile(), 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Freshness questions worth asking',
                'items' => [
                    ['title' => 'Can the source be late without failing?', 'text' => 'Batch jobs often finish cleanly after a source system missed its own export window. SignalLake tracks both source and transform timing.'],
                    ['title' => 'Does the metric age at the same speed for every team?', 'text' => 'A sales activity count may need hourly updates. Monthly recurring revenue may not. Freshness should match the decision cadence.'],
                    ['title' => 'Can readers see stale data before they act?', 'text' => 'SignalLake marks stale metrics in the dashboard and exposes the freshness rule behind the warning.'],
                ],
            ]],
        ], $blog );

        $this->page( [
            'lang' => 'en',
            'name' => 'One revenue number for finance and product',
            'title' => 'One revenue number for finance and product | SignalLake',
            'path' => 'one-revenue-number-for-finance-and-product',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'One revenue number for finance and product',
                "Finance and product teams often argue because they are both right.\n\nFinance needs controlled revenue recognition. Product needs behavioral context. The work is not to pick one number, but to connect the approved number to the product signals around it.",
                $this->img( 'finance' )
            ),
            ['id' => Utils::uid(), 'type' => 'slideshow', 'group' => 'main', 'data' => [
                'title' => 'Revenue views in context',
                'main' => true,
                'files' => [
                    ['id' => $this->img( 'finance' ), 'type' => 'file'],
                    ['id' => $this->img( 'control' ), 'type' => 'file'],
                    ['id' => $this->img( 'cloud' ), 'type' => 'file'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'file', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->briefFile(), 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'html', 'group' => 'main', 'data' => [
                'text' => '<aside style="padding:1.25rem;border:1px solid var(--pico-contrast-border);border-radius:var(--pico-border-radius);background:var(--pico-contrast-background)">SignalLake keeps the finance-approved number intact, then places product usage, account health, and cohort movement beside it.</aside>',
            ]],
        ], $blog );

        return $this;
    }


    /**
     * Creates the documentation page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addDocs( Page $home ) : static
    {
        $diagram = $this->img( 'infra' );

        $this->page( [
            'lang' => 'en',
            'name' => 'Docs',
            'title' => 'SignalLake Documentation',
            'path' => 'docs',
            'type' => 'docs',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'toc', 'group' => 'main', 'data' => [
                'title' => 'On this page',
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Start with a workspace',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "A SignalLake workspace contains sources, models, metrics, dashboards, alerts, and access rules for one business unit or tenant.\n\nCreate one workspace for each reporting boundary. Most companies start with Revenue, Product, and Customer Operations.",
            ]],
            ['id' => Utils::uid(), 'type' => 'code', 'group' => 'main', 'data' => [
                'language' => ['value' => 'bash'],
                'text' => "curl https://api.signallake.test/v1/workspaces \\\n  -H \"Authorization: Bearer sk_test\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"name\":\"Revenue\",\"region\":\"eu-central-1\"}'",
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Connect sources',
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $diagram, 'type' => 'file'],
                'position' => 'start',
                'ratio' => '1-2',
                'text' => "SignalLake reads from warehouses, application databases, event streams, spreadsheets, and billing systems.\n\nEach source has an owner, a refresh policy, and a failure rule. The platform records those details so dashboards can show whether a number is ready for use.",
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Define metrics',
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Metric fields',
                'header' => 'row',
                'table' => [
                    ['Field', 'Purpose', 'Example'],
                    ['name', 'Human-readable label', 'Net revenue retention'],
                    ['source', 'Warehouse table or stream', 'finance.nrr_monthly'],
                    ['grain', 'Lowest valid reporting level', 'account_month'],
                    ['freshness', 'Maximum accepted age', '24h'],
                    ['owner', 'Approver for definition changes', 'Revenue Operations'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'code', 'group' => 'main', 'data' => [
                'language' => ['value' => 'sql'],
                'text' => "select\n  account_id,\n  month,\n  recurring_revenue,\n  expansion_revenue,\n  contraction_revenue\nfrom warehouse.finance.account_revenue\nwhere month >= date_trunc('month', current_date) - interval '12 months';",
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Publish safely',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Publishing a dashboard does not copy data into a presentation layer. SignalLake keeps the dashboard connected to governed metrics, then applies permissions when a reader opens the page.\n\nUse scheduled reviews for board packs and live links for operating reviews.",
            ]],
            ['id' => Utils::uid(), 'type' => 'file', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->briefFile(), 'type' => 'file'],
            ]],
        ], $home, [$diagram], [
            ['type' => 'meta-tags', 'data' => [
                'description' => 'SignalLake documentation for workspaces, sources, metrics, dashboards, access rules, and publishing workflows.',
                'keywords' => 'SignalLake documentation, analytics API, metric definitions, cloud analytics docs',
            ]],
            ['type' => 'social-media', 'data' => [
                'title' => 'SignalLake Documentation',
                'description' => 'Set up a workspace, connect sources, define governed metrics, and publish dashboards.',
                'file' => ['id' => $diagram, 'type' => 'file'],
            ]],
        ] );

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
     * Creates the shared demo audio file and returns its ID.
     *
     * @return string File ID
     */
    protected function audioFile() : string
    {
        if( !isset( $this->audioFile ) )
        {
            $data = [
                'mime' => 'audio/mpeg',
                'lang' => 'en',
                'name' => 'SignalLake operator briefing',
                'path' => 'https://download.samplelib.com/mp3/sample-12s.mp3',
                'previews' => [],
                'description' => ['en' => 'Short audio briefing for analytics operations teams'],
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
            $this->audioFile = (string) $file->refresh()->id;
        }

        return $this->audioFile;
    }


    /**
     * Creates a downloadable demo PDF and returns its ID.
     *
     * @return string File ID
     */
    protected function briefFile() : string
    {
        if( !isset( $this->briefFile ) )
        {
            $data = [
                'mime' => 'application/pdf',
                'lang' => 'en',
                'name' => 'SignalLake metric contract template',
                'path' => 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf',
                'previews' => [],
                'description' => ['en' => 'Downloadable template for defining metric ownership, source, grain, and freshness'],
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
            $this->briefFile = (string) $file->refresh()->id;
        }

        return $this->briefFile;
    }


    /**
     * Creates the shared demo footer element and returns its ID.
     *
     * @return string Element ID
     */
    protected function element() : string
    {
        if( !isset( $this->element ) )
        {
            $cards = [
                ['title' => 'Product', 'text' => "Work with live metrics\n\n- [Platform](/)\n- [Pricing](/#pricing)\n- [Docs](/docs)"],
                ['title' => 'Resources', 'text' => "Build a better reporting habit\n\n- [Field Notes](/blog)\n- [Metric contracts](/docs)\n- [Revenue model guide](/one-revenue-number-for-finance-and-product)"],
                ['title' => 'Company', 'text' => "SignalLake Analytics Cloud\n\n- Security review\n- Data residency\n- hello@signallake.test"],
            ];

            $element = Element::forceCreate( [
                'lang' => 'en',
                'type' => 'cards',
                'name' => 'SignalLake footer',
                'data' => ['type' => 'cards', 'data' => ['cards' => $cards]],
                'editor' => 'demo',
            ] );

            $version = $element->versions()->forceCreate( [
                'lang' => 'en',
                'data' => [
                    'lang' => 'en',
                    'type' => 'cards',
                    'name' => 'SignalLake footer',
                    'data' => ['cards' => $cards],
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
     * Returns the ID of the primary shared demo image.
     *
     * @return string File ID
     */
    protected function file() : string
    {
        return $this->img( 'data' );
    }


    /**
     * Creates the home page and returns it.
     *
     * @param string $blogId Blog page ID referenced by listing elements
     * @return Page Home page
     */
    protected function home( string $blogId ) : Page
    {
        $elementId = $this->element();
        $fileId = $this->file();

        $content = [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'SignalLake Analytics Cloud',
                'subtitle' => 'Live metrics for operating teams',
                'text' => 'SignalLake gives revenue, product, and finance teams one governed place to read the numbers that shape the week.',
                'url' => '#pricing',
                'button' => 'View plans',
                'url-alternative' => '/docs',
                'button-alternative' => 'Read docs',
                'background' => ['id' => $this->img( 'cloud' ), 'type' => 'file'],
                'background-animation' => 'zoom',
                'files' => [
                    ['id' => $fileId, 'type' => 'file'],
                    ['id' => $this->img( 'control' ), 'type' => 'file'],
                    ['id' => $this->img( 'finance' ), 'type' => 'file'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'What SignalLake keeps in order',
                'cards' => [
                    ['title' => 'Metric contracts', 'text' => 'Every key number has an owner, source, grain, freshness rule, and approval state.', 'file' => ['id' => $this->img( 'board' ), 'type' => 'file']],
                    ['title' => 'Live operating views', 'text' => 'Dashboards stay connected to governed data instead of drifting into slide decks and spreadsheet copies.', 'file' => ['id' => $this->img( 'control' ), 'type' => 'file']],
                    ['title' => 'Private workspaces', 'text' => 'Separate tenants, roles, and retention settings for teams that should share context without sharing every row.', 'file' => ['id' => $this->img( 'infra' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'pipeline' ), 'type' => 'file'],
                'position' => 'start',
                'ratio' => '1-2',
                'text' => "## From source data to board-ready numbers\n\nSignalLake connects warehouses, billing tools, product events, and support systems. Each source is monitored for freshness and attached to the metrics that depend on it.\n\nWhen a number changes, readers can see the source, owner, and last approved definition without leaving the dashboard.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Operating signals covered',
                'header' => 'row',
                'table' => [
                    ['Area', 'Metrics', 'Typical owners'],
                    ['Revenue', 'ARR, expansion, churn, NRR, forecast coverage', 'Finance, RevOps'],
                    ['Product', 'Activation, retention, feature adoption, account health', 'Product, Growth'],
                    ['Customer', 'SLA, backlog, sentiment, escalation risk', 'Support, Success'],
                    ['Marketing', 'Pipeline source, campaign payback, cohort quality', 'Demand Gen, Finance'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'Used by teams that cannot afford a second version of the truth',
                'items' => [
                    ['name' => 'Marta Weiss', 'role' => 'VP Revenue Operations, Northbank', 'text' => 'SignalLake moved our weekly review away from reconciliation and back to decisions. The metric owner and source trail are visible before anyone asks.'],
                    ['name' => 'Eli Morgan', 'role' => 'Product Lead, HarborStack', 'text' => 'We finally have product usage sitting beside finance-approved revenue. The conversation changed immediately.'],
                    ['name' => 'Rina Okafor', 'role' => 'Head of Analytics, Modebridge', 'text' => 'The interface is calm, but the governance underneath is strict. That is exactly what our analysts needed.'],
                ],
            ]],
            ['id' => 'pricing', 'type' => 'pricing', 'group' => 'main', 'data' => [
                'title' => 'Plans for real operating teams',
                'text' => 'Start with governed dashboards. Add private workspaces, longer retention, and dedicated controls as the rollout grows.',
                'label' => 'Monthly',
                'label-alternative' => 'Annual',
                'items' => [
                    ['name' => 'Launch', 'price' => '$490', 'unit' => '/mo', 'price-alternative' => '$4,900', 'unit-alternative' => '/yr', 'text' => 'For one team moving from spreadsheets to governed dashboards.', 'features' => "- 3 workspaces\n- 20 governed metrics\n- Warehouse and billing connectors\n- 90-day history", 'url' => '/docs', 'button' => 'Start setup'],
                    ['name' => 'Operate', 'price' => '$1,200', 'unit' => '/mo', 'price-alternative' => '$12,000', 'unit-alternative' => '/yr', 'text' => 'For companies running weekly reviews from live data.', 'features' => "- 12 workspaces\n- Unlimited dashboards\n- Approval workflows\n- 24-month history\n- Priority support", 'url' => '/docs', 'button' => 'Plan rollout', 'highlight' => true, 'badge' => 'Most teams'],
                    ['name' => 'Control', 'price' => 'Custom', 'unit' => '', 'text' => 'For regulated teams with strict data residency and audit needs.', 'features' => "- Private region options\n- SSO and SCIM\n- Custom retention\n- Dedicated success review\n- Security questionnaire support", 'url' => '#contact', 'button' => 'Talk to sales'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'blog', 'group' => 'main', 'data' => [
                'title' => 'From Field Notes',
                'layout' => 'cards',
                'limit' => 3,
                'order' => '_lft',
                'parent-page' => ['value' => $blogId, 'label' => 'Field Notes'],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Questions teams ask before rollout',
                'items' => [
                    ['title' => 'Does SignalLake replace our warehouse?', 'text' => 'No. SignalLake sits above the systems you already trust. It governs definitions, dashboards, access, alerts, and review workflows.'],
                    ['title' => 'Can finance approve a metric before product uses it?', 'text' => 'Yes. Metric contracts include approval state, owner, source, grain, and version history. Dashboards can show draft and approved states separately.'],
                    ['title' => 'How does SignalLake handle tenant separation?', 'text' => 'Each workspace can use separate access rules, data retention, and region settings. Teams share definitions only when you decide to share them.'],
                    ['title' => 'Can we export reports for board meetings?', 'text' => 'Yes. Teams can publish live links or scheduled PDF snapshots while preserving the underlying source and freshness record.'],
                ],
            ]],
            ['id' => 'contact', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'See SignalLake with your own metrics',
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'footer', 'data' => ['level' => 2, 'title' => 'SignalLake']],
            ['type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        $meta = [
            ['type' => 'meta-tags', 'data' => [
                'description' => 'SignalLake Analytics Cloud gives revenue, product, and finance teams governed dashboards, metric ownership, and live operating reports.',
                'keywords' => 'analytics cloud, governed metrics, revenue dashboards, product analytics, business intelligence',
            ]],
            ['type' => 'social-media', 'data' => [
                'title' => 'SignalLake Analytics Cloud',
                'description' => 'Live, governed metrics for teams that run weekly operating reviews.',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ]],
        ];

        $page = Page::forceCreate( [
            'lang' => 'en',
            'name' => 'Home',
            'title' => 'SignalLake Analytics Cloud',
            'path' => '',
            'tag' => 'root',
            'theme' => $this->theme,
            'status' => 1,
            'cache' => 5,
            'editor' => 'demo',
            'meta' => $meta,
            'content' => $content,
        ] );

        $version = $page->versions()->forceCreate( [
            'lang' => 'en',
            'data' => [
                'name' => 'Home',
                'title' => 'SignalLake Analytics Cloud',
                'path' => '',
                'tag' => 'root',
                'domain' => '',
                'theme' => $this->theme,
                'status' => 1,
                'cache' => 5,
            ],
            'aux' => [
                'meta' => $meta,
                'content' => $content,
            ],
            'published' => true,
            'editor' => 'demo',
        ] );

        $version->files()->attach( array_unique( array_merge( [$fileId], $this->ids( $content ), $this->ids( $meta ) ) ) );
        $version->elements()->attach( $elementId );
        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->publish( $version );

        return $page;
    }


    /**
     * Returns file IDs referenced anywhere in the given data.
     *
     * @param mixed $value Content or meta data
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
     * Creates a demo page below the given parent and returns it.
     *
     * @param array<string, mixed> $data Page attributes
     * @param array<int, array<string, mixed>> $content Content elements
     * @param Page $parent Parent page to append to
     * @param array<int, string> $fileIds Additional file IDs to attach
     * @param array<int, array<string, mixed>> $meta Meta data blocks
     * @return Page Created page
     */
    protected function page( array $data, array $content, Page $parent, array $fileIds = [], array $meta = [] ) : Page
    {
        $elementId = $this->element();
        $fileId = $this->file();

        $meta = $data['meta'] ?? $meta ?: [
            ['type' => 'meta-tags', 'data' => [
                'description' => $data['title'] ?? '',
                'keywords' => 'SignalLake, analytics cloud, governed metrics, business intelligence',
            ]],
            ['type' => 'social-media', 'data' => [
                'title' => $data['title'] ?? '',
                'description' => $data['title'] ?? '',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ]],
        ];

        $content[] = ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'footer', 'data' => ['level' => 2, 'title' => 'SignalLake']];
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
        $version->files()->attach( array_unique( array_merge( [$fileId], $fileIds, $this->ids( $content ), $this->ids( $meta ) ) ) );

        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->publish( $version );

        return $page;
    }


    /**
     * Builds the Glass demo page tree.
     */
    protected function pages() : void
    {
        $blogId = (string) Str::uuid7();

        $home = $this->home( $blogId );

        $this->addDocs( $home )
            ->addBlog( $home, $blogId );
    }


    /**
     * Creates the shared demo video file and returns its ID.
     *
     * @return string File ID
     */
    protected function videoFile() : string
    {
        if( !isset( $this->videoFile ) )
        {
            $poster = 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=500&q=80&fm=jpg&fit=crop';

            $data = [
                'mime' => 'video/mp4',
                'lang' => 'en',
                'name' => 'SignalLake dashboard walkthrough',
                'path' => 'https://media.w3.org/2010/05/sintel/trailer.mp4',
                'previews' => ['500' => $poster],
                'description' => ['en' => 'Short walkthrough video for a live analytics dashboard'],
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
            $this->videoFile = (string) $file->refresh()->id;
        }

        return $this->videoFile;
    }
}
