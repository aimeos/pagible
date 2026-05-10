<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Utils;


class DemoSeeder extends Seeder
{
    private string $audioFile;
    private string $element;
    private string $file;
    private string $theme;
    private string $videoFile;


    public function __construct()
    {
        $this->theme = env( 'CMS_THEME', '' );
    }


    /**
     * Seed demo content with all content element types.
     * Pass theme name via CMS_THEME environment variable.
     */
    public function run()
    {
        $tenant = env( 'CMS_TENANT', '' );

        \Aimeos\Cms\Tenancy::$callback = fn() => $tenant;

        File::where( 'tenant_id', $tenant )->forceDelete();
        Version::where( 'tenant_id', $tenant )->forceDelete();
        Element::where( 'tenant_id', $tenant )->forceDelete();
        Page::where( 'tenant_id', $tenant )->forceDelete();

        Page::withoutSyncingToSearch( function() {
            Element::withoutSyncingToSearch( function() {
                File::withoutSyncingToSearch( function() {
                    $home = $this->home();

                    $this->addBlog( $home )
                        ->addDocs( $home );
                } );
            } );
        } );

        Page::query()->searchable();
        Element::query()->searchable();
        File::query()->searchable();
    }


    protected function file() : string
    {
        if( !isset( $this->file ) )
        {
            $file = File::forceCreate( [
                'mime' => 'image/jpeg',
                'lang' => 'en',
                'name' => 'PagibleAI CMS Dashboard',
                'path' => 'https://picsum.photos/id/10/1500/1000',
                'previews' => [
                    '500' => 'https://picsum.photos/id/10/500/333',
                    '1000' => 'https://picsum.photos/id/10/1000/666',
                ],
                'description' => ['en' => 'PagibleAI CMS delivers blazing-fast content management'],
                'editor' => 'demo',
            ] );

            $version = $file->versions()->forceCreate( [
                'lang' => 'en',
                'data' => [
                    'mime' => 'image/jpeg',
                    'lang' => 'en',
                    'name' => 'PagibleAI CMS Dashboard',
                    'path' => 'https://picsum.photos/id/10/1500/1000',
                    'previews' => [
                        '500' => 'https://picsum.photos/id/10/500/333',
                        '1000' => 'https://picsum.photos/id/10/1000/666',
                    ],
                    'description' => ['en' => 'PagibleAI CMS delivers blazing-fast content management'],
                ],
                'published' => true,
                'editor' => 'demo',
            ] );

            $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $file->publish( $version );
            $this->file = $file->refresh()->id;
        }

        return $this->file;
    }


    protected function videoFile() : string
    {
        if( !isset( $this->videoFile ) )
        {
            $file = File::forceCreate( [
                'mime' => 'video/mp4',
                'lang' => 'en',
                'name' => 'PagibleAI CMS Quick Tour',
                'path' => 'https://www.w3schools.com/html/mov_bbb.mp4',
                'previews' => ['500' => 'https://picsum.photos/id/20/500/333'],
                'description' => ['en' => 'See how PagibleAI CMS simplifies content creation with AI assistance'],
                'editor' => 'demo',
            ] );

            $version = $file->versions()->forceCreate( [
                'lang' => 'en',
                'data' => [
                    'mime' => 'video/mp4',
                    'lang' => 'en',
                    'name' => 'PagibleAI CMS Quick Tour',
                    'path' => 'https://www.w3schools.com/html/mov_bbb.mp4',
                    'previews' => ['500' => 'https://picsum.photos/id/20/500/333'],
                    'description' => ['en' => 'See how PagibleAI CMS simplifies content creation with AI assistance'],
                ],
                'published' => true,
                'editor' => 'demo',
            ] );

            $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $file->publish( $version );
            $this->videoFile = $file->refresh()->id;
        }

        return $this->videoFile;
    }


    protected function audioFile() : string
    {
        if( !isset( $this->audioFile ) )
        {
            $file = File::forceCreate( [
                'mime' => 'audio/mpeg',
                'lang' => 'en',
                'name' => 'PagibleAI CMS Podcast Episode',
                'path' => 'https://www.w3schools.com/html/horse.mp3',
                'previews' => [],
                'description' => ['en' => 'Learn about PagibleAI CMS features in this audio overview'],
                'editor' => 'demo',
            ] );

            $version = $file->versions()->forceCreate( [
                'lang' => 'en',
                'data' => [
                    'mime' => 'audio/mpeg',
                    'lang' => 'en',
                    'name' => 'PagibleAI CMS Podcast Episode',
                    'path' => 'https://www.w3schools.com/html/horse.mp3',
                    'previews' => [],
                    'description' => ['en' => 'Learn about PagibleAI CMS features in this audio overview'],
                ],
                'published' => true,
                'editor' => 'demo',
            ] );

            $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $file->publish( $version );
            $this->audioFile = $file->refresh()->id;
        }

        return $this->audioFile;
    }


