<?php

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
        $this->seed( CmsSeeder::class );

        $file = File::firstOrFail();

        $attr = collect($file->getAttributes())->except(['tenant_id'])->all();
        $expected = ['id' => (string) $file->id] + $attr + [
            'byelements' => [],
            'bypages' => [],
            'byversions' => [['published' => true]],
            'versions' => [['published' => false]]
        ];

        $this->expectsDatabaseQueryCount( 5 );
        $response = $this->actingAs( $this->user )->graphQL( "{
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
        }" )->assertJson( [
            'data' => [
                'file' => $expected,
            ]
        ] );
    }


    public function testFiles()
    {
        $this->seed( CmsSeeder::class );

        $file = File::where( 'lang', 'en' )->get()->first();

        $attr = collect($file->getAttributes())->except(['tenant_id'])->all();
        $expected = [['id' => (string) $file->id] + $attr];

        $this->expectsDatabaseQueryCount( 2 );
        $response = $this->actingAs( $this->user )->graphQL( '{
            files(filter: {
                id: ["' . $file->id . '"]
                lang: "en"
                mime: "image/"
                name: "Test"
                editor: "seeder"
                any: "Test"
            }, sort: [{column: MIME, order: ASC}], first: 10, trashed: WITH, publish: DRAFT) {
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
                }
                paginatorInfo {
                    currentPage
                    lastPage
                }
            }
        }' )->assertJson( [
            'data' => [
                'files' => [
                    'data' => $expected,
                    'paginatorInfo' => [
                        'currentPage' => 1,
                        'lastPage' => 1,
                    ]
                ],
            ]
        ] );
    }


    public function testFilesPublished()
    {
        $this->seed( CmsSeeder::class );

        $this->expectsDatabaseQueryCount( 1 );
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
                    'data' => [],
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
        $this->seed( CmsSeeder::class );

        $file = File::firstOrFail();

        $this->expectsDatabaseQueryCount( 7 );
        $response = $this->actingAs( $this->user )->multipartGraphQL( [
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
        ] );

        $file = File::findOrFail( $file->id );
        $previews = json_encode( $file->latest->data->previews );

        $response->assertJson( [
            'data' => [
                'saveFile' => [
                    'id' => $file->id,
                    'mime' => 'image/jpeg',
                    'lang' => 'en',
                    'name' => 'Test image',
                    'path' => $file->path,
                    'previews' => json_encode( $file->previews ),
                    'description' => json_encode( $file->description ),
                    'transcription' => json_encode( $file->transcription ),
                    'editor' => 'seeder',
                    'latest' => [
                        'data' => '{"mime":"image\\/jpeg","lang":"en-GB","name":"test file","path":"https:\\/\\/picsum.photos\\/id\\/0\\/1500\\/1000","previews":' . $previews . ',"description":{"en":"Test file description"},"transcription":{"en":"Test file transcription"}}',
                        'editor' => 'Test editor',
                    ]
                ],
            ]
        ] );
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
