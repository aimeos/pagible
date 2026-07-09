<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Database\Seeders;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Utils;
use Illuminate\Support\Facades\Storage;
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
        'audit' => ['photo-1454165804606-c3d57bc86b40', 'Change audit', 'Workspace with charts and notes for reviewing metric changes'],
        'approval' => ['photo-1551836022-d5d88e9218df', 'Metric approval', 'Analytics owner reviewing an approved metric record'],
        'board' => ['photo-1551434678-e076c223a692', 'Planning session', 'Team reviewing analytics priorities on a glass wall'],
        'changes' => ['photo-1556075798-4825dfaaf498', 'Metric change history', 'Version history review for governed metric changes'],
        'city' => ['photo-1494526585095-c41746248156', 'Market model', 'City grid at dusk representing market coverage'],
        'cloud' => ['photo-1451187580459-43490279c0fa', 'Cloud network', 'Global network of connected analytics systems'],
        'control' => ['photo-1504384308090-c894fdcc538d', 'Operations dashboard', 'Analytics dashboard with live business metrics'],
        'contract' => ['photo-1450101499163-c8848c66ca85', 'Metric contract desk', 'Business documents and charts used to define governed metrics'],
        'data' => ['photo-1551288049-bebda4e38f71', 'Metric workspace', 'Data workspace with dashboards and charts'],
        'finance' => ['photo-1460925895917-afdab827c52f', 'Revenue dashboard', 'Laptop showing revenue and performance charts'],
        'infra' => ['photo-1558494949-ef010cbdcc31', 'Cloud infrastructure', 'Server infrastructure for real-time analytics'],
        'meeting' => ['photo-1521737711867-e3b97375f902', 'Executive review', 'Business team reviewing quarterly metrics'],
        'owner' => ['photo-1552664730-d307ca884978', 'Metric owner review', 'Team reviewing ownership decisions in a planning session'],
        'pipeline' => ['photo-1516321318423-f06f85e504b3', 'Data pipeline', 'Modern workspace with connected screens'],
        'security' => ['photo-1563986768609-322da13575f3', 'Access control review', 'Security controls for private analytics workspaces'],
    ];

    private string $briefFile;
    private string $element;
    private string $logoFile;
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
            'title' => 'Field Notes',
            'path' => 'blog',
            'tag' => 'blog',
            'type' => 'blog',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Field Notes',
                'subtitle' => 'Analytics practice',
                'text' => 'Short essays from the SignalLake team on metric design, data freshness, executive reporting, and the habits that keep reporting trusted.',
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
                'title' => 'Field Notes',
                'description' => 'Practical writing for teams that depend on shared business metrics.',
                'file' => ['id' => $cover, 'type' => 'file'],
            ]],
        ] );

        $this->page( [
            'lang' => 'en',
            'name' => 'Why weekly dashboards lose trust',
            'title' => 'Why weekly dashboards lose trust',
            'path' => 'why-weekly-dashboards-lose-trust',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'Why weekly dashboards lose trust',
                "A dashboard can be technically correct and still fail the room.\n\nThe usual problem is not chart choice. It is timing, ownership, and a quiet drift between the number on the screen and the decision people are about to make.\n\nTeams notice the drift first in small ways: the sales forecast has a different cutoff than the pipeline review, product activation is pulled from a stale export, and finance has to correct the recurring revenue number before anyone trusts the rest of the deck.",
                $cover
            ),
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'meeting' ), 'type' => 'file'],
                'position' => 'end',
                'ratio' => '1-2',
                'text' => "### The report arrives after the question\n\nMost weekly packs describe a week that already happened. By the time managers read them, the pipeline has moved, campaigns have shifted, and support queues have changed shape.\n\nA trusted review needs a tighter loop. The dashboard should show which source changed, which metric version was used, and whether the value is inside the freshness rule agreed by the team.\n\nSignalLake keeps operating metrics close to the systems that create them, then marks each number with its source and freshness window.",
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
                'text' => "Dashboards earn trust when people can answer three questions quickly: where did this number come from, when did it change, and who can explain it?\n\nA small review ritual helps: start with stale or disputed metrics, confirm the owner, record the decision, and only then move to trend discussion. SignalLake puts those answers in the same view as the metric. That keeps weekly reviews from turning into archaeology.",
            ]],
            $this->articleHero(
                'Make the next review easier to trust',
                'See how SignalLake keeps metric sources, freshness, and ownership visible beside every dashboard.'
            ),
        ], $blog );

        $this->page( [
            'lang' => 'en',
            'name' => 'Metric ownership is a product decision',
            'title' => 'Metric ownership is a product decision',
            'path' => 'metric-ownership-is-a-product-decision',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'Metric ownership is a product decision',
                "A metric without an owner becomes a rumor with a chart.\n\nOwnership should be visible, reviewable, and boring. The best analytics teams treat definitions the way engineering teams treat interfaces: small, named, versioned, and hard to change by accident.\n\nThis is product work because every metric creates a promise to its readers. If the name, grain, or source changes without notice, the product has broken that promise even when the SQL still runs.",
                $this->img( 'board' )
            ),
            ['id' => Utils::uid(), 'type' => 'code', 'group' => 'main', 'data' => [
                'language' => ['value' => 'json'],
                'text' => "{\n  \"metric\": \"net_revenue_retention\",\n  \"owner\": \"Revenue Operations\",\n  \"source\": \"warehouse.finance.nrr_monthly\",\n  \"grain\": \"account_month\",\n  \"freshness\": \"24h\",\n  \"approved_by\": \"Finance\",\n  \"version\": \"2026-07-01\",\n  \"change_policy\": \"approval_required\",\n  \"review_cycle\": \"monthly\"\n}",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'What belongs in the metric record',
                'cards' => [
                    ['title' => 'A named owner', 'text' => 'The person or team that can approve a definition change and explain the result in plain language.', 'file' => ['id' => $this->img( 'approval' ), 'type' => 'file']],
                    ['title' => 'A source contract', 'text' => 'The table, stream, or application event that feeds the metric, with expected update timing.', 'file' => ['id' => $this->img( 'pipeline' ), 'type' => 'file']],
                    ['title' => 'A change trail', 'text' => 'Every material definition change, visible beside the metric instead of buried in a ticket.', 'file' => ['id' => $this->img( 'changes' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Ownership rhythm',
                'header' => 'row',
                'table' => [
                    ['Moment', 'Owner action', 'Reader benefit'],
                    ['New metric', 'Approve the first definition and source', 'Readers know the metric is intentional'],
                    ['Source change', 'Confirm grain and cutoff still match the promise', 'Trend breaks are explained before the review'],
                    ['Policy review', 'Retire unused variants and aliases', 'Teams stop choosing between near-duplicate numbers'],
                    ['Incident', 'Add a dated note to affected dashboards', 'Executives see why a number should be treated carefully'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "SignalLake keeps metric definitions close to the data product, not hidden in a spreadsheet tab. Teams can still move quickly, but they move with a record.\n\nThe goal is not to slow analysts down. It is to make ownership obvious enough that a reader can trust the number without opening a ticket or asking which warehouse model happened to feed the latest screenshot.",
            ]],
            $this->articleHero(
                'Give every important metric an owner',
                'Use the SignalLake metric contract to record the definition, source, review cycle, and approval trail.'
            ),
        ], $blog );

        $this->page( [
            'lang' => 'en',
            'name' => 'Freshness is more than a timestamp',
            'title' => 'Freshness is more than a timestamp',
            'path' => 'freshness-is-more-than-a-timestamp',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'Freshness is more than a timestamp',
                "A timestamp tells you when a job finished. It does not tell you whether the number is safe to use.\n\nGood freshness design compares the data to the pace of the business process it describes. Hourly lead routing and monthly recurring revenue should not share the same stale-data rule.\n\nSignalLake treats freshness as a contract between the metric owner and the reader: how late the source may be, what warning should appear, and when the metric should be held back from a published review.",
                $this->img( 'pipeline' )
            ),
            ['id' => Utils::uid(), 'type' => 'video', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->videoFile(), 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Freshness tiers',
                'header' => 'row',
                'table' => [
                    ['Metric class', 'Typical window', 'What happens when stale'],
                    ['Routing and support queues', '15 minutes', 'Show warning and notify the operations owner'],
                    ['Pipeline and campaign spend', '1 hour', 'Keep visible, but mark decisions that depend on it'],
                    ['Revenue recognition', '24 hours', 'Hold published board views until finance confirms the load'],
                    ['Monthly reporting', '3 days', 'Show close status and expected finalization date'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "The useful question is not whether the data loaded. It is whether the data is current enough for the decision in front of the team. A good dashboard makes that answer visible before the conversation starts.",
            ]],
            $this->articleHero(
                'Set freshness rules around real decisions',
                'See how SignalLake turns source timing, warnings, and publication holds into a clear operating contract.'
            ),
        ], $blog );

        $this->page( [
            'lang' => 'en',
            'name' => 'One revenue number for all',
            'title' => 'One revenue number for all',
            'path' => 'one-revenue-number-for-finance-and-product',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'One revenue number for all',
                "Finance and product teams often argue because they are both right.\n\nFinance needs controlled revenue recognition. Product needs behavioral context. The work is not to pick one number, but to connect the approved number to the product signals around it.\n\nA revenue review becomes useful when the approved number stays intact and the surrounding product signals explain what changed: activation, expansion, usage depth, support risk, and account health.",
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
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Revenue context map',
                'header' => 'row',
                'table' => [
                    ['Finance view', 'Product context', 'Shared question'],
                    ['Net revenue retention', 'Feature adoption by expanding accounts', 'Is expansion tied to product depth or contract timing?'],
                    ['Churn', 'Last active date and unresolved support risk', 'Did the account disengage before the renewal conversation?'],
                    ['Forecast coverage', 'Trial activation and sales-assist usage', 'Which forecast gaps have product evidence behind them?'],
                    ['Discounting', 'Seat utilization and plan fit', 'Are discounts masking accounts that need a different package?'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "The important boundary is clear: finance owns the recognized number, while product and customer teams explain movement around it. SignalLake keeps those views linked without letting one overwrite the other.",
            ]],
            $this->articleHero(
                'Connect revenue signals',
                'Build a governed revenue view that finance can approve and product teams can explain.'
            ),
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

        $docs = $this->page( [
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
                'text' => "A SignalLake workspace contains sources, models, metrics, dashboards, alerts, and access rules for one business unit or tenant.\n\nCreate one workspace for each reporting boundary. Most companies start with Revenue, Product, and Customer Operations. Keep the boundary close to how decisions are made: if two teams approve metrics separately, give them separate workspaces and share only the metrics they both need.\n\nUse short workspace keys because they appear in API paths, export names, and audit logs.",
            ]],
            ['id' => Utils::uid(), 'type' => 'code', 'group' => 'main', 'data' => [
                'language' => ['value' => 'bash'],
                'text' => "curl https://api.signallake.test/v1/workspaces \\\n  -H \"Authorization: Bearer sk_test\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"key\":\"revenue\",\"name\":\"Revenue\",\"region\":\"eu-central-1\",\"timezone\":\"Europe/Berlin\"}'",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Workspace settings',
                'header' => 'row',
                'table' => [
                    ['Setting', 'Recommended start', 'Reason'],
                    ['key', 'A short team name', 'Keeps API paths and audit entries readable'],
                    ['region', 'Closest regulated region', 'Keeps data residency decisions explicit'],
                    ['timezone', 'Review team timezone', 'Aligns freshness and scheduled exports'],
                    ['retention', '90 days for launch', 'Limits early storage while definitions settle'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Examples',
                'cards' => [
                    ['title' => 'Metric contract example', 'text' => "Define ownership, source, grain, freshness, approval, and review policy in one record. Use this when a number is reused across dashboards or reported outside the team.\n\n[Open metric contract example](/docs/metric-contract-example)", 'file' => ['id' => $this->img( 'contract' ), 'type' => 'file']],
                    ['title' => 'Dashboard publish example', 'text' => "Publish a governed dashboard for a review cycle without copying the underlying data. Use this when a live page should stay stable for a named audience.\n\n[Open dashboard publish example](/docs/dashboard-publish-example)", 'file' => ['id' => $this->img( 'data' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Connect sources',
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $diagram, 'type' => 'file'],
                'position' => 'start',
                'ratio' => '1-2',
                'text' => "SignalLake reads from warehouses, application databases, event streams, spreadsheets, and billing systems.\n\nEach source has an owner, a refresh policy, and a failure rule. The platform records those details so dashboards can show whether a number is ready for use.\n\nFor a first rollout, connect the system of record before enrichment sources. A revenue workspace usually starts with billing, CRM, and warehouse tables, then adds product events once the approved revenue number is stable.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Source policy examples',
                'header' => 'row',
                'table' => [
                    ['Source', 'Refresh policy', 'Failure rule'],
                    ['Billing export', 'Every 4 hours', 'Hold finance dashboards if older than 24 hours'],
                    ['CRM opportunity stream', 'Every 15 minutes', 'Warn pipeline dashboards after 1 hour'],
                    ['Product events', 'Hourly warehouse rollup', 'Show cohort views with last complete hour'],
                    ['Spreadsheet upload', 'Manual with owner approval', 'Mark draft until reviewed'],
                ],
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
                    ['policy', 'Change rule for the metric', 'Finance approval required'],
                    ['audience', 'Where the metric may be published', 'Executive review, board packet'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'code', 'group' => 'main', 'data' => [
                'language' => ['value' => 'sql'],
                'text' => "select\n  account_id,\n  month,\n  recurring_revenue,\n  expansion_revenue,\n  contraction_revenue,\n  churned_revenue\nfrom warehouse.finance.account_revenue\nwhere month >= date_trunc('month', current_date) - interval '12 months'\n  and recognition_status = 'approved';",
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Publish safely',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Publishing a dashboard does not copy data into a presentation layer. SignalLake keeps the dashboard connected to governed metrics, then applies permissions when a reader opens the page.\n\nUse scheduled reviews for board packs and live links for operating reviews. Before publishing, check that every visible metric has an owner, an approved source, a freshness policy, and a reader-facing stale-state message.",
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

        $this->page( [
            'lang' => 'en',
            'name' => 'Metric contract example',
            'title' => 'Metric Contract Example | SignalLake',
            'path' => 'docs/metric-contract-example',
            'type' => 'docs',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'toc', 'group' => 'main', 'data' => [
                'title' => 'On this page',
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Example contract',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "A metric contract keeps the business definition, source, owner, and approval state together. Use this shape for numbers that appear in operating reviews or board reporting.\n\nStart with the metric key and owner, then add the smallest amount of policy needed to make the number durable: source, grain, freshness, approval, and change rules. More detail can be added later, but those fields should be present before a dashboard depends on the metric.",
            ]],
            ['id' => Utils::uid(), 'type' => 'code', 'group' => 'main', 'data' => [
                'language' => ['value' => 'json'],
                'text' => "{\n  \"key\": \"net_revenue_retention\",\n  \"label\": \"Net revenue retention\",\n  \"description\": \"Recurring revenue retained from existing accounts after expansion, contraction, and churn.\",\n  \"owner\": \"Revenue Operations\",\n  \"source\": \"warehouse.finance.account_revenue\",\n  \"grain\": \"account_month\",\n  \"freshness\": \"24h\",\n  \"format\": \"percent\",\n  \"direction\": \"higher_is_better\",\n  \"approval\": {\n    \"state\": \"approved\",\n    \"by\": \"Finance\",\n    \"since\": \"2026-07-01\"\n  },\n  \"policy\": {\n    \"change\": \"finance_approval_required\",\n    \"review\": \"monthly\",\n    \"publish_to\": [\"executive-review\", \"board-packet\"]\n  }\n}",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Contract checklist',
                'header' => 'row',
                'table' => [
                    ['Field', 'Why it matters', 'Owner'],
                    ['source', 'Tells readers where the number is calculated', 'Analytics Engineering'],
                    ['grain', 'Prevents accidental comparison across incompatible levels', 'Metric Owner'],
                    ['freshness', 'Shows whether the number is ready for this decision', 'Data Operations'],
                    ['approval', 'Separates draft work from finance-approved reporting', 'Finance'],
                    ['policy', 'Controls where the metric can be published', 'Workspace Admin'],
                    ['description', 'Explains the number without requiring SQL knowledge', 'Metric Owner'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'contract' ), 'type' => 'file'],
                'position' => 'end',
                'ratio' => '1-2',
                'text' => "### Review before publishing\n\nBefore a contract becomes active, SignalLake checks that the owner, source, grain, and freshness policy are present. The approval state then follows the metric into every dashboard that uses it.\n\nIf the contract changes later, readers see the active version beside the metric. That makes definition updates visible without turning every dashboard into a change log.",
            ]],
        ], $docs, [], [
            ['type' => 'meta-tags', 'data' => [
                'description' => 'A concrete SignalLake metric contract example covering owner, source, grain, freshness, and approval state.',
                'keywords' => 'metric contract example, analytics governance, SignalLake docs',
            ]],
            ['type' => 'social-media', 'data' => [
                'title' => 'Metric Contract Example | SignalLake',
                'description' => 'Use one record to keep a metric definition, owner, source, grain, freshness, and approval state together.',
                'file' => ['id' => $this->img( 'contract' ), 'type' => 'file'],
            ]],
        ] );

        $this->page( [
            'lang' => 'en',
            'name' => 'Dashboard publish example',
            'title' => 'Dashboard Publish Example | SignalLake',
            'path' => 'docs/dashboard-publish-example',
            'type' => 'docs',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'toc', 'group' => 'main', 'data' => [
                'title' => 'On this page',
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Example publish flow',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Publishing gives a review group a stable dashboard URL while the metrics remain connected to their governed sources. Readers see the same approved definitions without receiving a copied data extract.\n\nA publish action records the target audience, the metric set, the refresh rule, and the stale-data behavior. Editors can keep working on draft changes while the published view remains tied to the approved version.",
            ]],
            ['id' => Utils::uid(), 'type' => 'code', 'group' => 'main', 'data' => [
                'language' => ['value' => 'bash'],
                'text' => "curl https://api.signallake.test/v1/dashboards/revenue-weekly/publish \\\n  -H \"Authorization: Bearer sk_test\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"audience\":\"executive-review\",\"metric_set\":\"revenue-v3\",\"refresh\":\"hourly\",\"stale\":\"warn\",\"exports\":\"disabled\"}'",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Publish decisions',
                'header' => 'row',
                'table' => [
                    ['Decision', 'Example', 'Effect'],
                    ['audience', 'executive-review', 'Applies the right access policy'],
                    ['metric_set', 'revenue-v3', 'Pins the approved metric definitions'],
                    ['refresh', 'hourly', 'Sets the dashboard freshness expectation'],
                    ['export', 'disabled', 'Keeps the review linked to live governed data'],
                    ['stale', 'warn', 'Shows stale metrics without hiding the full review page'],
                    ['lock', 'metric definitions', 'Lets editors revise layout without changing approved numbers'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'control' ), 'type' => 'file'],
                'position' => 'start',
                'ratio' => '1-2',
                'text' => "### Publish the view, not a copy\n\nA published dashboard still reads from the approved metric set. If a source becomes stale or a definition changes, SignalLake marks the issue on the review page instead of hiding it in an exported deck.\n\nThat distinction matters during weekly reviews. The audience gets a stable page, while editors keep the ability to prepare the next review cycle without overwriting the current one.",
            ]],
        ], $docs, [], [
            ['type' => 'meta-tags', 'data' => [
                'description' => 'A SignalLake dashboard publishing example for sharing governed metrics with an executive review audience.',
                'keywords' => 'dashboard publishing example, governed dashboard, SignalLake docs',
            ]],
            ['type' => 'social-media', 'data' => [
                'title' => 'Dashboard Publish Example | SignalLake',
                'description' => 'Publish a stable review dashboard while keeping metrics connected to governed live data.',
                'file' => ['id' => $this->img( 'control' ), 'type' => 'file'],
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
     * Creates the closing call-to-action hero for an article.
     *
     * @param string $title Hero title
     * @param string $text Hero text
     * @return array<string, mixed> Hero content element
     */
    protected function articleHero( string $title, string $text ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
            'title' => $title,
            'subtitle' => 'SignalLake in practice',
            'text' => $text,
            'url' => '/docs',
            'button' => 'Explore the documentation',
        ]];
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
                ['title' => 'Product', 'text' => "- [Platform](/)\n- [Pricing](/#pricing)\n- [Docs](/docs)"],
                ['title' => 'Resources', 'text' => "- [Field Notes](/blog)\n- [Metric contracts](/docs)\n- [One revenue number for all](/one-revenue-number-for-finance-and-product)"],
                ['title' => 'Company', 'text' => '- hello@signallake.test'],
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
        $logoId = $this->logoFile();

        $config = [
            'logo' => [
                'id' => Utils::uid(),
                'type' => 'logo',
                'group' => 'basic',
                'files' => [$logoId],
                'data' => ['file' => ['id' => $logoId, 'type' => 'file']],
            ],
            'logo-alternative' => [
                'id' => Utils::uid(),
                'type' => 'logo-alternative',
                'group' => 'basic',
                'files' => [$logoId],
                'data' => ['file' => ['id' => $logoId, 'type' => 'file']],
            ],
        ];

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
                    ['title' => 'Metric contracts', 'text' => 'Every key number has an owner, source, grain, freshness rule, and approval state.', 'file' => ['id' => $this->img( 'contract' ), 'type' => 'file']],
                    ['title' => 'Live operating views', 'text' => 'Dashboards stay connected to governed data instead of drifting into slide decks and spreadsheet copies.', 'file' => ['id' => $this->img( 'data' ), 'type' => 'file']],
                    ['title' => 'Private workspaces', 'text' => 'Separate tenants, roles, and retention settings for teams that should share context without sharing every row.', 'file' => ['id' => $this->img( 'security' ), 'type' => 'file']],
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
                'limit' => 2,
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
            'config' => $config,
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
                'config' => $config,
                'meta' => $meta,
                'content' => $content,
            ],
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
     * Creates the SignalLake SVG logo and returns its file ID.
     *
     * @return string File ID
     */
    protected function logoFile() : string
    {
        if( !isset( $this->logoFile ) )
        {
            $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 360 80" role="img" aria-labelledby="title desc">
  <title id="title">SignalLake logo</title>
  <desc id="desc">SignalLake wordmark with a glassy lake signal symbol</desc>
  <defs>
    <linearGradient id="mark" x1="8" y1="10" x2="64" y2="70" gradientUnits="userSpaceOnUse">
      <stop offset="0" stop-color="#F8FAFC"/>
      <stop offset="0.36" stop-color="#8AFFC4"/>
      <stop offset="1" stop-color="#6366F1"/>
    </linearGradient>
    <linearGradient id="shine" x1="17" y1="16" x2="55" y2="60" gradientUnits="userSpaceOnUse">
      <stop offset="0" stop-color="#FFFFFF" stop-opacity="0.85"/>
      <stop offset="1" stop-color="#FFFFFF" stop-opacity="0.12"/>
    </linearGradient>
    <linearGradient id="text" x1="88" y1="18" x2="286" y2="62" gradientUnits="userSpaceOnUse">
      <stop offset="0" stop-color="#FFFFFF"/>
      <stop offset="0.58" stop-color="#E2E8F0"/>
      <stop offset="1" stop-color="#8AFFC4"/>
    </linearGradient>
  </defs>
  <g fill="none" fill-rule="evenodd">
    <path d="M40 8 70 25.5v29L40 72 10 54.5v-29L40 8Z" fill="#FFFFFF" fill-opacity="0.08" stroke="#FFFFFF" stroke-opacity="0.34" stroke-width="1.5"/>
    <path d="M40 13.5 64.8 28v24L40 66.5 15.2 52V28L40 13.5Z" fill="url(#mark)" fill-opacity="0.72"/>
    <path d="M40 19 58 29.5v21L40 61 22 50.5v-21L40 19Z" fill="#060A12" fill-opacity="0.36"/>
    <path d="M24 47c7.2-5.8 14.1-5.8 20.8 0 3.6 3.1 7.5 3.1 11.8 0" stroke="#8AFFC4" stroke-width="4" stroke-linecap="round"/>
    <path d="M27 34.5c9.3-7.1 18-7.1 26 0" stroke="#F8FAFC" stroke-opacity="0.9" stroke-width="4" stroke-linecap="round"/>
    <path d="M18.5 29.5 40 17l21.5 12.5" stroke="url(#shine)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    <circle cx="40" cy="40" r="4" fill="#F8FAFC"/>
    <text x="88" y="51" fill="url(#text)" font-family="ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif" font-size="36" font-weight="750">SignalLake</text>
    <path d="M88 68h98" stroke="#8AFFC4" stroke-opacity="0.55" stroke-width="3" stroke-linecap="round"/>
    <path d="M198 68h32" stroke="#6366F1" stroke-opacity="0.75" stroke-width="3" stroke-linecap="round"/>
  </g>
</svg>
SVG;

            $disk = Storage::disk( config( 'cms.disk', 'public' ) );
            $path = rtrim( 'cms/' . $this->tenant, '/' ) . '/signallake-logo.svg';

            if( !$disk->put( $path, $svg ) ) {
                throw new \Aimeos\Cms\Exception( sprintf( 'Unable to store logo "%s"', $path ) );
            }

            $data = [
                'mime' => 'image/svg+xml',
                'lang' => 'en',
                'name' => 'SignalLake logo',
                'path' => $path,
                'previews' => ['500' => $path],
                'description' => ['en' => 'SignalLake wordmark with a glassy lake signal symbol'],
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
            $poster = 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=1500&q=80&fm=jpg&fit=crop';

            $data = [
                'mime' => 'video/mp4',
                'lang' => 'en',
                'name' => 'SignalLake dashboard walkthrough',
                'path' => 'https://media.w3.org/2010/05/sintel/trailer.mp4',
                'previews' => ['1500' => $poster],
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
