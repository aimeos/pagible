<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Resource;


class TestSeeder extends Seeder
{
    private string $element;
    private string $file;


    /**
     * Seed the CMS database.
     *
     * @return void
     */
    public function run()
    {
        // Resolve the tenant configured by the caller, not a stale scoped instance.
        app()->forgetInstance( \Aimeos\Cms\Tenancy::class );

        Page::withoutSyncingToSearch( function() {
            Element::withoutSyncingToSearch( function() {
                File::withoutSyncingToSearch( function() {
                    $home = $this->home();

                    $this->addBlog( $home )
                        ->addDev( $home )
                        ->addHidden( $home )
                        ->addDisabled( $home );
                } );
            } );
        } );

        if( config('scout.driver') !== 'collection' ) {
            Page::query()->searchable();
            Element::query()->searchable();
            File::query()->searchable();
        }
    }


    protected function file() : string
    {
        if( !isset( $this->file ) )
        {
            $file = File::forceCreate( [
                'mime' => 'image/jpeg',
                'lang' => 'en',
                'name' => 'Test image',
                'path' => 'https://picsum.photos/id/0/1500/1000',
                'previews' => ["500" => "https://picsum.photos/id/0/500/333", "1000" => "https://picsum.photos/id/0/1000/666"],
                'description' => [
                    'en' => 'Test file description',
                ],
                'editor' => 'seeder',
            ] );

            $version = $file->versions()->forceCreate([
                'lang' => 'en',
                'data' => [
                    'mime' => 'image/jpeg',
                    'lang' => 'en',
                    'name' => 'Test image',
                    'path' => 'https://picsum.photos/id/0/1500/1000',
                    'previews' => ["500" => "https://picsum.photos/id/0/500/333", "1000" => "https://picsum.photos/id/0/1000/666"],
                    'description' => [
                        'en' => 'Test file description',
                        'de' => 'Beschreibung der Testdatei',
                    ],
                ],
                'publish_at' => '2025-01-01 00:00:00',
                'published' => false,
                'editor' => 'seeder',
            ]);

            $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $this->file = $file->refresh()->id;

            $file2 = File::forceCreate( [
                'mime' => 'image/tiff',
                'lang' => 'en',
                'name' => 'Test file',
                'path' => 'https://picsum.photos/id/0/1500/1000',
                'description' => [
                    'en' => 'Test TIFF file description',
                ],
                'editor' => 'seeder',
            ] );

            $version = $file2->versions()->forceCreate([
                'lang' => 'en',
                'data' => [
                    'mime' => 'image/tiff',
                    'lang' => 'en',
                    'name' => 'Test file',
                    'path' => 'https://picsum.photos/id/0/200/200',
                    'description' => [
                        'en' => 'Test TIFF file description',
                    ],
                ],
                'published' => true,
                'editor' => 'seeder',
            ]);

            $file2->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        }

        return $this->file;
    }


    protected function element() : string
    {
        if( !isset( $this->element ) )
        {
            $element = Element::forceCreate([
                'lang' => 'en',
                'type' => 'footer',
                'name' => 'Shared footer',
                'data' => ['type' => 'footer', 'data' => ['text' => 'Powered by Laravel CMS']],
                'editor' => 'seeder',
            ]);

            $version = $element->versions()->forceCreate([
                'lang' => 'en',
                'data' => ['lang' => 'en', 'type' => 'footer', 'name' => 'Shared footer', 'data' => ['text' => 'Powered by Laravel CMS!']],
                'publish_at' => '2025-01-01 00:00:00',
                'published' => false,
                'editor' => 'seeder',
            ]);

            $element->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $this->element = $element->refresh()->id;
        }

        return $this->element;
    }


