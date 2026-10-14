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
 * Folio theme demo for the personal website of Lena Hartmann, a fictional backend engineer.
 */
class FolioDemo extends AbstractDemo
{
    /** @var array<string, string> Meta descriptions keyed by page path */
    protected const DESCRIPTIONS = [
        'about' => 'Lena Hartmann is a staff engineer in Berlin who builds Laravel backends, queues and search for products with millions of users.',
        'now' => 'What Lena Hartmann is working on, reading and learning right now.',
        'octane-in-production' => 'Twelve months of Laravel Octane in production: the memory leaks, the singletons and the 38% faster responses.',
        'projects' => 'Open source packages, tools and talks by Lena Hartmann, from feature flags for Laravel to queue monitoring.',
        'queues-are-a-design-tool' => 'Why queues belong in your architecture diagrams and not only in your performance tuning checklist.',
        'sqlite-in-memory-tests' => 'SQLite in memory makes Laravel tests fast. Here are the five cases where it hides bugs that MySQL and PostgreSQL find.',
        'terminal-setup-2026' => 'The shell, editor, prompts and small tools Lena Hartmann uses every day in 2026.',
        'tiny-search-engine' => 'Build a tiny full-text search engine in about 200 lines of PHP with an inverted index and BM25 ranking.',
        'uses' => 'The hardware, software and services Lena Hartmann uses to write code and articles.',
        'writing' => 'Articles about Laravel, PHP, databases, queues and building backends that stay fast and boring.',
        'zero-downtime-migrations' => 'How to change a 400 million row MySQL table without downtime: expand and contract, backfills in batches and safe cut-overs.',
    ];

    /** @var array<string, array{0: string, 1: string, 2: string}> */
    protected const PHOTOS = [
        'catalog' => ['photo-1544383835-bda2bc66a55d', 'Card catalog', 'Wooden library card catalog with many small labelled drawers'],
        'code' => ['photo-1515879218367-8466d910aaa4', 'Python code', 'Colorful source code on a dark monitor'],
        'glasses' => ['photo-1504639725590-34d0984388bd', 'Code and glasses', 'Glasses in front of two screens full of source code'],
        'laptop' => ['photo-1555066931-4365d14bab8c', 'Laptop with code', 'Laptop with a dark code editor on a desk'],
        'servers' => ['photo-1558494949-ef010cbdcc31', 'Server room', 'Rows of servers with blue status lights in a data centre'],
        'terminal' => ['photo-1629654297299-c8506221ca97', 'Terminal', 'Terminal window with a sudo command on a black background'],
    ];

    private string $avatar;
    private string $element;


