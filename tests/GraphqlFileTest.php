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

        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();

        $expected = [
            'id' => $file->id,
            'previews' => (array) $file->previews,
            'description' => (array) $file->description,
            'transcription' => (array) $file->transcription,
            'byelements' => $file->byelements->map( fn( $item ) => ['id' => $item->id] )->all(),
            'bypages' => $file->bypages->map( fn( $item ) => ['id' => $item->id] )->all(),
            'byversions' => $file->byversions->map( fn( $item ) => ['published' => $item->published] )->all(),
            'versions' => $file->versions->map( fn( $item ) => ['published' => $item->published] )->all(),
        ] + collect($file->getAttributes())->except(['tenant_id'])->all();

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
        $fileData['previews'] = json_decode( $fileData['previews'], true );
        $fileData['description'] = json_decode( $fileData['description'], true );
        $fileData['transcription'] = json_decode( $fileData['transcription'], true );

        $this->assertEquals($expected, $fileData);
    }


    public function testFiles()
    {
        $this->seed(CmsSeeder::class);

        $expected = File::orderBy( 'mime' )->get()->map( function( $file ) {
            return [
                'id' => $file->id,
                'previews' => (array) $file->previews,
                'description' => (array) $file->description,
                'transcription' => (array) $file->transcription,
                'byversions_count' => $file->byversions()->count(),
            ] + collect($file->getAttributes())->except(['tenant_id'])->all();
        } )->all();

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
        $filesData[0]['previews'] = json_decode( $filesData[0]['previews'], true );
        $filesData[0]['description'] = json_decode( $filesData[0]['description'], true );
        $filesData[0]['transcription'] = json_decode( $filesData[0]['transcription'], true );
        $filesData[1]['previews'] = json_decode( $filesData[1]['previews'], true );
        $filesData[1]['description'] = json_decode( $filesData[1]['description'], true );
        $filesData[1]['transcription'] = json_decode( $filesData[1]['transcription'], true );

        $this->assertCount(2, $filesData);
        $this->assertEquals($expected, $filesData);

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

        $file = File::whereHas( 'latest', function( $builder ) {
            $builder->where( 'cms_versions.publish_at', '!=', null )->where( 'cms_versions.published', false );
        } )->firstOrFail();

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

        $this->expectsDatabaseQueryCount( 4 );
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

        $result = $response->json('data.addFile');
        $file = File::findOrFail( $result['id'] );

        $response->assertJson( [
            'data' => [
                'addFile' => [
                    'id' => $file->id,
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

        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();

        $this->expectsDatabaseQueryCount(7);

\Illuminate\Support\Facades\DB::enableQueryLog();
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
print_r(\Illuminate\Support\Facades\DB::getQueryLog());

        $file = File::findOrFail($file->id);
        $saveFile = $response->json('data.saveFile');

        $this->assertEquals($file->id, $saveFile['id']);

        // Cast nested objects to arrays
        $expectedLatestData = [
            'mime' => 'image/jpeg',
            'lang' => 'en-GB',
            'name' => 'test file',
            'path' => $file->path,
            'previews' => (array) ( $file->latest?->data?->previews ?? [] ),
            'description' => (array) ( $file->latest?->data?->description ?? [] ),
            'transcription' => (array) ( $file->latest?->data?->transcription ?? [] ),
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
        $this->assertEquals($expectedLatestData, json_decode($saveFile['latest']['data'] ?? null, true));
        $this->assertEquals('Test editor', $saveFile['latest']['editor'] ?? null);
    }


    public function testDropFile()
    {
        $this->seed( CmsSeeder::class );

        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();

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
                    'deleted_at' => (string) $file->deleted_at,
                ]],
            ]
        ] );
    }


    public function testKeepFile()
    {
        $this->seed( CmsSeeder::class );

        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
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

        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();

        $this->expectsDatabaseQueryCount( 6 );
        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                pubFile(id: ["' . $file->id . '"]) {
                    id
                }
            }
        ' );

        $file = File::findOrFail( $file->id );

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

        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();

        $this->expectsDatabaseQueryCount( 4 );
        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                pubFile(id: ["' . $file->id . '"], at: "2025-01-01 00:00:00") {
                    id
                }
            }
        ' );

        $file = File::findOrFail( $file->id );

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

        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();

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
