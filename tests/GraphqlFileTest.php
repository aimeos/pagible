<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Illuminate\Http\UploadedFile;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;
use Database\Seeders\CmsSeeder;
use Aimeos\Cms\Models\File;


class GraphqlFileTest extends TestAbstract
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


    public function testFile()
    {
        $this->seed(CmsSeeder::class);

        $file = File::firstOrFail();

        // Prepare expected array
        $attr = collect($file->getAttributes())->except(['tenant_id'])->all();
        $expected = [
            'id' => (string) $file->id,
            'byelements' => $file->byelements->map( fn($item) => ['id' => $item->id] )->all(),
            'bypages' => $file->bypages->map( fn($item) => ['id' => $item->id] )->all(),
            'byversions' => [['published' => true]],
            'versions' => [['published' => false]],
            'created_at' => (string) $file->getAttribute( 'created_at' ),
            'updated_at' => (string) $file->getAttribute( 'updated_at' ),
        ] + $attr;

        // Decode JSON attributes for order-independent comparison
        $expected['previews'] = json_decode($expected['previews'], true);
        $expected['description'] = json_decode($expected['description'], true);
        $expected['transcription'] = json_decode($expected['transcription'], true);

        $this->expectsDatabaseQueryCount(5);

        $response = $this->actingAs($this->user)->graphQL("{
            file(id: \"{$file->id}\") {
                id
                lang
                mime
                name
                path
                previews
                description
                transcription
                editor
                created_at
                updated_at
                deleted_at
                byelements {
                    id
                }
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
        }");

        $fileData = $response->json('data.file');

        // Assert scalar fields
        foreach (['id', 'lang', 'mime', 'name', 'path', 'editor', 'created_at', 'updated_at', 'deleted_at'] as $key) {
            $this->assertEquals($expected[$key], $fileData[$key]);
        }

        // Assert JSON fields decoded as arrays
        $this->assertEquals($expected['previews'], json_decode($fileData['previews'], true));
        $this->assertEquals($expected['description'], json_decode($fileData['description'], true));
        $this->assertEquals($expected['transcription'], json_decode($fileData['transcription'], true));

        // Assert collections
        $this->assertEquals($expected['byelements'], $fileData['byelements']);
        $this->assertEquals($expected['bypages'], $fileData['bypages']);
        $this->assertEquals($expected['byversions'], $fileData['byversions']);
        $this->assertEquals($expected['versions'], $fileData['versions']);
    }


    public function testFiles()
    {
        $this->seed(CmsSeeder::class);

        $expected = [];
        $files = File::orderBy( 'id' )->get();
        $file = $files->first();

        // Prepare expected array
        $attr = collect($file->getAttributes())->except(['tenant_id'])->all();
        $expected[] = [
            'id' => (string) $file->id,
            'created_at' => (string) $file->getAttribute( 'created_at' ),
            'updated_at' => (string) $file->getAttribute( 'updated_at' ),
        ] + $attr;

        // Decode JSON attributes for order-independent comparison
        $expected[0]['previews'] = json_decode($expected[0]['previews'], true);
        $expected[0]['description'] = json_decode($expected[0]['description'], true);
        $expected[0]['transcription'] = json_decode($expected[0]['transcription'], true);

        $this->expectsDatabaseQueryCount(2);

        $response = $this->actingAs($this->user)->graphQL('{
            files(filter: {
            }, sort: [{column: MIME, order: ASC}], first: 10, trashed: WITH) {
                data {
                    id
                    lang
                    mime
                    name
                    path
                    previews
                    description
                    transcription
                    editor
                    created_at
                    updated_at
                    deleted_at
                    byversions_count
                }
                paginatorInfo {
                    currentPage
                    lastPage
                }
            }
        }');

        $filesData = $response->json('data.files.data');

        $this->assertCount(2, $filesData);
        $actual = $filesData[0];

        // Assert scalar fields
        foreach (['id', 'lang', 'mime', 'name', 'path', 'editor', 'created_at', 'updated_at', 'deleted_at'] as $key) {
            $this->assertEquals($expected[0][$key], $actual[$key]);
        }

        // Assert JSON fields decoded as arrays
        $this->assertEquals($expected[0]['previews'], json_decode($actual['previews'], true));
        $this->assertEquals($expected[0]['description'], json_decode($actual['description'], true));
        $this->assertEquals($expected[0]['transcription'], json_decode($actual['transcription'], true));

        // Assert paginator info
        $paginator = $response->json('data.files.paginatorInfo');
        $this->assertEquals(1, $paginator['currentPage']);
        $this->assertEquals(1, $paginator['lastPage']);
    }


    public function testFilesPublished()
    {
        $this->seed( CmsSeeder::class );

        $file = File::where( 'mime', 'image/tiff' )->first();

        $this->expectsDatabaseQueryCount( 2 );
        $response = $this->actingAs( $this->user )->graphQL( '{
            files(publish: PUBLISHED) {
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
                'files' => [
                    'data' => [[
                        'id' => (string) $file->id
                    ]],
                    'paginatorInfo' => [
                        'currentPage' => 1,
                        'lastPage' => 1,
                    ]
                ],
            ]
        ] );
    }


    public function testFilesScheduled()
    {
        $this->seed( CmsSeeder::class );

        $file = File::where( 'lang', 'en' )->get()->first();

        $this->expectsDatabaseQueryCount( 2 );
        $response = $this->actingAs( $this->user )->graphQL( '{
            files(publish: SCHEDULED) {
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
                'files' => [
                    'data' => [[
                        'id' => (string) $file->id,
                    ]],
                    'paginatorInfo' => [
                        'currentPage' => 1,
                        'lastPage' => 1,
                    ]
                ],
            ]
        ] );
    }


    public function testAddFile()
    {
        $this->seed( CmsSeeder::class );

        $this->expectsDatabaseQueryCount( 3 );
        $response = $this->actingAs( $this->user )->multipartGraphQL( [
            'query' => '
                mutation($file: Upload!, $preview: Upload) {
                    addFile(file: $file, input: {
                        transcription: "{\"en\": \"Test file transcription\"}"
                        description: "{\"en\": \"Test file description\"}"
                        name: "Test file name"
                        lang: "en-GB"
                    }, preview: $preview) {
                        id
                        lang
                        mime
                        name
                        path
                        previews
                        description
                        transcription
                        editor
                        created_at
                        updated_at
                    }
                }
            ',
            'variables' => [
                'file' => null,
                'preview' => null,
            ],
        ], [
            '0' => ['variables.file'],
            '1' => ['variables.preview'],
        ], [
            '0' => UploadedFile::fake()->create('test.pdf', 500),
            '1' => UploadedFile::fake()->image('test-preview-1.jpg', 20),
        ] );

        $result = json_decode( $response->getContent() );
        $id = $result?->data?->addFile?->id;
        $file = File::findOrFail( $id );

        $response->assertJson( [
            'data' => [
                'addFile' => [
                    'id' => strtolower( $file->id ),
                    'mime' => 'application/x-empty',
                    'lang' => 'en-GB',
                    'name' => 'Test file name',
                    'path' => $file->path,
                    'previews' => json_encode( $file->previews ),
                    'description' => json_encode( $file->description ),
                    'transcription' => json_encode( $file->transcription ),
                    'editor' => 'Test editor',
                    'created_at' => (string) $file->created_at,
                    'updated_at' => (string) $file->updated_at,
                ],
            ]
        ] );
    }


    public function testSaveFile()
    {
        $this->seed(CmsSeeder::class);

        $file = File::firstOrFail();

        $this->expectsDatabaseQueryCount(7);

        $response = $this->actingAs($this->user)->multipartGraphQL([
            'query' => '
                mutation($preview: Upload) {
                    saveFile(id: "' . $file->id . '", input: {
                        transcription: "{\"en\": \"Test file transcription\"}"
                        description: "{\"en\": \"Test file description\"}"
                        name: "test file"
                        lang: "en-GB"
                    }, preview: $preview) {
                        id
                        mime
                        lang
                        name
                        path
                        previews
                        description
                        transcription
                        editor
                        latest {
                            data
                            editor
                        }
                    }
                }
            ',
            'variables' => [
                'preview' => null,
            ],
        ], [
            '0' => ['variables.preview'],
        ], [
            '0' => UploadedFile::fake()->image('test-preview-1.jpg', 200),
        ]);

        $file = File::findOrFail($file->id);
        $saveFile = $response->json('data.saveFile');

        // Cast nested objects to arrays
        $expectedLatestData = [
            'mime' => 'image/jpeg',
            'lang' => 'en-GB',
            'name' => 'test file',
            'path' => $file->path,
            'previews' => (array) $file->latest->data->previews ?? [],
            'description' => (array) $file->latest->data->description ?? [],
            'transcription' => (array) $file->latest->data->transcription ?? [],
        ];

        // Assert scalar fields
        $this->assertEquals($file->id, $saveFile['id']);
        $this->assertEquals('image/jpeg', $saveFile['mime']);
        $this->assertEquals('en', $saveFile['lang']);
        $this->assertEquals('Test image', $saveFile['name']);
        $this->assertEquals($file->path, $saveFile['path']);
        $this->assertEquals('seeder', $saveFile['editor']);

        // Assert JSON-like fields as arrays
        $this->assertEquals((array) $file->previews, json_decode($saveFile['previews'], true));
        $this->assertEquals((array) $file->description, json_decode($saveFile['description'], true));
        $this->assertEquals((array) $file->transcription, json_decode($saveFile['transcription'], true));

        // Assert latest->data as array
        $this->assertEquals($expectedLatestData, json_decode($saveFile['latest']['data'], true));
        $this->assertEquals('Test editor', $saveFile['latest']['editor']);
    }


    public function testDropFile()
    {
        $this->seed( CmsSeeder::class );

        $file = File::firstOrFail();

        $this->expectsDatabaseQueryCount( 3 );
        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                dropFile(id: ["' . $file->id . '"]) {
                    id
                    deleted_at
                }
            }
        ' );

        $file = File::withTrashed()->find( $file->id );

        $response->assertJson( [
            'data' => [
                'dropFile' => [[
                    'id' => $file->id,
                    'deleted_at' => $file->deleted_at,
                ]],
            ]
        ] );
    }


    public function testKeepFile()
    {
        $this->seed( CmsSeeder::class );

        $file = File::firstOrFail();
        $file->delete();

        $this->expectsDatabaseQueryCount( 3 );
        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                keepFile(id: ["' . $file->id . '"]) {
                    id
                    deleted_at
                }
            }
        ' );

        $file = File::find( $file->id );

        $response->assertJson( [
            'data' => [
                'keepFile' => [[
                    'id' => $file->id,
                    'deleted_at' => null,
                ]],
            ]
        ] );
    }


    public function testPubFile()
    {
        $this->seed( CmsSeeder::class );

        $file = File::firstOrFail();

        $this->expectsDatabaseQueryCount( 6 );
        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                pubFile(id: ["' . $file->id . '"]) {
                    id
                }
            }
        ' );

        $file = File::where( 'id', $file->id )->firstOrFail();

        $response->assertJson( [
            'data' => [
                'pubFile' => [[
                    'id' => (string) $file->id
                ]],
            ]
        ] );
    }


    public function testPubFileAt()
    {
        $this->seed( CmsSeeder::class );

        $file = File::firstOrFail();

        $this->expectsDatabaseQueryCount( 4 );
        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                pubFile(id: ["' . $file->id . '"], at: "2025-01-01 00:00:00") {
                    id
                }
            }
        ' );

        $file = File::where( 'id', $file->id )->firstOrFail();

        $response->assertJson( [
            'data' => [
                'pubFile' => [[
                    'id' => (string) $file->id
                ]],
            ]
        ] );
    }


    public function testPurgeFile()
    {
        $this->seed( CmsSeeder::class );

        $file = File::firstOrFail();

        $this->expectsDatabaseQueryCount( 5 );
        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                purgeFile(id: ["' . $file->id . '"]) {
                    id
                }
            }
        ' );

        $this->assertNull( File::find( $file->id ) );
    }
}