    /**
     * Creates the about page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addAbout( Page $home ) : static
    {
        $this->page( ['name' => 'About', 'title' => 'About Lena Hartmann', 'path' => 'about'], [
            $this->heading( 'About', '1' ),
            $this->text( <<<'MD'
I'm a staff engineer at [Northwind Labs](https://example.com) in Berlin, where I look after the platform behind a logistics product that moves about two million parcels a week. Most days that means Laravel, MySQL, Redis and a lot of queues.

I started out building shop websites for an agency in Kiel and learned the hard way that the boring solution is usually the right one. Today I care most about systems that are easy to change, easy to debug at 3 a.m. and fast enough that nobody has to think about them.

Outside of work I maintain a few open source packages, speak at PHP conferences and write about what I learn on this site. I also bake far too much sourdough bread.
MD ),
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'Experience',
                'layout' => 'vertical',
                'items' => [
                    ['label' => '2023 — now', 'title' => 'Staff Engineer, Northwind Labs', 'text' => "Lead the platform team of five. Cut p95 API latency from 480 ms to 120 ms and moved 40 cron jobs to event-driven queues.\n\n`Laravel` `MySQL` `Redis` `Kubernetes`"],
                    ['label' => '2020 — 2023', 'title' => 'Senior Developer, Parcelly', 'text' => "Built the tracking API used by 300 online shops and the search that answers 50 queries per second.\n\n`PHP` `PostgreSQL` `Elasticsearch`"],
                    ['label' => '2017 — 2020', 'title' => 'Developer, Agency Kiel', 'text' => "Shipped more than 40 websites and shops for clients from bakeries to shipping companies.\n\n`Laravel` `Vue` `MariaDB`"],
                    ['label' => '2015', 'title' => 'B.Sc. Computer Science, TU Hamburg', 'text' => 'Thesis on inverted indexes for small search engines, the start of a long obsession.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Speaking',
                'columns' => '2',
                'cards' => [
                    ['title' => 'Tiny Search', 'url' => 'https://example.com/talks/tiny-search', 'text' => "Laracon EU 2026, Amsterdam. Full-text search in 200 lines of PHP.\n\n`Talk` `25 min`"],
                    ['title' => 'Queues as a design tool', 'url' => 'https://example.com/talks/queues', 'text' => "PHP Berlin 2025. Why async boundaries make better architecture.\n\n`Talk` `40 min`"],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the now page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addNow( Page $home ) : static
    {
        $this->page( ['name' => 'Now', 'title' => 'What I\'m Doing Now', 'path' => 'now'], [
            $this->heading( 'Now', '1' ),
            $this->text( <<<'MD'
*Updated October 2026 from Berlin.*

- **Work:** Splitting our largest table into monthly partitions without downtime. I'm writing about it as I go.
- **Open source:** Preparing laravel-flagship 3.0 with gradual rollouts by percentage and region.
- **Speaking:** Giving "Tiny Search" again at a meetup in Hamburg in November.
- **Reading:** *Designing Data-Intensive Applications* for the second time, slower and with notes.
- **Learning:** Enough Rust to write a PHP extension without crashing everything.

This is a [now page](https://nownownow.com/about). If you have your own, send it to me.
MD ),
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
        $this->page( ['name' => 'Projects', 'title' => 'Projects and Open Source', 'path' => 'projects'], [
            $this->heading( 'Projects', '1' ),
            $this->text( 'Things I built because I needed them and kept maintaining because other people needed them too. Everything is open source unless noted otherwise.' ),
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'layout' => 'figures',
                'cards' => [
                    ['title' => '2.1k', 'text' => 'GitHub stars'],
                    ['title' => '4.8M', 'text' => 'Packagist installs'],
                    ['title' => '14', 'text' => 'Conference talks'],
                ],
            ]],
            $this->projects(),
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Smaller things',
                'columns' => '2',
                'cards' => [
                    ['title' => 'dotfiles', 'url' => 'https://github.com/example/dotfiles', 'text' => "Shell, editor and Git setup for macOS and Linux.\n\n`zsh` `Neovim`"],
                    ['title' => 'Tiny Search slides', 'url' => 'https://example.com/talks/tiny-search', 'text' => "Slides and code from my Laracon EU 2026 talk.\n\n`Talk` `PHP`"],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the uses page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addUses( Page $home ) : static
    {
        $this->page( ['name' => 'Uses', 'title' => 'What I Use', 'path' => 'uses'], [
            $this->heading( 'Uses', '1' ),
            $this->text( 'People ask about my setup after every talk, so here is the full list. Nothing on it is sponsored.' ),
            $this->heading( 'Hardware' ),
            $this->text( "- **Laptop:** MacBook Pro 14\", M3 Pro, 36 GB\n- **Monitor:** 27\" 4K, mostly running a single terminal\n- **Keyboard:** Split mechanical keyboard with silent tactile switches\n- **Chair:** A used office chair that cost less than the keyboard" ),
            $this->heading( 'Development' ),
            $this->text( "- **Editor:** Neovim with LSP, PHPStan and Pint on save\n- **Terminal:** Ghostty with zsh and a two-line prompt\n- **Database:** TablePlus for MySQL, PostgreSQL and SQLite\n- **Local PHP:** Laravel Herd, one PHP version per project" ),
            $this->heading( 'Writing' ),
            $this->text( "- **Drafts:** Plain Markdown files in a Git repository\n- **Website:** This site runs on PagibleAI CMS\n- **Diagrams:** Excalidraw, exported as SVG" ),
        ], $home );

        return $this;
    }


    /**
     * Creates the writing page with the blog posts below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addWriting( Page $home ) : static
    {
        $id = (string) Str::uuid7();
        $writing = $this->page( ['id' => $id, 'name' => 'Writing', 'title' => 'Writing', 'path' => 'writing'], [
            $this->heading( 'Writing', '1' ),
            $this->text( 'Notes on Laravel, PHP, databases and the craft of building backends. I write when I have learned something the hard way, about once a month.' ),
            $this->blog( '', 'list', 20, $id ),
        ], $home );

        foreach( $this->posts() as [$date, $data, $photo, $intro, $content] )
        {
            $fileId = $this->img( $photo );
            $article = $this->article( $data['title'], $intro, $fileId );
            $post = $this->page( $data + ['type' => 'blog'], array_merge( [$article], $content ), $writing, [$fileId] );

            $post->forceFill( ['created_at' => $date] )->saveQuietly();
        }

        return $this;
    }


    /**
     * Creates the avatar of Lena Hartmann once and returns its file ID.
     *
     * @return string File ID
     */
    protected function avatar() : string
    {
        if( !isset( $this->avatar ) )
        {
            $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 160" role="img" aria-labelledby="title desc">
  <title id="title">Lena Hartmann</title>
  <desc id="desc">Green circle with the white initials LH</desc>
  <rect width="160" height="160" fill="#1F7A4D"/>
  <circle cx="128" cy="32" r="56" fill="#2E9A63"/>
  <text x="80" y="101" fill="#FFFFFF" font-family="Georgia, 'Times New Roman', serif" font-size="58" font-weight="700" letter-spacing="-2" text-anchor="middle">LH</text>
</svg>
SVG;

            $this->avatar = $this->svgFile( $svg, 'lena-hartmann.svg', 'Lena Hartmann', 'Green circle with the white initials LH', true );
        }

        return $this->avatar;
    }


