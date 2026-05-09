<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;
use Database\Seeders\TestSeeder;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;


class GraphqlElementTest extends GraphqlTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;
    use MakesGraphQLRequests;
    use RefreshesSchemaCache;


	protected function defineEnvironment( $app )
	{
        parent::defineEnvironment( $app );

		$app['config']->set( 'lighthouse.schema_path', __DIR__ . '/default-schema.graphql' );
		$app['config']->set( 'lighthouse.namespaces.models', ['App\Models', 'Aimeos\\Cms\\Models'] );
		$app['config']->set( 'lighthouse.namespaces.mutations', ['Aimeos\\Cms\\GraphQL\\Mutations'] );
		$app['config']->set( 'lighthouse.namespaces.directives', ['Aimeos\\Cms\\GraphQL\\Directives'] );
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

        $this->user = new \App\Models\User([
            'name' => 'Test editor',
            'email' => 'editor@testbench',
            'password' => 'secret',
            'cmsperms' => \Aimeos\Cms\Permission::all()
        ]);
    }


    public function testElement()
    {
        $this->seed( TestSeeder::class );

        $element = Element::where( 'type', 'footer' )->firstOrFail();

        $expected = [
            'id' => $element->id,
            'data' => $element->data,
            'bypages' => $element->bypages->map( fn( $item ) => ['id' => $item->id] )->all(),
            'byversions' => $element->byversions->map( fn( $item ) => ['id' => $item->id] )->all(),
            'versions' => $element->versions->map( fn( $item ) => ['published' => $item->published] )->all(),
            'created_at' => (string) $element->created_at,
            'updated_at' => (string) $element->updated_at,
        ] + collect($element->getAttributes())->except(['tenant_id', 'latest_id'])->all();

        $this->expectsDatabaseQueryCount(4);

        $response = $this->actingAs($this->user)->graphQL('{
            element(id: "' . $element->id . '") {
                id
                lang
                type
                name
                data
                editor
                created_at
                updated_at
                deleted_at
                bypages {
                    id
                }
                byversions {
                    id
                }
                versions {
                    published
                }
            }
        }');

        $elementData = $response->json('data.element');
        $elementData['data'] = json_decode( $elementData['data'] );

        $this->assertEquals($expected, $elementData);
    }


    public function testElements()
    {
        $this->seed(TestSeeder::class);

        $element = Element::where('type', 'footer')->first();

        $expected = [
            'id' => $element->id,
            'data' => $element->data,
            'created_at' => (string) $element->created_at,
            'updated_at' => (string) $element->updated_at,
        ] + collect($element->getAttributes())->except(['tenant_id', 'latest_id'])->all();

        $this->expectsDatabaseQueryCount(2);

        $response = $this->actingAs($this->user)->graphQL('{
            elements(filter: {
                id: ["' . $element->id . '"]
                lang: "en"
                type: "footer"
                editor: "seeder"
            }, sort: [{column: TYPE, order: ASC}], first: 10, trashed: WITH, publish: DRAFT) {
                data {
                    id
                    lang
                    type
                    name
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
        }');

        $elementsData = $response->json('data.elements.data');
        $elementsData[0]['data'] = json_decode( $elementsData[0]['data'] );

        // Assert elements
        $this->assertCount(1, $elementsData);
        $this->assertEquals($expected, $elementsData[0]);

        // Assert paginator info
        $paginator = $response->json('data.elements.paginatorInfo');
        $this->assertEquals(1, $paginator['currentPage']);
        $this->assertEquals(1, $paginator['lastPage']);
    }


    public function testElementsPublished()
    {
        $this->seed( TestSeeder::class );

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
        $this->seed( TestSeeder::class );

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
        $this->seed(TestSeeder::class);

        $element = Element::where( 'type', 'footer' )->firstOrFail();

        $this->expectsDatabaseQueryCount(3);

        $response = $this->actingAs($this->user)->graphQL('{
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
        }');

        $elementData = $response->json('data.element');

        // Assert scalar fields
        $this->assertEquals($element->id, $elementData['id']);
        $this->assertEquals($element->type, $elementData['type']);

        // Assert versions
        $this->assertCount(1, $elementData['versions']);
        $version = $elementData['versions'][0];

        $this->assertEquals($element->lang, $version['lang']);
        $this->assertEquals('seeder', $version['editor']);
        $this->assertEquals([], $version['files']);
    }


    public function testAddElement()
    {
        $this->seed(TestSeeder::class);

        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $element = Element::where( 'type', 'footer' )->firstOrFail();

        $this->expectsDatabaseQueryCount( 7 );

        $response = $this->actingAs($this->user)->graphQL('
            mutation {
                addElement(input: {
                    type: "heading"
                    lang: "en"
                    data: "{\\"key\\":\\"value\\"}"
                }, files: ["' . $file->id . '"]) {
                    id
                    type
                    lang
                    data
                    editor
                    created_at
                    updated_at
                    bypages {
                        id
                    }
                    latest {
                        data
                    }
                }
            }
        ');

        $result = $response->json('data.addElement');
        $element = Element::findOrFail( $result['id'] );

        $response->assertJson( [
            'data' => [
                'addElement' => [
                    'id' => $element->id,
                    'type' => 'heading',
                    'lang' => 'en',
                    'data' => json_encode( $element->data ),
                    'editor' => 'editor@testbench',
                    'created_at' => (string) $element->created_at,
                    'updated_at' => (string) $element->updated_at,
                    'bypages' => [],
                    'latest' => [
                        'data' => json_encode( [
                            'data' => ['key' => 'value'],
                            'lang' => 'en',
                            'name' => '',
                            'type' => 'heading',
                            'scheduled' => 0,
                        ] ),
                    ],
                ]
            ]
        ] );
    }


    public function testSaveElement()
    {
        $this->seed(TestSeeder::class);

        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $element = Element::where( 'type', 'footer' )->firstOrFail();

        $this->expectsDatabaseQueryCount( 8 );

        $response = $this->actingAs($this->user)->graphQL('
            mutation {
                saveElement(id: "' . $element->id . '", input: {
                    type: "heading"
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
        ');

        $element = Element::findOrFail($element->id);
        $saveElement = $response->json('data.saveElement');

        // Assert scalar fields
        $this->assertEquals($element->id, $saveElement['id']);
        $this->assertEquals('footer', $saveElement['type']);
        $this->assertEquals('en', $saveElement['lang']);
        $this->assertEquals('seeder', $saveElement['editor']);
        $this->assertEquals(['type' => 'footer', 'data' => ['text' => 'Powered by Laravel CMS']], json_decode($saveElement['data'], true));

        // Decode latest->data JSON for order-independent comparison (merged with existing)
        $latestData = json_decode($saveElement['latest']['data'] ?? null, true);
        $this->assertNull($saveElement['latest']['publish_at'] ?? null);
        $this->assertEquals('de', $saveElement['latest']['lang'] ?? null);
        $this->assertEquals(false, $saveElement['latest']['published'] ?? null);
        $this->assertEquals('editor@testbench', $saveElement['latest']['editor'] ?? null);
        $this->assertEquals('heading', $latestData['type']);
        $this->assertEquals('de', $latestData['lang']);
        $this->assertEquals(['key' => 'value'], $latestData['data']);
        $this->assertArrayHasKey('name', $latestData);
    }


    public function testAddElementBadType()
    {
        $response = $this->actingAs($this->user)->graphQL('
            mutation {
                addElement(input: {
                    type: "nonexistent"
                    lang: "en"
                    data: "{\\"key\\":\\"value\\"}"
                }) {
                    id
                }
            }
        ');

        $response->assertGraphQLErrorMessage( 'Unknown element type "nonexistent"' );
    }


    public function testSaveElementBadType()
    {
        $this->seed( TestSeeder::class );

        $element = Element::where( 'type', 'footer' )->firstOrFail();

        $response = $this->actingAs($this->user)->graphQL('
            mutation {
                saveElement(id: "' . $element->id . '", input: {
                    type: "nonexistent"
                    lang: "en"
                    data: "{\\"key\\":\\"value\\"}"
                }) {
                    id
                }
            }
        ');

        $response->assertGraphQLErrorMessage( 'Unknown element type "nonexistent"' );
    }


    public function testDropElement()
    {
        $this->seed( TestSeeder::class );

        $element = Element::where( 'type', 'footer' )->firstOrFail();

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
                    'deleted_at' => (string) $element->deleted_at,
                ]],
            ]
        ] );
    }


    public function testKeepElement()
    {
        $this->seed( TestSeeder::class );

        $element = Element::where( 'type', 'footer' )->firstOrFail();
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
        $this->seed( TestSeeder::class );

        $element = Element::where( 'type', 'footer' )->firstOrFail();

        $this->expectsDatabaseQueryCount( 7 );
        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                pubElement(id: ["' . $element->id . '"]) {
                    id
                }
            }
        ' );

        $element = Element::findOrFail( $element->id );

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
        $this->seed( TestSeeder::class );

        $element = Element::where( 'type', 'footer' )->firstOrFail();

        $this->expectsDatabaseQueryCount( 4 );
        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                pubElement(id: ["' . $element->id . '"], at: "2099-01-01 00:00:00") {
                    id
                }
            }
        ' );

        $element = Element::findOrFail( $element->id );

        $response->assertJson( [
            'data' => [
                'pubElement' => [[
                    'id' => (string) $element->id
                ]],
            ]
        ] );
    }


    public function testPubElementAtWithTime()
    {
        $this->seed( TestSeeder::class );

        $element = Element::where( 'type', 'footer' )->firstOrFail();

        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                pubElement(id: ["' . $element->id . '"], at: "2099-06-15 14:30:00") {
                    id
                }
            }
        ' );

        $response->assertJson( [
            'data' => [
                'pubElement' => [[
                    'id' => (string) $element->id
                ]],
            ]
        ] );

        $element = Element::with( 'latest' )->findOrFail( $element->id );
        $this->assertStringContainsString( '14:30:00', $element->latest->publish_at );
    }


    public function testPurgeElement()
    {
        $this->seed( TestSeeder::class );

        $element = Element::where( 'type', 'footer' )->firstOrFail();

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