    protected function home() : Page
    {
        $elementId = $this->element();

        $page = Resource::insertPage( ( new Page() )->forceFill( [
            'lang' => 'en',
            'name' => 'Home',
            'title' => 'Home | Laravel CMS',
            'path' => '',
            'tag' => 'root',
            'domain' => 'mydomain.tld',
            'status' => 1,
            'cache' => 5,
            'editor' => 'seeder',
            'meta' => ['meta' => ['type' => 'meta', 'data' => ['text' => 'Laravel CMS is outstanding'], 'files' => []]],
            'config' => ['test' => ['type' => 'test', 'data' => ['key' => 'value'], 'files' => []]],
            'content' => [
                ['type' => 'heading', 'data' => ['title' => 'Welcome to Laravel CMS']],
                ['type' => 'reference', 'refid' => $elementId, 'group' => 'footer']
            ],
        ] ) );
        $version = $page->versions()->forceCreate([
            'lang' => 'en',
            'data' => [
                'name' => 'Home',
                'title' => 'Home | Laravel CMS',
                'path' => '',
                'to' => '',
                'tag' => 'root',
                'domain' => 'mydomain.tld',
                'theme' => '',
                'type' => '',
                'status' => 1,
                'cache' => 5,
                'editor' => 'seeder',
            ],
            'aux' => [
                'meta' => ['meta' => ['type' => 'meta', 'data' => ['text' => 'Laravel CMS is outstanding'], 'files' => []]],
                'config' => ['test' => ['type' => 'test', 'data' => ['key' => 'value'], 'files' => []]],
                'content' => [
                    ['type' => 'heading', 'data' => ['title' => 'Welcome to Laravel CMS']],
                    ['type' => 'reference', 'refid' => $elementId, 'group' => 'footer']
                ],
            ],
            'published' => true,
            'editor' => 'seeder',
        ]);
        Resource::updatePage( $page, ['latest_id' => $version->id] );
        $version->elements()->attach( $elementId );
        $page->elements()->attach( $elementId );

        return $page;
    }


    protected function addBlog( Page $home )
    {
        $elementId = $this->element();

        $page = Resource::insertPage( ( new Page() )->forceFill( [
            'lang' => 'en',
            'name' => 'Blog',
            'title' => 'Blog | Laravel CMS',
            'path' => 'blog',
            'tag' => 'blog',
            'status' => 1,
            'editor' => 'seeder',
            'content' => [
                ['type' => 'blog', 'data' => ['text' => 'Blog example']],
                ['type' => 'reference', 'refid' => $elementId, 'group' => 'footer']
            ],
        ] ), parent: $home->id );

        $version = $page->versions()->forceCreate([
            'lang' => 'en',
            'data' => [
                'name' => 'Blog',
                'title' => 'Blog | Laravel CMS',
                'path' => 'blog',
                'tag' => 'blog',
                'status' => 1,
                'editor' => 'seeder',
            ],
            'aux' => [
                'content' => [
                    ['type' => 'blog', 'data' => ['text' => 'Blog example']],
                    ['type' => 'reference', 'refid' => $elementId, 'group' => 'footer']
                ],
            ],
            'published' => true,
            'editor' => 'seeder',
        ]);
        Resource::updatePage( $page, ['latest_id' => $version->id] );
        $version->elements()->attach( $elementId );
        $page->elements()->attach( $elementId );

        return $this->addBlogArticle( $page );
    }


