<?php

namespace Tests;

use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;
use Database\Seeders\CmsSeeder;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;


class GraphqlPageTest extends TestAbstract
{
    use MakesGraphQLRequests;
    use RefreshesSchemaCache;


	protected function defineEnvironment( $app )
	{
        parent::defineEnvironment( $app );

		$app['config']->set( 'lighthouse.schema_path', __DIR__ . '/default-schema.graphql' );
		$app['config']->set( 'lighthouse.namespaces.models', ['App\Models', 'Aimeos\\Cms\\Models'] );
		$app['config']->set( 'lighthouse.namespaces.mutations', ['Aimeos\\Cms\\GraphQL\\Mutations'] );
    }


	protected function getPackageProviders( $app )
	{
		return array_merge( parent::getPackageProviders( $app ), [
			'Nuwave\Lighthouse\LighthouseServiceProvider'
		] );
	}


    protected function setUp(): void
    {
        parent::setUp();
        $this->bootRefreshesSchemaCache();

        $this->user = \App\Models\User::create([
            'name' => 'Test editor',
            'email' => 'editor@testbench',
            'password' => 'secret',
            'cmseditor' => 0x7fffffff
        ]);
    }


    public function testPage()
    {
        $this->seed( CmsSeeder::class );

        $page = Page::where('tag', 'root')->firstOrFail();

        $attr = collect($page->getAttributes())->except(['tenant_id', '_lft', '_rgt'])->all();
        $expected = ['id' => (string) $page->id] + $attr;

        $this->expectsDatabaseQueryCount( 1 );
        $response = $this->actingAs( $this->user )->graphQL( "{
            page(id: {$page->id}) {
                id
                related_id
                parent_id
                lang
                path
                name
                title
                domain
                to
                tag
                type
                theme
                meta
                config
                content
                status
                cache
                editor
                has
                created_at
                updated_at
                deleted_at
            }
        }" )->assertJson( [
            'data' => [
                'page' => $expected,
            ]
        ] );
    }


    public function testPages()
    {
        $this->seed( CmsSeeder::class );

        $page = Page::where('tag', 'root')->firstOrFail();

        $attr = collect($page->getAttributes())->except(['tenant_id', '_lft', '_rgt'])->all();
        $expected = [['id' => (string) $page->id] + $attr];

        $this->expectsDatabaseQueryCount( 2 );
        $response = $this->actingAs( $this->user )->graphQL( '{
            pages(filter: {
                id: [' . $page->id . ']
                parent_id: null
                lang: "en"
                name: "Home"
                title: "Home"
                path: ""
                tag: "root"
                to: ""
                domain: "mydomain.tld"
                type: ""
                theme: ""
                cache: 5
                status: 1
                meta: "Laravel"
                config: "value"
                content: "Welcome"
                editor: "seed"
                any: "Laravel"
            }, first: 10, page: 1, trashed: WITH, publish: PUBLISHED) {
                data {
                    id
                    related_id
                    parent_id
                    lang
                    path
                    name
                    title
                    domain
                    to
                    tag
                    type
                    theme
                    meta
                    config
                    content
                    status
                    cache
                    editor
                    created_at
                    updated_at
                    deleted_at
                }
                paginatorInfo {
                    currentPage
                    lastPage
                }
            }
        }' )->assertJson( [
            'data' => [
                'pages' => [
                    'data' => $expected,
                    'paginatorInfo' => [
                        'currentPage' => 1,
                        'lastPage' => 1,
                    ]
                ],
            ]
        ] );
    }


    public function testPagesDraft()
    {
        $this->seed( CmsSeeder::class );

        $page = Page::where('tag', 'hidden')->firstOrFail();

        $this->expectsDatabaseQueryCount( 2 );
        $response = $this->actingAs( $this->user )->graphQL( '{
            pages(publish: DRAFT) {
                data {
                    id
                }
                paginatorInfo {
                    currentPage
                    lastPage
                }
            }
        }' )->assertJson( [
            'data' => [
                'pages' => [
                    'data' => [[
                        'id' => (string) $page->id,
                    ]],
                    'paginatorInfo' => [
                        'currentPage' => 1,
                        'lastPage' => 1,
                    ]
                ],
            ]
        ] );
    }


    public function testPagesScheduled()
    {
        $this->seed( CmsSeeder::class );

        $page = Page::where('tag', 'hidden')->firstOrFail();

        $this->expectsDatabaseQueryCount( 2 );
        $response = $this->actingAs( $this->user )->graphQL( '{
            pages(publish: SCHEDULED) {
                data {
                    id
                }
                paginatorInfo {
                    currentPage
                    lastPage
                }
            }
        }' )->assertJson( [
            'data' => [
                'pages' => [
                    'data' => [[
                        'id' => (string) $page->id,
                    ]],
                    'paginatorInfo' => [
                        'currentPage' => 1,
                        'lastPage' => 1,
                    ]
                ],
            ]
        ] );
    }


    public function testPagesWithParentid()
    {
        $this->seed( CmsSeeder::class );

        $root = Page::where('tag', 'root')->firstOrFail();
        $expected = [];

        foreach( $root->children as $page )
        {
            $attr = collect($page->getAttributes())->except(['tenant_id', '_lft', '_rgt'])->all();
            $expected[] = ['id' => (string) $page->id, 'parent_id' => (string) $page->parent_id] + $attr;
        }

        $this->expectsDatabaseQueryCount( 2 );
        $response = $this->actingAs( $this->user )->graphQL( '{
            pages(filter: {
                parent_id: "' . $root->id . '"
            }, first: 10, page: 1) {
                data {
                    id
                    related_id
                    parent_id
                    lang
                    path
                    name
                    title
                    domain
                    to
                    tag
                    type
                    theme
                    meta
                    config
                    content
                    status
                    cache
                    editor
                    created_at
                    updated_at
                    deleted_at
                }
                paginatorInfo {
                    currentPage
                    lastPage
                }
            }
        }' )->assertJson( [
            'data' => [
                'pages' => [
                    'data' => $expected,
                    'paginatorInfo' => [
                        'currentPage' => 1,
                        'lastPage' => 1,
                    ]
                ],
            ]
        ] );
    }


    public function testPageParent()
    {
        $this->seed( CmsSeeder::class );

        $page = Page::where('tag', 'article')->firstOrFail();

        $this->expectsDatabaseQueryCount( 3 );
        $response = $this->actingAs( $this->user )->graphQL( "{
            page(id: {$page->id}) {
                id
                parent {
                    id
                    tag
                }
            }
        }" )->assertJson( [
            'data' => [
                'page' => [
                    'id' => (string) $page->id,
                    'parent' => [
                        'id' => (string) $page->parent->id,
                        'tag' => 'blog',
                    ]
                ],
            ]
        ] );
    }


    public function testPageChildren()
    {
        $this->seed( CmsSeeder::class );

        $page = Page::where('tag', 'blog')->firstOrFail();

        $this->expectsDatabaseQueryCount( 2 );
        $response = $this->actingAs( $this->user )->graphQL( "{
            page(id: {$page->id}) {
                id
                children(first: 3) {
                    data {
                        tag
                    }
                }
            }
        }" )->assertJson( [
            'data' => [
                'page' => [
                    'id' => (string) $page->id,
                    'children' => [
                        'data' => [
                            ['tag' => 'root'],
                            ['tag' => 'blog'],
                            ['tag' => 'article'],
                        ],
                    ]
                ],
            ]
        ] );
    }


    public function testPageAncestors()
    {
        $this->seed( CmsSeeder::class );

        $page = Page::where('tag', 'article')->firstOrFail();

        $this->expectsDatabaseQueryCount( 2 );
        $response = $this->actingAs( $this->user )->graphQL( "{
            page(id: {$page->id}) {
                id
                ancestors {
                    tag
                }
            }
        }" )->assertJson( [
            'data' => [
                'page' => [
                    'id' => (string) $page->id,
                    'ancestors' => [
                        ['tag' => 'root'],
                        ['tag' => 'blog'],
                    ]
                ],
            ]
        ] );
    }


    public function testPageVersions()
    {
        $this->seed( CmsSeeder::class );

        $page = Page::where('tag', 'root')->firstOrFail();
        $element = $page->elements()->firstOrFail();

        $this->expectsDatabaseQueryCount( 2 );
        $response = $this->actingAs( $this->user )->graphQL( "{
            page(id: {$page->id}) {
                id
                versions {
                    lang
                    data
                    aux
                    editor
                }
            }
        }" )->assertJson( [
            'data' => [
                'page' => [
                    'id' => (string) $page->id,
                    'versions' => [
                        [
                            'lang' => $page->lang,
                            'data' => '{"name":"Home","title":"Home | Laravel CMS","path":"","to":"","tag":"root","domain":"mydomain.tld","theme":"","type":"","status":1,"cache":5,"editor":"seeder"}',
                            'aux' => '{"meta":{"type":"meta","data":{"text":"Laravel CMS is outstanding"}},"config":{"test":{"type":"test","data":{"key":"value"}}},"content":[{"type":"heading","text":"Welcome to Laravel CMS"},{"type":"ref","id":"' . $element->id . '"}]}',
                            'editor' => 'seeder'
                        ],
                    ],
                ],
            ]
        ] );
    }


    public function testPageSimple()
    {
        $this->seed( CmsSeeder::class );

        $page = Page::where('tag', 'disabled')->firstOrFail();

        $this->expectsDatabaseQueryCount( 5 );
        $response = $this->actingAs( $this->user )->graphQL( "{
            page(id: {$page->id}) {
                id
                ancestors {
                    id
                }
                children(first: 2) {
                    data {
                        id
                    }
                }
                elements {
                    id
                }
                versions {
                    id
                }
            }
        }" )->assertJson( [
            'data' => [
                'page' => [
                    'id' => (string) $page->id,
                    'ancestors' => [],
                    'children' => [],
                    'elements' => [],
                    'versions' => [],
                ],
            ]
        ] );
    }


    public function testPageElements()
    {
        $this->seed( CmsSeeder::class );

        $page = Page::where('tag', 'root')->firstOrFail();

        $this->expectsDatabaseQueryCount( 2 );
        $response = $this->actingAs( $this->user )->graphQL( "{
            page(id: {$page->id}) {
                id
                elements {
                    lang
                    name
                    data
                }
            }
        }" )->assertJson( [
            'data' => [
                'page' => [
                    'id' => (string) $page->id,
                    'elements' => [
                        [
                            'lang' => 'en',
                            'name' => 'Shared footer',
                            'data' => '{"type":"footer","data":{"text":"Powered by Laravel CMS"}}',
                        ],
                    ],
                ],
            ]
        ] );
    }


    public function testAddPage()
    {
        $this->seed( CmsSeeder::class );

        $file = File::firstOrFail();
        $element = Element::firstOrFail();

        $this->expectsDatabaseQueryCount( 9 );
        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                addPage(input: {
                    lang: "en"
                    path: "test"
                    name: "test"
                    domain: "test.com"
                    title: "Test page"
                    to: "/to/page"
                    tag: "test"
                    meta: "{\"canonical\":\"to\/page\"}"
                    config: "{\"key\":\"test\"}"
                    content: "[{\"type\":\"heading\",\"text\":\"Welcome to Laravel CMS\"}]"
                    status: 0
                    cache: 0
                }, elements: ["' . $element->id . '"], files: ["' . $file->id . '"]) {
                    id
                    related_id
                    parent_id
                    lang
                    path
                    domain
                    name
                    title
                    to
                    tag
                    type
                    theme
                    meta
                    config
                    content
                    status
                    cache
                    editor
                    created_at
                    updated_at
                    deleted_at
                    elements {
                        lang
                        data
                        name
                    }
                }
            }
        ' );

        $page = Page::where('tag', 'test')->where('lang', 'en')->firstOrFail();

        $attr = collect($page->getAttributes())->except(['tenant_id', '_lft', '_rgt'])->all();
        $expected = ['id' => (string) $page->id, 'parent_id' => null] + $attr;

        $response->assertJson( [
            'data' => [
                'addPage' => $expected,
            ]
        ] );
    }


    public function testAddPageChild()
    {
        $this->seed( CmsSeeder::class );

        $root = Page::where('tag', 'root')->firstOrFail();

        $this->expectsDatabaseQueryCount( 7 );
        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                addPage(input: {
                    lang: "en"
                    path: "test"
                    name: "test"
                    domain: "test.com"
                    title: "Test page"
                    to: "/to/page"
                    tag: "test"
                    meta: "{}"
                    config: "{}"
                    content: "[]"
                    status: 0
                    cache: 0
                }, parent: "' . $root->id . '") {
                    id
                    parent_id
                }
            }
        ' );

        $page = Page::where('tag', 'test')->firstOrFail();

        $response->assertJson( [
            'data' => [
                'addPage' => ['id' => (string) $page->id, 'parent_id' => $root->id],
            ]
        ] );
    }


    public function testAddPageChildRef()
    {
        $this->seed( CmsSeeder::class );

        $root = Page::where('tag', 'root')->firstOrFail();
        $ref = Page::where('tag', 'blog')->firstOrFail();

        $this->expectsDatabaseQueryCount( 7 );
        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                addPage(input: {
                    lang: "en"
                    path: "test"
                    name: "test"
                    domain: ""
                    title: "Test page"
                    to: "/to/page"
                    tag: "test"
                    meta: "{}"
                    config: "{}"
                    content: "[]"
                    status: 0
                    cache: 0
                }, parent: "' . $root->id . '", ref: "' . $ref->id .'") {
                    id
                    parent_id
                }
            }
        ' );

        $page = Page::where('tag', 'test')->firstOrFail();

        $response->assertJson( [
            'data' => [
                'addPage' => ['id' => (string) $page->id, 'parent_id' => $root->id],
            ]
        ] );
        $this->assertEquals( 2, $page->_lft );
        $this->assertEquals( 3, $page->_rgt );
    }


    public function testMovePage()
    {
        $this->seed( CmsSeeder::class );

        $blog = Page::where('tag', 'blog')->firstOrFail();

        $this->expectsDatabaseQueryCount( 7 );
        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                movePage(id: "' . $blog->id . '") {
                    id
                    parent_id
                    editor
                }
            }
        ' );

        $page = Page::where('tag', 'blog')->firstOrFail();

        $response->assertJson( [
            'data' => [
                'movePage' => [
                    'id' => (string) $page->id,
                    'parent_id' => null,
                    'editor' => 'Test editor',
                ],
            ]
        ] );
    }


    public function testMovePageParent()
    {
        $this->seed( CmsSeeder::class );

        $root = Page::where('tag', 'root')->firstOrFail();
        $article = Page::where('tag', 'article')->firstOrFail();

        $this->expectsDatabaseQueryCount( 9 );
        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                movePage(id: "' . $article->id . '", parent: "' . $root->id . '") {
                    id
                    parent_id
                }
            }
        ' );

        $page = Page::where('tag', 'article')->firstOrFail();

        $response->assertJson( [
            'data' => [
                'movePage' => [
                    'id' => (string) $page->id,
                    'parent_id' => $root->id
                ],
            ]
        ] );
    }


    public function testMovePageParentRef()
    {
        $this->seed( CmsSeeder::class );

        $root = Page::where('tag', 'root')->firstOrFail();
        $blog = Page::where('tag', 'blog')->firstOrFail();
        $article = Page::where('tag', 'article')->firstOrFail();

        $this->expectsDatabaseQueryCount( 9 );
        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                movePage(id: "' . $article->id . '", parent: "' . $root->id . '", ref: "' . $blog->id . '") {
                    id
                    parent_id
                }
            }
        ' );

        $page = Page::where('tag', 'article')->firstOrFail();

        $response->assertJson( [
            'data' => [
                'movePage' => [
                    'id' => (string) $page->id,
                    'parent_id' => $root->id
                ],
            ]
        ] );
        $this->assertEquals( 2, $page->_lft );
        $this->assertEquals( 3, $page->_rgt );
    }


    public function testSavePage()
    {
        $this->seed( CmsSeeder::class );

        $file = File::firstOrFail();
        $element = Element::firstOrFail();
        $root = Page::where('tag', 'root')->firstOrFail();

        $this->expectsDatabaseQueryCount( 11 );
        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                savePage(id: "' . $root->id . '", input: {
                    lang: "de"
                    path: "test"
                    domain: "test.com"
                    name: "test"
                    title: "Test page"
                    to: "/to/page"
                    tag: "test"
                    meta: "{\"canonical\":\"to\/page\"}"
                    config: "{\"key\":\"test\"}"
                    content: "[{\"type\":\"heading\",\"text\":\"Welcome to Laravel CMS\"}]"
                    status: 0
                    cache: 5
                }, elements: ["' . $element->id . '"], files: ["' . $file->id . '"]) {
                    id
                    parent_id
                    lang
                    path
                    domain
                    name
                    title
                    to
                    tag
                    type
                    theme
                    meta
                    config
                    content
                    status
                    cache
                    editor
                    elements {
                        lang
                        data
                        name
                    }
                    created_at
                    updated_at
                    deleted_at
                    latest {
                        lang
                        data
                        aux
                        published
                        publish_at
                        editor
                    }
                    published {
                        data
                        aux
                    }
                }
            }
        ' );

        $page = Page::where('id', $root->id)->firstOrFail();
        $element = $page->elements()->firstOrFail();

        $response->assertJson( [
            'data' => [
                'savePage' => [
                    'id' => (string) $root->id,
                    'parent_id' => null,
                    'lang' => 'en',
                    'path' => '',
                    'domain' => 'mydomain.tld',
                    'name' => 'Home',
                    'title' => 'Home | Laravel CMS',
                    'to' => '',
                    'tag' => 'root',
                    'type' => '',
                    'theme' => '',
                    'meta' => '{"meta":{"type":"meta","data":{"text":"Laravel CMS is outstanding"}}}',
                    'config' => '{"test":{"type":"test","data":{"key":"value"}}}',
                    'content' => '[{"type":"heading","text":"Welcome to Laravel CMS"},{"type":"ref","id":"' . $element->id . '"}]',
                    'status' => 1,
                    'cache' => 5,
                    'editor' => 'seeder',
                    'created_at' => (string) $root->created_at,
                    'updated_at' => (string) $page->updated_at,
                    'latest' => [
                        'lang' => 'de',
                        'data' => '{"name":"test","title":"Test page","path":"test","to":"\\/to\\/page","tag":"test","domain":"test.com","theme":"","type":"","status":0,"cache":5,"editor":"seeder","lang":"de"}',
                        'aux' => '{"meta":{"canonical":"to\\/page"},"config":{"key":"test"},"content":[{"type":"heading","text":"Welcome to Laravel CMS"}]}',
                        'published' => false,
                        'publish_at' => null,
                        'editor' => 'Test editor',

                    ],
                    'published' => [
                        'data' => '{"name":"Home","title":"Home | Laravel CMS","path":"","to":"","tag":"root","domain":"mydomain.tld","theme":"","type":"","status":1,"cache":5,"editor":"seeder"}',
                        'aux' => '{"meta":{"type":"meta","data":{"text":"Laravel CMS is outstanding"}},"config":{"test":{"type":"test","data":{"key":"value"}}},"content":[{"type":"heading","text":"Welcome to Laravel CMS"},{"type":"ref","id":"' . $element->id . '"}]}',
                    ]
                ],
            ]
        ] );
    }


    public function testDropPage()
    {
        $this->seed( CmsSeeder::class );

        $root = Page::where('tag', 'root')->firstOrFail();

        $this->expectsDatabaseQueryCount( 6 );
        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                dropPage(id: ["' . $root->id . '"]) {
                    id
                    editor
                    deleted_at
                }
            }
        ' );

        $page = Page::withTrashed()->where('id', $root->id)->firstOrFail();

        $response->assertJson( [
            'data' => [
                'dropPage' => [[
                    'id' => (string) $root->id,
                    'editor' => 'Test editor',
                    'deleted_at' => (string) $page->deleted_at,
                ]],
            ]
        ] );

        foreach( $page->children as $child ) {
            $this->assertNotNull( $child->deleted_at );
        }
    }


    public function testKeepPage()
    {
        $this->seed( CmsSeeder::class );

        $root = Page::where('tag', 'root')->firstOrFail();
        $root->delete();

        $this->expectsDatabaseQueryCount( 5 );
        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                keepPage(id: ["' . $root->id . '"]) {
                    id
                    editor
                    deleted_at
                }
            }
        ' );

        $page = Page::where('id', $root->id)->firstOrFail();

        $response->assertJson( [
            'data' => [
                'keepPage' => [[
                    'id' => (string) $root->id,
                    'editor' => 'Test editor',
                    'deleted_at' => null,
                ]],
            ]
        ] );

        foreach( $page->children as $child ) {
            $this->assertNull( $child->deleted_at );
        }
    }


    public function testPubPage()
    {
        $this->seed( CmsSeeder::class );

        $page = Page::where('tag', 'root')->firstOrFail();

        $this->expectsDatabaseQueryCount( 9 );
        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                pubPage(id: ["' . $page->id . '"]) {
                    id
                }
            }
        ' );

        $page = Page::where('id', $page->id)->firstOrFail();

        $response->assertJson( [
            'data' => [
                'pubPage' => [[
                    'id' => (string) $page->id
                ]],
            ]
        ] );
    }


    public function testPubPageAt()
    {
        $this->seed( CmsSeeder::class );

        $page = Page::where('tag', 'root')->firstOrFail();

        $this->expectsDatabaseQueryCount( 4 );
        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                pubPage(id: ["' . $page->id . '"], at: "2025-01-01 00:00:00") {
                    id
                }
            }
        ' );

        $page = Page::where('id', $page->id)->firstOrFail();

        $response->assertJson( [
            'data' => [
                'pubPage' => [[
                    'id' => (string) $page->id
                ]],
            ]
        ] );
    }


    public function testPurgePage()
    {
        $this->seed( CmsSeeder::class );

        $root = Page::where('tag', 'root')->firstOrFail();

        $this->expectsDatabaseQueryCount( 6 );
        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                purgePage(id: ["' . $root->id . '"]) {
                    id
                }
            }
        ' );

        $this->assertNull( Page::where('tag', 'root')->first() );
    }
}
