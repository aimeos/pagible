<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;


class CmsSeeder extends Seeder
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
        \Aimeos\Cms\Tenancy::$callback = function() {
            return 'demo';
        };

        File::where('tenant_id', 'demo')->forceDelete();
        Version::where('tenant_id', 'demo')->forceDelete();
        Element::where('tenant_id', 'demo')->forceDelete();
        Page::where('tenant_id', 'demo')->forceDelete();

        $home = $this->home();

        $this->addBlog( $home )
            ->addDev( $home )
            ->addHidden( $home )
            ->addDisabled( $home );
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

            $this->file = $file->id;
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

            $this->element = $element->id;
        }

        return $this->element;
    }


    protected function home() : Page
    {
        $elementId = $this->element();

        $page = Page::forceCreate([
            'lang' => 'en',
            'name' => 'Home',
            'title' => 'Home | Laravel CMS',
            'path' => '',
            'tag' => 'root',
            'domain' => 'mydomain.tld',
            'status' => 1,
            'cache' => 5,
            'editor' => 'seeder',
            'meta' => ['meta' => ['type' => 'meta', 'data' => ['text' => 'Laravel CMS is outstanding']]],
            'config' => ['test' => ['type' => 'test', 'data' => ['key' => 'value']]],
            'content' => [
                ['type' => 'heading', 'text' => 'Welcome to Laravel CMS'],
                ['type' => 'ref', 'id' => $elementId]
            ],
        ]);
        $page->versions()->forceCreate([
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
                'meta' => ['type' => 'meta', 'data' => ['text' => 'Laravel CMS is outstanding']],
                'config' => ['test' => ['type' => 'test', 'data' => ['key' => 'value']]],
                'content' => [
                    ['type' => 'heading', 'text' => 'Welcome to Laravel CMS'],
                    ['type' => 'ref', 'id' => $elementId]
                ],
            ],
            'published' => true,
            'editor' => 'seeder',
        ]);
        $page->elements()->attach( $elementId );

        return $page;
    }


    protected function addBlog( Page $home )
    {
        $elementId = $this->element();

        $page = Page::forceCreate([
            'name' => 'Blog',
            'title' => 'Blog | Laravel CMS',
            'path' => 'blog',
            'tag' => 'blog',
            'status' => 1,
            'editor' => 'seeder',
            'content' => [
                ['type' => 'blog', 'data' => ['text' => 'Blog example']],
                ['type' => 'ref', 'id' => $elementId]
            ],
        ]);
        $page->appendToNode( $home )->save();
        $page->elements()->attach( $elementId );

        $page->versions()->forceCreate([
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
                    ['type' => 'ref', 'id' => $elementId]
                ],
            ],
            'published' => true,
            'editor' => 'seeder',
        ]);

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
            ['type' => 'heading', 'data' => ['level' => 2, 'text' => 'Rethink content management!']],
            ['type' => 'paragraph', 'data' => ['text' => 'Laravel CMS is exceptional in every way. Headless and API-first!']],
            ['type' => 'heading', 'data' => ['level' => 2, 'text' => 'API first!']],
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
            ['type' => 'ref', 'id' => $elementId],
        ];

        $data = [
            'name' => 'Welcome to Laravel CMS',
            'title' => 'Welcome to Laravel CMS | Laravel CMS',
            'path' => 'welcome-to-laravelcms',
            'tag' => 'article',
            'status' => 1,
            'editor' => 'seeder'
        ];

        $page = Page::forceCreate($data + ['content' => $content]);
        $page->appendToNode( $blog )->save();
        $page->elements()->attach( $elementId );
        $page->files()->attach( $fileId );

        $version = $page->versions()->forceCreate([
            'data' => $data,
            'aux' => [
                'content' => $content,
            ],
            'published' => true,
            'editor' => 'seeder',
        ]);
        $version->files()->attach( $fileId );

        return $this;
    }


    protected function addDev( Page $home )
    {
        $elementId = $this->element();
        $fileId = $this->file();

        $page = Page::forceCreate([
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
                'type' => 'ref', 'id' => $elementId
            ]]
        ]);
        $page->appendToNode( $home )->save();
        $page->elements()->attach( $elementId );
        $page->files()->attach( $fileId );


        $page->versions()->forceCreate([
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
                    'type' => 'ref', 'id' => $elementId
                ]]
            ],
            'published' => true,
            'editor' => 'seeder',
        ]);

        return $this;
    }


    protected function addDisabled( Page $home )
    {
        $page = Page::forceCreate([
            'name' => 'Disabled',
            'title' => 'Disabled page | Laravel CMS',
            'path' => 'disabled',
            'tag' => 'disabled',
            'status' => 0,
            'editor' => 'seeder',
        ]);
        $page->appendToNode( $home )->save();

        $version = $page->versions()->forceCreate([
            'data' => [
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

        $child = Page::forceCreate([
            'name' => 'Disabled child',
            'title' => 'Disabled child | Laravel CMS',
            'path' => 'disabled-child',
            'tag' => 'disabled-child',
            'status' => 1,
            'editor' => 'seeder',
        ]);
        $child->appendToNode( $page )->save();

        $version = $page->versions()->forceCreate([
            'data' => [
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

        return $this;
    }


    protected function addHidden( Page $home )
    {
        $page = Page::forceCreate([
            'name' => 'Hidden',
            'title' => 'Hidden page | Laravel CMS',
            'path' => 'hidden',
            'tag' => 'hidden',
            'status' => 2,
            'editor' => 'seeder',
        ]);
        $page->appendToNode( $home )->save();

        $version = $page->versions()->forceCreate([
            'data' => [
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

        return $this;
    }
}