    protected function addBlogArticle( Page $blog )
    {
        $elementId = $this->element();
        $fileId = $this->file();

        $content = [
            [
                'type' => 'article',
                'data' => [
                    'title' => 'Welcome to Laravel CMS',
                    'cover' => ['id' => $fileId, 'type' => 'file'],
                    'intro' => 'A new light-weight Laravel CMS is here!',
                    'text' => 'Laravel CMS is lightweight, lighting fast, easy to use, fully customizable and scalable from one-pagers to millions of pages',
                ]
            ],
            ['type' => 'heading', 'data' => ['level' => 2, 'title' => 'Rethink content management!']],
            ['type' => 'paragraph', 'data' => ['text' => 'Laravel CMS is exceptional in every way. Headless and API-first!']],
            ['type' => 'heading', 'data' => ['level' => 2, 'title' => 'API first!']],
            ['type' => 'paragraph', 'data' => [
                'text' => 'Use GraphQL for editing everything after login:

```graphql
mutation {
  cmsLogin(email: "editor@example.org", password: "secret") {
    name
    email
  }
}
```'            ],
            ],
            ['type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        $data = [
            'name' => 'Welcome to Laravel CMS',
            'title' => 'Welcome to Laravel CMS | Laravel CMS',
            'path' => 'welcome-to-laravelcms',
            'tag' => 'article',
            'lang' => 'en',
            'status' => 1,
            'editor' => 'seeder'
        ];

        $page = Resource::insertPage( ( new Page() )->forceFill( $data + ['content' => $content] ), parent: $blog->id );

        $version = $page->versions()->forceCreate([
            'data' => $data,
            'aux' => [
                'content' => $content,
            ],
            'published' => true,
            'editor' => 'seeder',
        ]);
        $version->files()->attach( $fileId );
        $version->elements()->attach( $elementId );
        Resource::updatePage( $page, ['latest_id' => $version->id] );
        $page->elements()->attach( $elementId );
        $page->files()->attach( $fileId );

        return $this;
    }


    protected function addDev( Page $home )
    {
        $elementId = $this->element();
        $fileId = $this->file();

        $page = Resource::insertPage( ( new Page() )->forceFill( [
            'lang' => 'en',
            'name' => 'Dev',
            'title' => 'For Developer | Laravel CMS',
            'path' => 'dev',
            'status' => 1,
            'editor' => 'seeder',
            'content' => [[
                'type' => 'paragraph',
                'data' => [
                    'text' => '# For Developers

This is content created using [markdown syntax](https://www.markdownguide.org/basic-syntax/)'
                ]
            ], [
                'type' => 'image-text',
                'data' => [
                    'image' => ['id' => $fileId, 'type' => 'file'],
                    'text' => 'Test image'
                ]
            ], [
                'type' => 'reference', 'refid' => $elementId, 'group' => 'footer'
            ]]
        ] ), parent: $home->id );

        $version = $page->versions()->forceCreate([
            'lang' => 'en',
            'data' => [
                'name' => 'Dev',
                'title' => 'For Developer | Laravel CMS',
                'path' => 'dev',
                'status' => 1,
                'editor' => 'seeder',
            ],
            'aux' => [
                'content' => [[
                    'type' => 'paragraph',
                    'data' => [
                        'text' => '# For Developers

This is content created using [markdown syntax](https://www.markdownguide.org/basic-syntax/)'
                    ]
                ], [
                    'type' => 'image-text',
                    'data' => [
                        'image' => ['id' => $fileId, 'type' => 'file'],
                        'text' => 'Test image'
                    ]
                ], [
                    'type' => 'reference', 'refid' => $elementId, 'group' => 'footer'
                ]]
            ],
            'published' => true,
            'editor' => 'seeder',
        ]);
        Resource::updatePage( $page, ['latest_id' => $version->id] );
        $version->elements()->attach( $elementId );
        $page->elements()->attach( $elementId );
        $page->files()->attach( $fileId );

        return $this;
    }


    protected function addDisabled( Page $home )
    {
        $page = Resource::insertPage( ( new Page() )->forceFill( [
            'lang' => 'en',
            'name' => 'Disabled',
            'title' => 'Disabled page | Laravel CMS',
            'path' => 'disabled',
            'tag' => 'disabled',
            'status' => 0,
            'editor' => 'seeder',
        ] ), parent: $home->id );

        $version = $page->versions()->forceCreate([
            'data' => [
                'lang' => 'en',
                'name' => 'Disabled',
                'title' => 'Disabled page | Laravel CMS',
                'path' => 'disabled',
                'tag' => 'disabled',
                'status' => 0,
                'editor' => 'seeder',
            ],
            'published' => true,
            'editor' => 'seeder',
        ]);
        Resource::updatePage( $page, ['latest_id' => $version->id] );

        $child = Resource::insertPage( ( new Page() )->forceFill( [
            'lang' => 'en',
            'name' => 'Disabled child',
            'title' => 'Disabled child | Laravel CMS',
            'path' => 'disabled-child',
            'tag' => 'disabled-child',
            'status' => 1,
            'editor' => 'seeder',
        ] ), parent: $page->id );

        $version = $child->versions()->forceCreate([
            'data' => [
                'lang' => 'en',
                'name' => 'Disabled child',
                'title' => 'Disabled child | Laravel CMS',
                'path' => 'disabled-child',
                'tag' => 'disabled-child',
                'status' => 1,
                'editor' => 'seeder',
            ],
            'published' => true,
            'editor' => 'seeder',
        ]);
        Resource::updatePage( $child, ['latest_id' => $version->id] );

        return $this;
    }


    protected function addHidden( Page $home )
    {
        $page = Resource::insertPage( ( new Page() )->forceFill( [
            'lang' => 'en',
            'name' => 'Hidden',
            'title' => 'Hidden page | Laravel CMS',
            'path' => 'hidden',
            'tag' => 'hidden',
            'status' => 2,
            'editor' => 'seeder',
        ] ), parent: $home->id );

        $version = $page->versions()->forceCreate([
            'data' => [
                'lang' => 'en',
                'name' => 'Hidden',
                'title' => 'Hidden page | Laravel CMS',
                'path' => 'hidden',
                'tag' => 'hidden',
                'status' => 1,
                'editor' => 'seeder',
            ],
            'publish_at' => '2025-01-01 00:00:00',
            'published' => false,
            'editor' => 'seeder',
        ]);
        Resource::updatePage( $page, ['latest_id' => $version->id] );

        return $this;
    }
}
