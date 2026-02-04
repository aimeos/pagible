<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
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
        $this->seed(CmsSeeder::class);

        $page = Page::where('tag', 'root')->firstOrFail();

        // Prepare expected attributes
        $attr = collect($page->getAttributes())->except(['tenant_id', '_lft', '_rgt', 'depth'])->all();
        $expected = [
            'id' => (string) $page->id,
            'has' => $page->has,
            'meta' => (array) $page->meta,
            'config' => (array) $page->config,
            'content' => (array) $page->content,
        ] + $attr;

        $this->expectsDatabaseQueryCount(1);

        $response = $this->actingAs($this->user)->graphQL("{
            page(id: \"{$page->id}\") {
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
        }");

        $pageData = $response->json('data.page');
        $pageData['meta'] = (array) json_decode($pageData['meta']);
        $pageData['config'] = (array) json_decode($pageData['config']);
        $pageData['content'] = (array) json_decode($pageData['content']);

        $this->assertEquals($expected, $pageData);
    }


    public function testPages()
    {
        $this->seed(CmsSeeder::class);

        $expected = [];
        $page = Page::where('tag', 'root')->firstOrFail();

        // Prepare expected attributes
        $attr = collect($page->getAttributes())->except(['tenant_id', '_lft', '_rgt', 'depth'])->all();
        $expected[] = [
            'id' => (string) $page->id,
            'meta' => (array) $page->meta,
            'config' => (array) $page->config,
            'content' => (array) $page->content,
        ] + $attr;

        $this->expectsDatabaseQueryCount(2);

        $response = $this->actingAs($this->user)->graphQL('{
            pages(filter: {
                id: ["' . $page->id . '"]
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
                editor: "seed"
                any: "Home"
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
        }');

        $pagesData = $response->json('data.pages.data');
        $this->assertCount(1, $pagesData);

        $pagesData[0]['meta'] = (array) json_decode($pagesData[0]['meta']);
        $pagesData[0]['config'] = (array) json_decode($pagesData[0]['config']);
        $pagesData[0]['content'] = (array) json_decode($pagesData[0]['content']);

        $this->assertEquals($expected, $pagesData);

        // Assert paginator info
        $paginator = $response->json('data.pages.paginatorInfo');
        $this->assertEquals(1, $paginator['currentPage']);
        $this->assertEquals(1, $paginator['lastPage']);
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
        $this->seed(CmsSeeder::class);

        $root = Page::where('tag', 'root')->firstOrFail();
        $expected = [];

        foreach ($root->children as $page) {
            $expected[] = [
                'id' => (string) $page->id,
                'parent_id' => (string) $page->parent_id,
            ] + collect($page->getAttributes())->except(['tenant_id', '_lft', '_rgt', 'depth'])->all();
        }

        $this->expectsDatabaseQueryCount(2);

        $response = $this->actingAs($this->user)->graphQL('{
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
        }');

        $pagesData = $response->json('data.pages.data');
        $this->assertCount(count($expected), $pagesData);

        foreach ($pagesData as $i => $actual) {
            // Assert scalar fields
            foreach (['id','related_id','parent_id','lang','path','name','title','domain','to','tag','type','theme','status','cache','editor','created_at','updated_at','deleted_at'] as $key) {
                $this->assertEquals($expected[$i][$key], $actual[$key]);
            }

            // Assert JSON-like fields decoded from response
            $this->assertEquals($root->children[$i]->meta, json_decode($actual['meta']));
            $this->assertEquals($root->children[$i]->config, json_decode($actual['config']));
            $this->assertEquals($root->children[$i]->content, json_decode($actual['content']));
        }

        // Assert paginator info
        $paginator = $response->json('data.pages.paginatorInfo');
        $this->assertEquals(1, $paginator['currentPage']);
        $this->assertEquals(1, $paginator['lastPage']);
    }


    public function testPageParent()
    {
        $this->seed( CmsSeeder::class );

        $page = Page::where('tag', 'article')->firstOrFail();

        $this->expectsDatabaseQueryCount( 3 );
        $response = $this->actingAs( $this->user )->graphQL( "{
            page(id: \"{$page->id}\") {
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

        $this->expectsDatabaseQueryCount( 3 );

        $this->actingAs( $this->user )->graphQL( "{
            page(id: \"{$page->id}\") {
                id
                children(first: 3) {
                    data {
                        path
                    }
                    paginatorInfo {
                        currentPage
                        lastPage
                    }
                }
            }
        }" )->assertJson( [
            'data' => [
                'page' => [
                    'id' => (string) $page->id,
                    'children' => [
                        'data' => [
                            ['path' => 'welcome-to-laravelcms'],
                        ],
                        'paginatorInfo' => [
                            'currentPage' => 1,
                            'lastPage' => 1,
                        ]
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
            page(id: \"{$page->id}\") {
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
        $this->seed(CmsSeeder::class);

        $page = Page::where('tag', 'root')->firstOrFail();
        $element = $page->elements()->firstOrFail();

        $this->expectsDatabaseQueryCount(2);

        $response = $this->actingAs($this->user)->graphQL("{
            page(id: \"{$page->id}\") {
                id
                versions {
                    lang
                    data
                    aux
                    editor
                }
            }
        }");

        $pageData = $response->json('data.page');

        $this->assertEquals((string)$page->id, $pageData['id']);

        $this->assertCount(1, $pageData['versions']);
        $version = $pageData['versions'][0];

        $this->assertEquals($page->lang, $version['lang']);
        $this->assertEquals('seeder', $version['editor']);

        // Decode JSON fields to arrays
        $expectedData = [
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
        ];
        $this->assertEquals($expectedData, json_decode($version['data'], true));

        $expectedAux = [
            'meta' => ['type' => 'meta', 'data' => ['text' => 'Laravel CMS is outstanding']],
            'config' => ['test' => ['type' => 'test', 'data' => ['key' => 'value']]],
            'content' => [
                ['type' => 'heading', 'data' => ['title' => 'Welcome to Laravel CMS']],
                ['type' => 'ref', 'id' => strtolower( $element->id )],
            ],
        ];
        $this->assertEquals($expectedAux, json_decode($version['aux'], true));
    }


    public function testPageSimple()
    {
        $this->seed( CmsSeeder::class );

        $page = Page::where('tag', 'disabled')->firstOrFail();

        $this->expectsDatabaseQueryCount( 6 );
        $response = $this->actingAs( $this->user )->graphQL( "{
            page(id: \"{$page->id}\") {
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
        $this->seed(CmsSeeder::class);

        $page = Page::where('tag', 'root')->firstOrFail();

        $this->expectsDatabaseQueryCount(2);

        $response = $this->actingAs($this->user)->graphQL("{
            page(id: \"{$page->id}\") {
                id
                elements {
                    lang
                    name
                    data
                }
            }
        }");

        $pageData = $response->json('data.page');

        $this->assertEquals((string)$page->id, $pageData['id']);

        $this->assertCount(1, $pageData['elements']);
        $element = $pageData['elements'][0];

        $this->assertEquals('en', $element['lang']);
        $this->assertEquals('Shared footer', $element['name']);

        // Decode JSON field to array
        $expectedData = [
            'type' => 'footer',
            'data' => [
                'text' => 'Powered by Laravel CMS',
            ],
        ];
        $this->assertEquals($expectedData, json_decode($element['data'], true));
    }


    public function testAddPage()
    {
        $this->seed( CmsSeeder::class );

        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $element = Element::where( 'type', 'footer' )->firstOrFail();

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

        $attr = collect($page->getAttributes())->except(['tenant_id', '_lft', '_rgt', 'depth'])->all();
        $expected = [
            'id' => strtolower( $page->id ),
            'parent_id' => null,
            'status' => 0,
            'cache' => 0,
        ] + $attr;

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

        $this->expectsDatabaseQueryCount( 8 );
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
                'addPage' => ['id' => strtolower( $page->id ), 'parent_id' => $root->id],
            ]
        ] );
    }


    public function testAddPageChildRef()
    {
        $this->seed( CmsSeeder::class );

        $root = Page::where('tag', 'root')->firstOrFail();
        $ref = Page::where('tag', 'blog')->firstOrFail();

        $this->expectsDatabaseQueryCount( 8 );
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
                'addPage' => ['id' => strtolower( $page->id ), 'parent_id' => $root->id],
            ]
        ] );
        $this->assertEquals( 2, $page->_lft );
        $this->assertEquals( 3, $page->_rgt );
    }


    public function testMovePage()
    {
        $this->seed( CmsSeeder::class );

        $blog = Page::where('tag', 'blog')->firstOrFail();

        $this->expectsDatabaseQueryCount( 8 );
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

        $this->expectsDatabaseQueryCount( 10 );
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

        $this->expectsDatabaseQueryCount( 10 );
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
        $this->seed(CmsSeeder::class);

        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $element = Element::where( 'type', 'footer' )->firstOrFail();
        $root = Page::where('tag', 'root')->firstOrFail();

        $this->expectsDatabaseQueryCount( 12 );

        $response = $this->actingAs($this->user)->graphQL('
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
                    content: "[{\"type\":\"heading\",\"data\":{\"title\":\"Welcome to Laravel CMS\"}}]"
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
                    versions {
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
        ');

        $page = Page::findOrFail( $root->id );
        $element = $page->elements()->firstOrFail();

        $savePage = $response->json('data.savePage');
print_r( $savePage );
print_r( $page->latest );
print_r( $page->versions );

        // Assert basic fields
        $this->assertEquals((string)$root->id, $savePage['id']);
        $this->assertEquals(null, $savePage['parent_id']);
        $this->assertEquals('en', $savePage['lang']);
        $this->assertEquals('Home', $savePage['name']);
        $this->assertEquals('Home | Laravel CMS', $savePage['title']);

        // Assert JSON fields as arrays (order-independent)
        $expectedLatestData = [
            'name' => 'test',
            'title' => 'Test page',
            'path' => 'test',
            'to' => '/to/page',
            'tag' => 'test',
            'domain' => 'test.com',
            'theme' => '',
            'type' => '',
            'status' => 0,
            'cache' => 5,
            'editor' => 'seeder',
            'lang' => 'de',
        ];
        $this->assertEquals($expectedLatestData, json_decode($savePage['latest']['data'] ?? null, true));

        $expectedLatestAux = [
            'meta' => ['canonical' => 'to/page'],
            'config' => ['key' => 'test'],
            'content' => [['type' => 'heading', 'data' => ['title' => 'Welcome to Laravel CMS']]],
        ];
        $this->assertEquals($expectedLatestAux, json_decode($savePage['latest']['aux'] ?? null, true));

        $expectedPublishedData = [
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
        ];
        $this->assertEquals($expectedPublishedData, json_decode($savePage['published']['data'] ?? null, true));

        $expectedPublishedAux = [
            'meta' => ['type' => 'meta', 'data' => ['text' => 'Laravel CMS is outstanding']],
            'config' => ['test' => ['type' => 'test', 'data' => ['key' => 'value']]],
            'content' => [
                ['type' => 'heading', 'data' => ['title' => 'Welcome to Laravel CMS']],
                ['type' => 'ref', 'id' => strtolower( $element->id )],
            ],
        ];
        $this->assertEquals($expectedPublishedAux, json_decode($savePage['published']['aux'] ?? null, true));
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

        $page = Page::withTrashed()->findOrFail( $root->id );

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

        $page = Page::findOrFail( $root->id );

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

        $this->expectsDatabaseQueryCount( 11 );

        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                pubPage(id: ["' . $page->id . '"]) {
                    id
                }
            }
        ' );

        $page = Page::findOrFail( $page->id );

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

        $page = Page::findOrFail( $page->id );

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
