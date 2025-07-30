<?php

namespace Tests;

use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;
use Database\Seeders\CmsSeeder;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;


class GraphqlElementTest extends TestAbstract
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


    public function testElement()
    {
        $this->seed( CmsSeeder::class );

        $element = Element::firstOrFail();

        $attr = collect($element->getAttributes())->except(['tenant_id'])->all();
        $expected = ['id' => (string) $element->id] + $attr;

        $this->expectsDatabaseQueryCount( 4 );
        $response = $this->actingAs( $this->user )->graphQL( "{
            element(id: \"{$element->id}\") {
                id
                lang
                type
                name
                lang
                data
                editor
                created_at
                updated_at
                deleted_at
                bypages {
                    id
                }
                byversions {
                    published
                }
                versions {
                    published
                }
            }
        }" )->assertJson( [
            'data' => [
                'element' => $expected,
            ]
        ] );
    }


    public function testElements()
    {
        $this->seed( CmsSeeder::class );

        $element = Element::where( 'type', 'footer' )->get()->first();

        $attr = collect($element->getAttributes())->except(['tenant_id'])->all();
        $expected = [['id' => (string) $element->id] + $attr];

        $this->expectsDatabaseQueryCount( 2 );
        $response = $this->actingAs( $this->user )->graphQL( '{
            elements(filter: {
                id: ["' . $element->id . '"]
                lang: "en"
                type: "footer"
                name: "Shared"
                editor: "seeder"
                data: "Powered by Laravel"
                any: "Laravel"
            }, sort: [{column: TYPE, order: ASC}], first: 10, trashed: WITH, publish: DRAFT) {
                data {
                    id
                    lang
                    type
                    name
                    lang
                    data
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
                'elements' => [
                    'data' => $expected,
                    'paginatorInfo' => [
                        'currentPage' => 1,
                        'lastPage' => 1,
                    ]
                ],
            ]
        ] );
    }


    public function testElementsPublished()
    {
        $this->seed( CmsSeeder::class );

        $this->expectsDatabaseQueryCount( 1 );
        $response = $this->actingAs( $this->user )->graphQL( '{
            elements(publish: PUBLISHED) {
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
                'elements' => [
                    'data' => [],
                    'paginatorInfo' => [
                        'currentPage' => 1,
                        'lastPage' => 1,
                    ]
                ],
            ]
        ] );
    }


    public function testElementsScheduled()
    {
        $this->seed( CmsSeeder::class );

        $element = Element::where( 'type', 'footer' )->get()->first();

        $this->expectsDatabaseQueryCount( 2 );
        $response = $this->actingAs( $this->user )->graphQL( '{
            elements(publish: SCHEDULED) {
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
                'elements' => [
                    'data' => [[
                        'id' => (string) $element->id,
                    ]],
                    'paginatorInfo' => [
                        'currentPage' => 1,
                        'lastPage' => 1,
                    ]
                ],
            ]
        ] );
    }


    public function testElementVersions()
    {
        $this->seed( CmsSeeder::class );

        $element = Element::firstOrFail();

        $this->expectsDatabaseQueryCount( 3 );
        $response = $this->actingAs( $this->user )->graphQL( '{
            element(id: "' . $element->id . '") {
                id
                type
                versions {
                    lang
                    data
                    files {
                        path
                    }
                    editor
                }
            }
        }' )->assertJson( [
            'data' => [
                'element' => [
                    'id' => $element->id,
                    'type' => $element->type,
                    'versions' => [
                        [
                            'lang' => $element->lang,
                            'data' => '{"lang":"en","type":"footer","name":"Shared footer","data":{"text":"Powered by Laravel CMS!"}}',
                            'files' => [],
                            'editor' => 'seeder'
                        ],
                    ],
                ],
            ]
        ] );
    }


    public function testAddElement()
    {
        $this->seed( CmsSeeder::class );

        $file = File::firstOrFail();

        $this->expectsDatabaseQueryCount( 6 );
        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                addElement(input: {
                    type: "test"
                    lang: "en"
                    data: "{\\"key\\":\\"value\\"}"
                }, files: ["' . $file->id . '"]) {
                    type
                    lang
                    data
                    editor
                    bypages {
                        id
                    }
                    latest {
                        data
                    }
                }
            }
        ' );

        $response->assertJson( [
            'data' => [
                'addElement' => [
                    'type' => 'test',
                    'lang' => 'en',
                    'data' => '{"key":"value"}',
                    'editor' => 'Test editor',
                    'bypages' => [],
                    'latest' => [
                        'data' => '{"type":"test","lang":"en","data":{"key":"value"}}',
                    ]
                ],
            ]
        ] );
    }


    public function testSaveElement()
    {
        $this->seed( CmsSeeder::class );

        $file = File::firstOrFail();
        $element = Element::firstOrFail();

        $this->expectsDatabaseQueryCount( 6 );
        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                saveElement(id: "' . $element->id . '", input: {
                    type: "test"
                    lang: "de"
                    data: "{\\"key\\":\\"value\\"}"
                }, files: ["' . $file->id . '"]) {
                    id
                    type
                    lang
                    data
                    editor
                    latest {
                        lang
                        data
                        published
                        publish_at
                        editor
                    }
                }
            }
        ' );

        $element = Element::find( $element->id );

        $response->assertJson( [
            'data' => [
                'saveElement' => [
                    'id' => $element->id,
                    'type' => 'footer',
                    'lang' => 'en',
                    'data' => '{"type":"footer","data":{"text":"Powered by Laravel CMS"}}',
                    'editor' => 'seeder',
                    'latest' => [
                        'lang' => 'de',
                        'data' => '{"type":"test","lang":"de","data":{"key":"value"}}',
                        'published' => false,
                        'publish_at' => null,
                        'editor' => 'Test editor',
                    ],
               ],
            ]
        ] );
    }


    public function testDropElement()
    {
        $this->seed( CmsSeeder::class );

        $element = Element::firstOrFail();

        $this->expectsDatabaseQueryCount( 3 );
        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                dropElement(id: ["' . $element->id . '"]) {
                    id
                    deleted_at
                }
            }
        ' );

        $element = Element::withTrashed()->find( $element->id );

        $response->assertJson( [
            'data' => [
                'dropElement' => [[
                    'id' => $element->id,
                    'deleted_at' => $element->deleted_at,
                ]],
            ]
        ] );
    }


    public function testKeepElement()
    {
        $this->seed( CmsSeeder::class );

        $element = Element::firstOrFail();
        $element->delete();

        $this->expectsDatabaseQueryCount( 3 );
        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                keepElement(id: ["' . $element->id . '"]) {
                    id
                    deleted_at
                }
            }
        ' );

        $element = Element::find( $element->id );

        $response->assertJson( [
            'data' => [
                'keepElement' => [[
                    'id' => $element->id,
                    'deleted_at' => null,
                ]],
            ]
        ] );
    }


    public function testPubElement()
    {
        $this->seed( CmsSeeder::class );

        $element = Element::firstOrFail();

        $this->expectsDatabaseQueryCount( 7 );
        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                pubElement(id: ["' . $element->id . '"]) {
                    id
                }
            }
        ' );

        $element = Element::where( 'id', $element->id )->firstOrFail();

        $response->assertJson( [
            'data' => [
                'pubElement' => [[
                    'id' => (string) $element->id
                ]],
            ]
        ] );
    }


    public function testPubElementAt()
    {
        $this->seed( CmsSeeder::class );

        $element = Element::firstOrFail();

        $this->expectsDatabaseQueryCount( 4 );
        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                pubElement(id: ["' . $element->id . '"], at: "2025-01-01 00:00:00") {
                    id
                }
            }
        ' );

        $element = Element::where( 'id', $element->id )->firstOrFail();

        $response->assertJson( [
            'data' => [
                'pubElement' => [[
                    'id' => (string) $element->id
                ]],
            ]
        ] );
    }


    public function testPurgeElement()
    {
        $this->seed( CmsSeeder::class );

        $element = Element::firstOrFail();

        $this->expectsDatabaseQueryCount( 3 );
        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                purgeElement(id: "' . $element->id . '") {
                    id
                }
            }
        ' );

        $this->assertNull( Element::where('id', $element->id)->first() );
    }
}