    protected function element() : string
    {
        if( !isset( $this->element ) )
        {
            $cards = [
                ['title' => 'Product', 'text' => "Explore our tools\n\n- [Features](/)\n- [Pricing](/)"],
                ['title' => 'Resources', 'text' => "Learn and grow\n\n- [Docs](/)\n- [Blog](/)"],
                ['title' => 'Company', 'text' => "Get in touch\n\n- [About](/)\n- [Contact](/)"],
            ];

            $element = Element::forceCreate( [
                'lang' => 'en',
                'type' => 'cards',
                'name' => 'Shared footer',
                'data' => ['type' => 'cards', 'data' => ['cards' => $cards]],
                'editor' => 'demo',
            ] );

            $version = $element->versions()->forceCreate( [
                'lang' => 'en',
                'data' => [
                    'lang' => 'en',
                    'type' => 'cards',
                    'name' => 'Shared footer',
                    'data' => ['cards' => $cards],
                ],
                'published' => true,
                'editor' => 'demo',
            ] );

            $element->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $element->publish( $version );
            $this->element = $element->refresh()->id;
        }

        return $this->element;
    }


    protected function page( array $data, array $content, Page $parent, array $fileIds = [], array $meta = [] ) : Page
    {
        $elementId = $this->element();
        $fileId = $this->file();

        $meta = $data['meta'] ?? $meta ?: [
            ['type' => 'meta-tags', 'data' => [
                'description' => $data['title'] ?? '',
                'keywords' => 'PagibleAI CMS, Laravel CMS, AI content management',
            ]],
            ['type' => 'social-media', 'data' => [
                'title' => $data['title'] ?? '',
                'description' => $data['title'] ?? '',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ]],
        ];

        $content[] = ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'footer', 'data' => ['level' => 2, 'title' => 'PagibleAI CMS']];
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
            'data' => array_diff_key( $data, ['content' => 1, 'meta' => 1] ) + [
                'domain' => '',
                'theme' => $this->theme,
            ],
            'aux' => ['meta' => $meta, 'content' => $content],
            'published' => true,
            'editor' => 'demo',
        ] );

        $version->elements()->attach( $elementId );
        $version->files()->attach( array_unique( array_merge( [$fileId], $fileIds ) ) );

        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->publish( $version );

        return $page;
    }


    protected function home() : Page
    {
        $elementId = $this->element();
        $fileId = $this->file();

        $content = [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'PagibleAI CMS',
                'subtitle' => 'AI-Powered Content Management for Laravel',
                'text' => 'Create stunning websites effortlessly. PagibleAI CMS combines artificial intelligence with the elegance of Laravel to deliver the fastest, smartest CMS ever built.',
                'url' => '/pricing',
                'button' => 'See Plans',
                'url-alternative' => '/contact',
                'button-alternative' => 'Request a Demo',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Why Teams Love PagibleAI CMS',
                'cards' => [
                    ['title' => 'AI-Powered Editing', 'text' => 'PagibleAI CMS generates, translates, and refines content using built-in AI — saving hours of manual work every week.', 'file' => ['id' => $fileId, 'type' => 'file']],
                    ['title' => 'Sub-Millisecond Speed', 'text' => 'Intelligent page caching in PagibleAI CMS delivers responses in under a millisecond, outperforming every traditional CMS.', 'file' => ['id' => $fileId, 'type' => 'file']],
                    ['title' => 'Infinite Scalability', 'text' => 'PagibleAI CMS scales from a simple landing page to millions of pages without breaking a sweat — no infrastructure changes needed.', 'file' => ['id' => $fileId, 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
                'title' => 'Simple, Transparent Pricing',
                'text' => 'PagibleAI CMS offers plans for every team size — from solo creators to enterprise organizations.',
                'label' => 'Monthly',
                'label-alternative' => 'Yearly',
                'items' => [
                    ['name' => 'Starter', 'price' => 'Free', 'unit' => '', 'text' => 'Perfect for personal projects', 'features' => "- 5 pages\n- Community support\n- All core themes\n- AI content suggestions", 'url' => '#', 'button' => 'Start Free'],
                    ['name' => 'Pro', 'price' => '$29', 'unit' => '/mo', 'price-alternative' => '$290', 'unit-alternative' => '/yr', 'text' => 'For growing teams', 'features' => "- Unlimited pages\n- Priority support\n- Custom themes\n- Full AI suite\n- Multi-language", 'url' => '#', 'button' => 'Start Trial', 'highlight' => true, 'badge' => 'Most Popular'],
                    ['name' => 'Enterprise', 'price' => '$99', 'unit' => '/mo', 'price-alternative' => '$990', 'unit-alternative' => '/yr', 'text' => 'For large organizations', 'features' => "- Everything in Pro\n- SLA guarantee\n- Dedicated support\n- Custom AI models\n- On-premise option", 'url' => '#', 'button' => 'Contact Sales'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Frequently Asked Questions',
                'items' => [
                    ['title' => 'What makes PagibleAI CMS different from other platforms?', 'text' => 'PagibleAI CMS is the only Laravel CMS with native AI integration. It generates content, translates pages, and optimizes SEO automatically — all while delivering sub-millisecond page loads.'],
                    ['title' => 'How quickly can I get started with PagibleAI CMS?', 'text' => 'In under two minutes. Run `composer require aimeos/pagible` and `php artisan cms:install` — your site is ready to publish immediately.'],
                    ['title' => 'Is PagibleAI CMS suitable for large-scale projects?', 'text' => 'Absolutely. PagibleAI CMS handles millions of pages with ease thanks to its nested-set page tree, intelligent caching, and multi-tenant architecture.'],
                    ['title' => 'Can I customize themes in PagibleAI CMS?', 'text' => 'Yes! PagibleAI CMS themes are standard Composer packages with Blade templates. Override only what you need — everything else inherits from the base theme automatically.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Ready to Experience PagibleAI CMS?',
                'to' => 'hello@example.com',
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'footer', 'data' => ['level' => 2, 'title' => 'PagibleAI CMS']],
            ['type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        $page = Page::forceCreate( [
            'lang' => 'en',
            'name' => 'Home',
            'title' => 'PagibleAI CMS — The Smartest Content Management for Laravel',
            'path' => '',
            'tag' => 'root',
            'theme' => $this->theme,
            'status' => 1,
            'cache' => 5,
            'editor' => 'demo',
            'meta' => [
                ['type' => 'meta-tags', 'data' => [
                    'description' => 'PagibleAI CMS combines artificial intelligence with Laravel to deliver the fastest, smartest content management system ever built.',
                    'keywords' => 'PagibleAI CMS, Laravel CMS, AI CMS, headless CMS, content management, PHP CMS',
                ]],
                ['type' => 'social-media', 'data' => [
                    'title' => 'PagibleAI CMS — AI-Powered Content Management for Laravel',
                    'description' => 'PagibleAI CMS combines artificial intelligence with Laravel to deliver the fastest, smartest CMS ever built.',
                    'file' => ['id' => $fileId, 'type' => 'file'],
                ]],
            ],
            'content' => $content,
        ] );

        $version = $page->versions()->forceCreate( [
            'lang' => 'en',
            'data' => [
                'name' => 'Home',
                'title' => 'PagibleAI CMS — The Smartest Content Management for Laravel',
                'path' => '',
                'tag' => 'root',
                'domain' => '',
                'theme' => $this->theme,
                'status' => 1,
                'cache' => 5,
            ],
            'aux' => [
                'meta' => [
                    ['type' => 'meta-tags', 'data' => [
                        'description' => 'PagibleAI CMS combines artificial intelligence with Laravel to deliver the fastest, smartest content management system ever built.',
                        'keywords' => 'PagibleAI CMS, Laravel CMS, AI CMS, headless CMS, content management, PHP CMS',
                    ]],
                    ['type' => 'social-media', 'data' => [
                        'title' => 'PagibleAI CMS — AI-Powered Content Management for Laravel',
                        'description' => 'PagibleAI CMS combines artificial intelligence with Laravel to deliver the fastest, smartest CMS ever built.',
                        'file' => ['id' => $fileId, 'type' => 'file'],
                    ]],
                ],
                'content' => $content,
            ],
            'published' => true,
            'editor' => 'demo',
        ] );

        $version->files()->attach( $fileId );
        $version->elements()->attach( $elementId );
        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->publish( $version );

        return $page;
    }


    protected function addBlog( Page $home ) : static
    {
        $fileId = $this->file();

        $blog = $this->page( [
            'lang' => 'en',
            'name' => 'Blog',
            'title' => 'Blog | PagibleAI CMS',
            'path' => 'blog',
            'tag' => 'blog',
            'type' => 'blog',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'blog', 'group' => 'main', 'data' => [
                'title' => 'Latest from PagibleAI CMS',
                'limit' => 2,
            ]],
        ], $home, [], [
            ['type' => 'meta-tags', 'data' => [
                'description' => 'Stay up to date with the latest PagibleAI CMS features, performance insights, and best practices for building exceptional websites.',
                'keywords' => 'PagibleAI CMS blog, Laravel CMS updates, AI content management news',
            ]],
            ['type' => 'social-media', 'data' => [
                'title' => 'Blog | PagibleAI CMS',
                'description' => 'News, tutorials, and insights from the PagibleAI CMS team.',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ]],
        ] );

        // Article 1: article + image-text + text
        $this->page( [
            'lang' => 'en',
            'name' => 'Why PagibleAI CMS Outperforms Traditional Platforms',
            'title' => 'Why PagibleAI CMS Outperforms Traditional Platforms | Blog',
            'path' => 'why-pagibleai-outperforms',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
            'meta' => [
                ['type' => 'meta-tags', 'data' => [
                    'description' => 'Discover why PagibleAI CMS delivers superior performance, AI-powered editing, and developer experience compared to legacy CMS platforms.',
                    'keywords' => 'PagibleAI CMS vs WordPress, fastest Laravel CMS, AI content management',
                ]],
                ['type' => 'social-media', 'data' => [
                    'title' => 'Why PagibleAI CMS Outperforms Traditional Platforms',
                    'description' => 'PagibleAI CMS redefines content management — faster, smarter, built for the modern web.',
                    'file' => ['id' => $fileId, 'type' => 'file'],
                ]],
            ],
        ], [
            ['id' => Utils::uid(), 'type' => 'article', 'group' => 'main', 'data' => [
                'title' => 'Why PagibleAI CMS Outperforms Traditional Platforms',
                'file' => ['id' => $fileId, 'type' => 'file'],
                'text' => "PagibleAI CMS redefines what a content management system can be — faster, smarter, and built for the modern web.\n\nTraditional CMS platforms carry decades of technical debt. **PagibleAI CMS** starts fresh with a clean architecture built on Laravel, delivering performance that legacy systems simply cannot match.\n\nWith native AI integration, PagibleAI CMS writes, translates, and optimizes content while you focus on strategy.",
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $fileId, 'type' => 'file'],
                'text' => "### Intelligent Caching in PagibleAI CMS\n\nEvery page rendered by PagibleAI CMS is cached with configurable TTL. Response times stay under one millisecond even under heavy load — no CDN required.",
                'position' => 'start',
                'ratio' => '1-1',
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $fileId, 'type' => 'file'],
                'text' => "### AI-Native Content Generation\n\nPagibleAI CMS generates, translates, and optimizes content using built-in AI. Write once, publish everywhere — in any language.",
                'position' => 'end',
                'ratio' => '1-1',
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $fileId, 'type' => 'file'],
                'text' => "### Developer-Friendly Architecture\n\nBuilt on Laravel, PagibleAI CMS leverages Blade templates, Eloquent models, and the full PHP ecosystem. Extend it exactly as you would any Laravel application.",
                'position' => 'grid-start',
                'ratio' => '1-2',
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $fileId, 'type' => 'file'],
                'text' => "### Enterprise-Ready Security\n\nPagibleAI CMS ships with HTMLPurifier sanitization, Content Security Policy headers, rate limiting, and multi-tenant isolation — all configured out of the box.",
                'position' => 'grid-end',
                'ratio' => '1-2',
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $fileId, 'type' => 'file'],
                'text' => "### Seamless Multi-Language Support\n\nPagibleAI CMS handles translations natively. AI-powered translation lets you publish in dozens of languages without leaving the admin panel.",
                'position' => 'start',
                'ratio' => '1-3',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "## Key Advantages of PagibleAI CMS\n\n- **Zero bloat** — PagibleAI CMS includes only what you need, nothing more\n- **AI-native** — Content generation, translation, and SEO optimization built in\n- **Laravel-powered** — Leverage the full ecosystem of packages and tools\n- **Multi-tenant** — One installation serves unlimited sites with PagibleAI CMS",
            ]],
        ], $blog, [$fileId] );

        // Article 2: article + slideshow + table
        $this->page( [
            'lang' => 'en',
            'name' => 'PagibleAI CMS Performance That Speaks for Itself',
            'title' => 'PagibleAI CMS Performance That Speaks for Itself | Blog',
            'path' => 'pagibleai-performance',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
            'meta' => [
                ['type' => 'meta-tags', 'data' => [
                    'description' => 'Independent benchmarks show PagibleAI CMS renders pages in 0.3ms cached — 150x faster than WordPress and 40x faster than Statamic.',
                    'keywords' => 'PagibleAI CMS benchmarks, CMS performance comparison, fastest PHP CMS',
                ]],
                ['type' => 'social-media', 'data' => [
                    'title' => 'PagibleAI CMS Performance That Speaks for Itself',
                    'description' => 'Sub-millisecond page loads. See how PagibleAI CMS compares to WordPress, Statamic, and Craft.',
                    'file' => ['id' => $fileId, 'type' => 'file'],
                ]],
            ],
        ], [
            ['id' => Utils::uid(), 'type' => 'article', 'group' => 'main', 'data' => [
                'title' => 'PagibleAI CMS Performance That Speaks for Itself',
                'file' => ['id' => $fileId, 'type' => 'file'],
                'text' => "Independent benchmarks confirm PagibleAI CMS delivers the fastest page rendering of any PHP-based CMS.\n\nSpeed matters for SEO, conversions, and user experience. PagibleAI CMS was engineered from the ground up for maximum performance — cached pages render in microseconds, not milliseconds.",
            ]],
            ['id' => Utils::uid(), 'type' => 'slideshow', 'group' => 'main', 'data' => [
                'title' => 'PagibleAI CMS Benchmark Visualizations',
                'files' => [
                    ['id' => $fileId, 'type' => 'file'],
                    ['id' => $fileId, 'type' => 'file'],
                    ['id' => $fileId, 'type' => 'file'],
                ],
                'main' => true,
            ]],
            ['id' => Utils::uid(), 'type' => 'slideshow', 'group' => 'main', 'data' => [
                'title' => 'Infrastructure Comparison',
                'files' => [
                    ['id' => $fileId, 'type' => 'file'],
                    ['id' => $fileId, 'type' => 'file'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'PagibleAI CMS vs Competitors — Response Times (ms)',
                'header' => 'row',
                'table' => [
                    ['CMS', 'Cached Page', 'Uncached Page', 'Search Query'],
                    ['PagibleAI CMS', '0.3', '2.1', '1.5'],
                    ['WordPress', '45', '250', '120'],
                    ['Statamic', '12', '85', '45'],
                    ['Craft CMS', '18', '95', '55'],
                ],
            ]],
        ], $blog, [$fileId] );

        // Article 3: article + video + audio + code
        $videoId = $this->videoFile();
        $audioId = $this->audioFile();

        $this->page( [
            'lang' => 'en',
            'name' => 'Rich Media Made Easy with PagibleAI CMS',
            'title' => 'Rich Media Made Easy with PagibleAI CMS | Blog',
            'path' => 'rich-media-pagibleai',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
            'meta' => [
                ['type' => 'meta-tags', 'data' => [
                    'description' => 'PagibleAI CMS makes embedding video, audio, and syntax-highlighted code effortless with responsive players and automatic accessibility.',
                    'keywords' => 'PagibleAI CMS media, video CMS, audio CMS, code highlighting Laravel',
                ]],
                ['type' => 'social-media', 'data' => [
                    'title' => 'Rich Media Made Easy with PagibleAI CMS',
                    'description' => 'Video, audio, and code — beautifully handled by PagibleAI CMS.',
                    'file' => ['id' => $fileId, 'type' => 'file'],
                ]],
            ],
        ], [
            ['id' => Utils::uid(), 'type' => 'article', 'group' => 'main', 'data' => [
                'title' => 'Rich Media Made Easy with PagibleAI CMS',
                'file' => ['id' => $fileId, 'type' => 'file'],
                'text' => "PagibleAI CMS handles video, audio, and code with elegant simplicity — responsive, accessible, and fast.\n\nModern websites need more than text and images. PagibleAI CMS makes embedding rich media effortless with built-in players, syntax highlighting, and automatic accessibility features.",
            ]],
            ['id' => Utils::uid(), 'type' => 'video', 'group' => 'main', 'data' => [
                'file' => ['id' => $videoId, 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'audio', 'group' => 'main', 'data' => [
                'file' => ['id' => $audioId, 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'code', 'group' => 'main', 'data' => [
                'language' => ['value' => 'php'],
                'text' => "<?php\n\n// PagibleAI CMS makes page rendering beautifully simple\nuse Aimeos\\Cms\\Models\\Page;\n\n\$page = Page::where('path', 'blog')->firstOrFail();\n\n// AI-powered content generation\n\$page->generateContent('Write an engaging introduction');\n\n// Automatic multi-language support\n\$page->translate(['de', 'fr', 'es']);",
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'PagibleAI CMS Handles the Complexity',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => 'Add any media type to your page and PagibleAI CMS takes care of responsive sizing, lazy loading, accessibility attributes, and structured data — automatically.',
            ]],
        ], $blog, [$fileId, $videoId, $audioId] );

        // Article 4: article + image + file + html
        $this->page( [
            'lang' => 'en',
            'name' => 'Effortless File Management in PagibleAI CMS',
            'title' => 'Effortless File Management in PagibleAI CMS | Blog',
            'path' => 'file-management-pagibleai',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
            'meta' => [
                ['type' => 'meta-tags', 'data' => [
                    'description' => 'PagibleAI CMS automatically optimizes images with responsive srcsets, lazy loading, and WebP conversion — zero configuration required.',
                    'keywords' => 'PagibleAI CMS files, image optimization, responsive images, Laravel file management',
                ]],
                ['type' => 'social-media', 'data' => [
                    'title' => 'Effortless File Management in PagibleAI CMS',
                    'description' => 'Upload once, serve everywhere — PagibleAI CMS handles optimization automatically.',
                    'file' => ['id' => $fileId, 'type' => 'file'],
                ]],
            ],
        ], [
            ['id' => Utils::uid(), 'type' => 'article', 'group' => 'main', 'data' => [
                'title' => 'Effortless File Management in PagibleAI CMS',
                'file' => ['id' => $fileId, 'type' => 'file'],
                'text' => "PagibleAI CMS automatically optimizes images, generates responsive srcsets, and serves files with perfect performance.\n\nUpload once, serve everywhere. PagibleAI CMS generates multiple image sizes on upload, creates responsive srcset attributes, enables lazy loading, and serves optimized formats like WebP — all without any configuration.",
            ]],
            ['id' => Utils::uid(), 'type' => 'image', 'group' => 'main', 'data' => [
                'file' => ['id' => $fileId, 'type' => 'file'],
                'main' => true,
            ]],
            ['id' => Utils::uid(), 'type' => 'file', 'group' => 'main', 'data' => [
                'file' => ['id' => $fileId, 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'html', 'group' => 'main', 'data' => [
                'text' => '<div style="padding:1.5rem;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border-radius:8px;color:#fff;text-align:center"><p style="margin:0;font-size:1.2rem">PagibleAI CMS — Where AI meets elegant content management</p></div>',
            ]],
        ], $blog, [$fileId] );

        return $this;
    }


    protected function addDocs( Page $home ) : static
    {
        $fileId = $this->file();

        $docs = $this->page( [
            'lang' => 'en',
            'name' => 'Documentation',
            'title' => 'Documentation | PagibleAI CMS',
            'path' => 'docs',
            'type' => 'docs',
            'status' => 1,
            'meta' => [
                ['type' => 'meta-tags', 'data' => [
                    'description' => 'Complete documentation for PagibleAI CMS — installation, configuration, themes, and content elements explained step by step.',
                    'keywords' => 'PagibleAI CMS documentation, Laravel CMS guide, CMS installation, PagibleAI setup',
                ]],
                ['type' => 'social-media', 'data' => [
                    'title' => 'Documentation | PagibleAI CMS',
                    'description' => 'Everything you need to build with PagibleAI CMS — from installation to advanced customization.',
                    'file' => ['id' => $fileId, 'type' => 'file'],
                ]],
            ],
        ], [
            ['id' => Utils::uid(), 'type' => 'toc', 'group' => 'main', 'data' => [
                'title' => 'On this page',
                'action' => '\\Aimeos\\Cms\\Actions\\Toc',
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Getting Started with PagibleAI CMS',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "PagibleAI CMS installs in seconds via Composer:\n\n```bash\ncomposer require aimeos/pagible\nphp artisan cms:install\n```\n\nThat's it. PagibleAI CMS publishes migrations, configuration, and theme assets automatically. Your site is ready to publish.",
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'PagibleAI CMS Configuration',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "PagibleAI CMS keeps configuration simple and centralized in `config/cms/`. Key settings include:\n\n- `theme.cache` — Choose your cache store for rendered pages\n- `theme.ttl` — Control how long theme data stays cached\n- `theme.csp` — Fine-tune Content Security Policy directives\n- `ai.provider` — Select your preferred AI provider for content generation",
            ]],
            ['id' => Utils::uid(), 'type' => 'code', 'group' => 'main', 'data' => [
                'language' => ['value' => 'php'],
                'text' => "// PagibleAI CMS configuration — config/cms/theme.php\nreturn [\n    'cache' => env('APP_DEBUG') ? 'array' : 'file',\n    'ttl' => env('CMS_THEME_TTL', 86400),\n];",
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Multi-Tenancy in PagibleAI CMS',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "PagibleAI CMS supports multi-tenancy out of the box. Each tenant has isolated pages, elements, and files — all managed through a single installation with zero additional configuration.",
            ]],
        ], $home );

        // Docs child: Themes
        $themes = $this->page( [
            'lang' => 'en',
            'name' => 'Themes',
            'title' => 'Themes | PagibleAI CMS Documentation',
            'path' => 'themes',
            'type' => 'docs',
            'status' => 1,
            'meta' => [
                ['type' => 'meta-tags', 'data' => [
                    'description' => 'Learn how to create custom themes for PagibleAI CMS — Composer packages with Blade templates, smart inheritance, and zero overhead.',
                    'keywords' => 'PagibleAI CMS themes, custom CMS theme, Laravel Blade theme, theme development',
                ]],
                ['type' => 'social-media', 'data' => [
                    'title' => 'Themes | PagibleAI CMS Documentation',
                    'description' => 'Build beautiful custom themes for PagibleAI CMS with full creative freedom.',
                    'file' => ['id' => $fileId, 'type' => 'file'],
                ]],
            ],
        ], [
            ['id' => Utils::uid(), 'type' => 'toc', 'group' => 'main', 'data' => [
                'title' => 'On this page',
                'action' => '\\Aimeos\\Cms\\Actions\\Toc',
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Creating Themes for PagibleAI CMS',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "PagibleAI CMS themes are standard Composer packages that register a view namespace and publish CSS assets. Creating a custom theme is straightforward:\n\n1. Create a Composer package\n2. Add a ServiceProvider that registers your views\n3. Design your Blade layouts with full creative freedom\n4. Publish your CSS to `public/vendor/cms/themes/yourtheme/`\n\nPagibleAI CMS handles the rest — view resolution, asset loading, and fallback inheritance.",
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'PagibleAI CMS Theme Structure',
            ]],
            ['id' => Utils::uid(), 'type' => 'code', 'group' => 'main', 'data' => [
                'language' => ['value' => 'bash'],
                'text' => "my-theme/\n├── composer.json\n├── public/\n│   ├── cms.css          # Core overrides\n│   ├── hero.css         # Hero element styles\n│   └── ...              # Element-specific CSS\n├── src/\n│   └── MyThemeServiceProvider.php\n└── views/\n    └── layouts/\n        └── main.blade.php   # Your custom layout",
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Smart Theme Inheritance',
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $fileId, 'type' => 'file'],
                'text' => "Your theme only needs to override what it changes. PagibleAI CMS automatically falls back to the base theme for any view or asset you don't customize — keeping your theme lean and maintainable.",
                'position' => 'end',
                'ratio' => '1-3',
            ]],
        ], $docs, [$fileId] );

        // Docs grandchild: Theme Customization (enables sidebar details.is-menu)
        $this->page( [
            'lang' => 'en',
            'name' => 'Customization',
            'title' => 'Theme Customization | PagibleAI CMS Documentation',
            'path' => 'customization',
            'type' => 'docs',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'toc', 'group' => 'main', 'data' => [
                'title' => 'On this page',
                'action' => '\\Aimeos\\Cms\\Actions\\Toc',
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Customizing PagibleAI CMS Themes',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Override any view or CSS file in your custom theme. PagibleAI CMS resolves views from your theme first, falling back to the default theme for anything you haven't customized.",
            ]],
        ], $themes );

        // Docs child: Content Elements
        $this->page( [
            'lang' => 'en',
            'name' => 'Content Elements',
            'title' => 'Content Elements | PagibleAI CMS Documentation',
            'path' => 'content-elements',
            'type' => 'docs',
            'status' => 1,
            'meta' => [
                ['type' => 'meta-tags', 'data' => [
                    'description' => 'All 20 content elements in PagibleAI CMS — hero, cards, pricing, FAQ, blog, video, code, and more. Each with its own optimized CSS.',
                    'keywords' => 'PagibleAI CMS elements, content blocks, hero section, pricing table, FAQ accordion, blog CMS',
                ]],
                ['type' => 'social-media', 'data' => [
                    'title' => 'Content Elements | PagibleAI CMS Documentation',
                    'description' => 'PagibleAI CMS ships with 20 content elements that cover every use case — loaded on demand for peak performance.',
                    'file' => ['id' => $fileId, 'type' => 'file'],
                ]],
            ],
        ], [
            ['id' => Utils::uid(), 'type' => 'toc', 'group' => 'main', 'data' => [
                'title' => 'On this page',
                'action' => '\\Aimeos\\Cms\\Actions\\Toc',
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'PagibleAI CMS Content Elements',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => 'PagibleAI CMS ships with a comprehensive set of content elements that cover every common use case. Each element is a Blade partial with its own CSS — loaded only when used.',
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => '',
                'header' => 'row',
                'table' => [
                    ['Element', 'CSS', 'Description'],
                    ['hero', 'hero.css', 'Full-width hero section with image and call-to-action'],
                    ['heading', '—', 'Semantic h1–h6 headings'],
                    ['text', '—', 'Markdown-rendered rich text blocks'],
                    ['image', 'image.css', 'Responsive image with automatic srcset'],
                    ['image-text', 'image-text.css', 'Image alongside text with configurable ratio'],
                    ['cards', 'cards.css', 'Grid of feature or content cards'],
                    ['blog', 'blog.css', 'Paginated blog post listing'],
                    ['article', 'article.css', 'Blog article with cover image and metadata'],
                    ['slideshow', 'slideshow.css', 'Animated image carousel'],
                    ['video', 'video.css', 'Responsive video player with poster'],
                    ['audio', '—', 'Audio player with transcription support'],
                    ['code', 'prism.css', 'Syntax-highlighted code blocks'],
                    ['table', 'table.css', 'Responsive data tables'],
                    ['pricing', 'pricing.css', 'Pricing plan comparison'],
                    ['questions', 'questions.css', 'FAQ accordion with structured data'],
                    ['contact', 'contact.css', 'Contact form with validation'],
                    ['toc', 'toc.css', 'Auto-generated table of contents'],
                    ['file', '—', 'Downloadable file link'],
                    ['html', '—', 'Custom HTML for embeds and widgets'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'PagibleAI CMS Element Data Format',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => 'Every content element in PagibleAI CMS follows a clean, consistent JSON structure that makes programmatic content creation simple:',
            ]],
            ['id' => Utils::uid(), 'type' => 'code', 'group' => 'main', 'data' => [
                'language' => ['value' => 'json'],
                'text' => "{\n  \"id\": \"abc123\",\n  \"type\": \"hero\",\n  \"group\": \"main\",\n  \"data\": {\n    \"title\": \"PagibleAI CMS\",\n    \"text\": \"The smartest CMS for Laravel.\",\n    \"url\": \"/get-started\",\n    \"button\": \"Try It Free\"\n  }\n}",
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Using Elements in PagibleAI CMS Pages',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Add elements to a page's `content` array. PagibleAI CMS renders them in order, loading only the CSS required for each element type — keeping pages lightweight and fast.",
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 3,
                'title' => 'Element Groups',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Elements belong to named groups like `main`, `footer`, or `sidebar`. PagibleAI CMS renders each group in the corresponding template section.",
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 4,
                'title' => 'Default Groups',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "The `main` group is rendered in the primary content area. Additional groups like `footer` and `header` map to their respective layout sections.",
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 5,
                'title' => 'Custom Groups',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Define custom groups in your theme layout to place elements in sidebars, modals, or any other container.",
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 6,
                'title' => 'Group Priority',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Elements render in array order within each group. Use this to control visual stacking.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Feature Availability by Plan',
                'header' => 'col',
                'table' => [
                    ['Starter', 'Pro', 'Enterprise'],
                    ['5 pages', 'Unlimited', 'Unlimited'],
                    ['Community support', 'Priority support', 'Dedicated support'],
                    ['Core themes', 'Custom themes', 'Custom + white-label'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Element Properties Reference',
                'header' => 'row+col',
                'table' => [
                    ['Property', 'hero', 'cards', 'pricing'],
                    ['title', 'Required', 'Required', 'Required'],
                    ['text', 'Optional', 'Optional', 'Optional'],
                    ['file', 'Optional', 'Per card', 'No'],
                ],
            ]],
        ], $docs, [$fileId] );

        return $this;
    }
}