    /**
     * Returns a blog list content element.
     *
     * @param string $title List title
     * @param string $layout List layout
     * @param int $limit Maximum number of posts
     * @param string|null $parentId Parent page ID
     * @return array<string, mixed> Blog content element
     */
    protected function blog( string $title, string $layout, int $limit, ?string $parentId = null ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'blog', 'group' => 'main', 'data' => array_filter( [
            'title' => $title,
            'layout' => $layout,
            'order' => '-created_at',
            'limit' => $limit,
            'parent-page' => $parentId ? ['value' => $parentId, 'label' => 'Writing'] : null,
        ] )];
    }


    /**
     * Returns a code content element.
     *
     * @param string $language Programming language
     * @param string $text Source code
     * @return array<string, mixed> Code content element
     */
    protected function code( string $language, string $text ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'code', 'group' => 'main', 'data' => [
            'language' => ['value' => $language],
            'text' => $text,
        ]];
    }


    /**
     * Creates the shared footer note and returns its ID.
     *
     * @return string Element ID
     */
    protected function element() : string
    {
        return $this->element ??= $this->saveElement( 'text', 'Folio footer', [
            'text' => 'Thanks for reading. New posts arrive about once a month, follow along via [GitHub](https://github.com/example) or [Mastodon](https://example.social/@lena).',
        ] );
    }


    /**
     * Returns a heading content element.
     *
     * @param string $title Heading text
     * @param string $level Heading level
     * @return array<string, mixed> Heading content element
     */
    protected function heading( string $title, string $level = '2' ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => ['title' => $title, 'level' => $level]];
    }


    /**
     * Returns the home page of Lena Hartmann.
     *
     * @return Page Home page
     */
    protected function home() : Page
    {
        $elementId = $this->element();
        $fileId = $this->avatar();

        $config = [
            'website' => Validation::entry( 'website', ['title' => 'Lena Hartmann'], 'config' ),
            'folio::profile' => [
                'type' => 'folio::profile',
                'files' => [$fileId],
                'data' => [
                    'name' => 'Lena Hartmann',
                    'job' => 'Staff Engineer',
                    'company' => 'Northwind Labs',
                    'location' => 'Berlin, Germany',
                    'avatar' => ['id' => $fileId, 'type' => 'file'],
                    'bio' => 'Staff engineer in Berlin. I build Laravel backends that stay fast and boring, and write about what I learn along the way.',
                    'email' => 'hello@example.com',
                    'links' => [
                        ['label' => 'GitHub', 'url' => 'https://github.com/example'],
                        ['label' => 'Mastodon', 'url' => 'https://example.social/@lena'],
                        ['label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/in/example'],
                    ],
                ],
            ],
        ];

        $content = [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'subtitle' => 'Staff Engineer at Northwind Labs · Berlin',
                'title' => 'Hi, I\'m Lena. I build *fast, boring* backends.',
                'text' => 'I work on Laravel platforms that handle millions of requests a day, maintain a few open source packages and write about databases, queues and search. Right now I\'m splitting a 400M row table without anyone noticing.',
                'buttons' => [
                    ['label' => 'Read my writing', 'url' => '/writing'],
                    ['label' => 'More about me', 'url' => '/about'],
                ],
                'files' => [['id' => $fileId, 'type' => 'file']],
            ]],
            $this->blog( 'Recent writing', 'list', 5 ),
            $this->projects( 'Selected projects' ),
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Let\'s *work together*',
                'text' => 'I take on a few consulting projects each year: performance reviews, queue architecture and database migrations for Laravel teams. Write me a short note about your project.',
                'buttons' => [
                    ['label' => 'Send an e-mail', 'url' => 'mailto:hello@example.com'],
                    ['label' => 'Read about me', 'url' => '/about'],
                ],
            ]],
        ];

        $meta = [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => 'Lena Hartmann is a staff engineer in Berlin who writes about Laravel, PHP, databases, queues and search.',
                'keywords' => 'Lena Hartmann, Laravel, PHP, backend, engineer, blog, portfolio, Berlin',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => 'Lena Hartmann | Backend Engineer',
                'description' => 'Fast, boring backends with Laravel. Articles, projects and talks.',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        return $this->saveRoot( 'Lena Hartmann | Backend Engineer and Writer', $config, $meta, $content, $elementId, $fileId );
    }


    /**
     * Creates a page below the given parent and returns it.
     *
     * @param array<string, mixed> $data Page attributes
     * @param array<int, array<string, mixed>> $content Content elements
     * @param Page $parent Parent page
     * @param array<int, string> $fileIds Additional file IDs
     * @return Page Created page
     */
    protected function page( array $data, array $content, Page $parent, array $fileIds = [] ) : Page
    {
        $elementId = $this->element();
        $footer = [
            ['id' => Utils::uid(), 'type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        return $this->savePage( $data + ['lang' => 'en', 'type' => 'page', 'status' => 1], $content, $parent,
            $elementId, $this->avatar(), $footer, 'Lena Hartmann, Laravel, PHP, backend, blog', $fileIds );
    }


    /**
     * Builds the Folio demo page tree.
     */
    protected function pages() : void
    {
        $home = $this->home();

        $this->addWriting( $home )
            ->addProjects( $home )
            ->addAbout( $home )
            ->addUses( $home )
            ->addNow( $home );
    }


    /**
     * Returns the blog posts as date, page data, photo key, introduction and content elements, newest first.
     *
     * @return array<int, array{0: string, 1: array<string, string>, 2: string, 3: string, 4: array<int, array<string, mixed>>}>
     */
    protected function posts() : array
    {
        return [
            ['2026-10-02 08:00:00', ['name' => 'Zero-downtime migrations', 'title' => 'Zero-downtime migrations on a 400M-row table', 'path' => 'zero-downtime-migrations'], 'servers',
                'Adding a column to a table with 400 million rows locked our checkout for eleven minutes once. This is the playbook we use since then, and it has not failed us in two years.', [
                $this->text( 'An `ALTER TABLE` on a large MySQL table can copy the whole table, hold metadata locks and fill the replication lag for an hour. Online DDL helps, but not for every change. The safe way is to never change a column in place: you expand the schema first, move the data in small steps and contract it at the end.' ),
                $this->heading( 'Expand, migrate, contract' ),
                $this->text( "Every risky change becomes three deployments:\n\n1. **Expand:** add the new column or table, nullable and without defaults that rewrite rows.\n2. **Migrate:** write to both places and backfill old rows in batches.\n3. **Contract:** switch reads, then drop the old column a release later." ),
                $this->code( 'php', <<<'PHP'
Schema::table('shipments', function (Blueprint $table) {
    $table->string('carrier_code', 16)->nullable()->after('carrier_id');
});
PHP ),
                $this->heading( 'Backfill in batches' ),
                $this->text( 'Never update millions of rows in one statement. Walk the primary key in chunks, keep each transaction short and pause between batches so replicas can keep up.' ),
                $this->code( 'php', <<<'PHP'
Shipment::whereNull('carrier_code')
    ->chunkById(5000, function ($shipments) {
        Shipment::whereKey($shipments->modelKeys())->update([
            'carrier_code' => DB::raw('(select code from carriers where carriers.id = shipments.carrier_id)'),
        ]);

        usleep(200_000); // let the replicas catch up
    });
PHP ),
                $this->text( "> [!TIP]\n> Watch `Seconds_Behind_Source` on your replicas while the backfill runs and stop the job automatically when it grows above a few seconds." ),
                $this->heading( 'The cut-over' ),
                $this->text( 'Switch reads behind a feature flag, compare the results of old and new column for a day and only then remove the old code path. Dropping the old column is the last, most boring step, and that is exactly how it should feel.' ),
            ]],
            ['2026-08-14 08:00:00', ['name' => 'Octane in production', 'title' => 'What I learned running Octane in production for a year', 'path' => 'octane-in-production'], 'laptop',
                'Laravel Octane made our API 38% faster. It also gave us three memory leaks and one very confusing bug with a tenant that saw another tenant\'s settings for a few seconds.', [
                $this->text( 'Octane keeps your application in memory between requests. Bootstrapping happens once, which is where the speed comes from. Everything that assumes a fresh process per request is now a potential bug.' ),
                $this->heading( 'Singletons remember everything' ),
                $this->text( 'A singleton that stores the current user, tenant or locale keeps that value for the next request on the same worker. Use scoped bindings for request state, they are flushed after each request.' ),
                $this->code( 'php', <<<'PHP'
// Leaks the first tenant into later requests
$this->app->singleton(TenantContext::class);

// Rebuilt for every request
$this->app->scoped(TenantContext::class);
PHP ),
                $this->heading( 'Static properties are global state' ),
                $this->text( 'Static caches in your own classes and in packages grow forever. We found them by recording the worker memory after every request and alerting when it grew for 1000 requests in a row.' ),
                $this->heading( 'Was it worth it?' ),
                $this->text( 'Yes. p95 latency went from 190 ms to 118 ms and we run four fewer servers. But plan a week for auditing your code and your packages before you switch.' ),
            ]],
            ['2026-06-30 08:00:00', ['name' => 'Queues are a design tool', 'title' => 'Queues are a design tool, not a performance trick', 'path' => 'queues-are-a-design-tool'], 'glasses',
                'Most teams add a queue when a request gets slow. The bigger win is using queues to decide which parts of your system are allowed to fail independently.', [
                $this->text( 'When an order is placed, five things happen: the payment is captured, stock is reserved, an e-mail is sent, the warehouse is notified and the analytics are updated. Only the first two have to succeed before you answer the customer.' ),
                $this->heading( 'Draw the async boundary' ),
                $this->text( 'Everything after the boundary runs in jobs. If the mail server is down, the order still works and the mail goes out later. That is a product decision first and a performance optimization second.' ),
                $this->code( 'php', <<<'PHP'
DB::transaction(function () use ($order) {
    $order->capturePayment();
    $order->reserveStock();
});

Bus::chain([
    new SendOrderConfirmation($order),
    new NotifyWarehouse($order),
])->dispatch();

TrackOrderPlaced::dispatch($order)->onQueue('low');
PHP ),
                $this->heading( 'Make jobs safe to repeat' ),
                $this->text( 'Jobs will run twice sooner or later. Give every job an idempotency key, store what it already did and let the second run do nothing.' ),
            ]],
            ['2026-05-09 08:00:00', ['name' => 'SQLite in memory tests', 'title' => 'Testing with SQLite in memory: when it lies to you', 'path' => 'sqlite-in-memory-tests'], 'code',
                'An in-memory SQLite database makes a Laravel test suite run in seconds. Here are five bugs it let through to production for us, and how we catch them now.', [
                $this->text( 'SQLite is a fantastic database. It is just not the database you run in production, and the differences show up in exactly the places where bugs hurt most.' ),
                $this->heading( 'Five differences that matter' ),
                $this->text( "1. **Types:** SQLite stores `'42abc'` in an integer column without complaining.\n2. **String comparison:** MySQL compares case-insensitively by default, SQLite does not.\n3. **Foreign keys:** they are off unless you enable them.\n4. **Locking:** `lockForUpdate()` does nothing useful.\n5. **JSON:** the JSON functions and their results differ in edge cases." ),
                $this->heading( 'Run both' ),
                $this->text( 'We keep SQLite for the fast local loop and run the full suite against MySQL in CI. A tiny trait marks the tests that only make sense on the real database.' ),
                $this->code( 'php', <<<'PHP'
trait RequiresMysql
{
    protected function setUpRequiresMysql(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Needs MySQL locking semantics');
        }
    }
}
PHP ),
            ]],
            ['2026-03-21 08:00:00', ['name' => 'My 2026 terminal setup', 'title' => 'My 2026 terminal setup', 'path' => 'terminal-setup-2026'], 'terminal',
                'I spend most of my day in a terminal. This is the setup I ended up with after trying far too many alternatives, with the config to copy.', [
                $this->text( 'The goal is simple: start fast, show what matters and stay out of the way. Everything here starts in under 50 ms.' ),
                $this->heading( 'Shell and prompt' ),
                $this->text( 'zsh with a handful of plugins and a two-line prompt that shows the Git branch, the PHP version and the exit code of the last command.' ),
                $this->code( 'bash', <<<'BASH'
alias a="php artisan"
alias t="php artisan test --parallel"
alias stan="vendor/bin/phpstan analyse --memory-limit=1G"

export FZF_DEFAULT_COMMAND="rg --files --hidden -g '!.git'"
BASH ),
                $this->heading( 'Small tools I use every day' ),
                $this->text( "- `rg` to search code\n- `fzf` to jump to files and branches\n- `lazygit` for interactive rebases\n- `httpie` for API requests\n- `just` for project tasks" ),
            ]],
            ['2026-01-18 08:00:00', ['name' => 'A tiny search engine', 'title' => 'Writing a tiny full-text search engine in PHP', 'path' => 'tiny-search-engine'], 'catalog',
                'Full-text search feels like magic until you build one. In about 200 lines of PHP you get an inverted index, BM25 ranking and results that are surprisingly good.', [
                $this->text( 'Every search engine starts with the same idea a library card catalog uses: instead of reading every document, you look up each word and get the list of documents that contain it.' ),
                $this->heading( 'Tokenize and index' ),
                $this->code( 'php', <<<'PHP'
function tokens(string $text): array
{
    return preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
}

foreach ($documents as $id => $text) {
    foreach (array_count_values(tokens($text)) as $term => $count) {
        $index[$term][$id] = $count;
    }
}
PHP ),
                $this->heading( 'Rank with BM25' ),
                $this->text( 'BM25 rewards documents that contain a rare word often, but with diminishing returns, and it punishes very long documents a little. Two parameters, `k1` and `b`, are all you need to tune.' ),
                $this->heading( 'What I would add next' ),
                $this->text( 'Stemming, a prefix index for search-as-you-type and storing the index in SQLite FTS5 once it no longer fits into memory. That is where you stop writing a tiny engine and start using a real one.' ),
            ]],
        ];
    }


    /**
     * Returns the project cards content element.
     *
     * @param string $title Section title
     * @return array<string, mixed> Cards content element
     */
    protected function projects( string $title = '' ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => array_filter( [
            'title' => $title,
            'columns' => '2',
            'cards' => [
                ['title' => 'laravel-flagship', 'url' => 'https://github.com/example/laravel-flagship', 'text' => "Feature flags for Laravel with gradual rollouts, stored in your own database. 2.1k stars.\n\n`PHP` `Laravel`"],
                ['title' => 'queue-lens', 'url' => 'https://github.com/example/queue-lens', 'text' => "A terminal dashboard for Laravel queues: throughput, failures and slow jobs at a glance.\n\n`PHP` `Redis`"],
                ['title' => 'pgsnap', 'url' => 'https://github.com/example/pgsnap', 'text' => "Snapshot and restore PostgreSQL test databases in milliseconds instead of migrating.\n\n`Go` `PostgreSQL`"],
                ['title' => 'tiny-search', 'url' => '/tiny-search-engine', 'text' => "The full-text search engine from my blog post, with BM25 ranking in 200 lines.\n\n`PHP`"],
            ],
        ] )];
    }


    /**
     * Returns a text content element.
     *
     * @param string $text Markdown text
     * @return array<string, mixed> Text content element
     */
    protected function text( string $text ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => ['text' => $text]];
    }
}
