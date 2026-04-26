<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;
use Database\Seeders\CmsSeeder;
use Aimeos\Cms\Models\File;


class GraphqlFileTest extends GraphqlTestAbstract
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
            'created_at' => (string) $file->created_at,
            'updated_at' => (string) $file->updated_at,
        ] + collect($file->getAttributes())->except(['tenant_id', 'latest_id'])->all();

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
                'created_at' => (string) $file->created_at,
                'updated_at' => (string) $file->updated_at,
            ] + collect($file->getAttributes())->except(['tenant_id', 'latest_id'])->all();
        } )->all();

        $this->expectsDatabaseQueryCount(3);

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

        $filesData[0]['previews'] = json_decode( $filesData[0]['previews'], true );
        $filesData[0]['description'] = json_decode( $filesData[0]['description'], true );
        $filesData[0]['transcription'] = json_decode( $filesData[0]['transcription'], true );
        $filesData[1]['previews'] = json_decode( $filesData[1]['previews'], true );
        $filesData[1]['description'] = json_decode( $filesData[1]['description'], true );
        $filesData[1]['transcription'] = json_decode( $filesData[1]['transcription'], true );

        $this->assertEquals($expected, $filesData);

        // Assert paginator info
        $paginator = $response->json('data.files.paginatorInfo');
        $this->assertEquals(1, $paginator['currentPage']);
        $this->assertEquals(1, $paginator['lastPage']);
    }


    public function testFilesMime()
    {
        $this->seed( CmsSeeder::class );

        $this->expectsDatabaseQueryCount( 3 );
        $response = $this->actingAs( $this->user )->graphQL( '{
            files(filter: {
                mime: ["image/jpeg", "image/png"]
            }) {
                data {
                    id
                    mime
                }
            }
        }' );

        $filesData = $response->json( 'data.files.data' );
        $this->assertCount( 1, $filesData );
        $this->assertEquals( 'image/jpeg', $filesData[0]['mime'] );
    }


    public function testFilesPublished()
    {
        $this->seed( CmsSeeder::class );

        $file = File::where( 'mime', 'image/tiff' )->first();

        $this->expectsDatabaseQueryCount( 3 );
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

        $this->expectsDatabaseQueryCount( 3 );
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
        $tmpFile = tempnam( sys_get_temp_dir(), 'pdf' );
        file_put_contents( $tmpFile, '%PDF-1.4 test content' );
        $upload = new UploadedFile( $tmpFile, 'test.pdf', 'application/pdf', null, true );

        $tmpPreview = tempnam( sys_get_temp_dir(), 'jpg' );
        $img = imagecreatetruecolor( 20, 20 );
        imagejpeg( $img, $tmpPreview );
        imagedestroy( $img );
        $preview = new UploadedFile( $tmpPreview, 'test-preview-1.jpg', 'image/jpeg', null, true );

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
            '0' => $upload,
            '1' => $preview,
        ] );

        $result = $response->json('data.addFile');
        $file = File::findOrFail( $result['id'] );

        $response->assertJson( [
            'data' => [
                'addFile' => [
                    'id' => $file->id,
                    'mime' => 'application/pdf',
                    'lang' => 'en-GB',
                    'name' => 'Test file name',
                    'path' => $file->path,
                    'previews' => json_encode( $file->previews ),
                    'description' => json_encode( $file->description ),
                    'transcription' => json_encode( $file->transcription ),
                    'editor' => 'editor@testbench',
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

        $this->expectsDatabaseQueryCount( 8 );

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
            'scheduled' => 0,
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
        $this->assertEquals('editor@testbench', $saveFile['latest']['editor'] ?? null);
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
                pubFile(id: ["' . $file->id . '"], at: "2099-01-01 00:00:00") {
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


    public function testPubFileAtWithTime()
    {
        $this->seed( CmsSeeder::class );

        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();

        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                pubFile(id: ["' . $file->id . '"], at: "2099-06-15 14:30:00") {
                    id
                }
            }
        ' );

        $response->assertJson( [
            'data' => [
                'pubFile' => [[
                    'id' => (string) $file->id
                ]],
            ]
        ] );

        $file = File::with( 'latest' )->findOrFail( $file->id );
        $this->assertStringContainsString( '14:30:00', $file->latest->publish_at );
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


    public function testAddFileRejectsSize()
    {
        config()->set( 'cms.graphql.filesize', 0.001 ); // ~1 KB

        $response = $this->actingAs( $this->user )->multipartGraphQL( [
            'query' => '
                mutation($file: Upload!) {
                    addFile(file: $file, input: { name: "test" }) {
                        id
                    }
                }
            ',
            'variables' => [
                'file' => null,
            ],
        ], [
            '0' => ['variables.file'],
        ], [
            '0' => UploadedFile::fake()->create( 'test.pdf', 100 ),
        ] );

        $response->assertGraphQLErrorMessage( 'File size of 0.098 MB exceeds the maximum of 0.001 MB' );
    }


    public function testAddFileRejectsMime()
    {
        config()->set( 'cms.graphql.mimetypes', ['image/'] );

        $response = $this->actingAs( $this->user )->multipartGraphQL( [
            'query' => '
                mutation($file: Upload!) {
                    addFile(file: $file, input: { name: "test" }) {
                        id
                    }
                }
            ',
            'variables' => [
                'file' => null,
            ],
        ], [
            '0' => ['variables.file'],
        ], [
            '0' => UploadedFile::fake()->create( 'test.pdf', 1 ),
        ] );

        $response->assertGraphQLErrorMessage( 'File type "application/pdf" not allowed, permitted types: image/' );
    }


    public function testAddFileSanitizesSvg()
    {
        $svgContent = '<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/><script>alert(1)</script></svg>';
        $tmpFile = tempnam( sys_get_temp_dir(), 'svg' );
        file_put_contents( $tmpFile, $svgContent );

        $upload = new UploadedFile( $tmpFile, 'test.svg', 'image/svg+xml', null, true );

        $response = $this->actingAs( $this->user )->multipartGraphQL( [
            'query' => '
                mutation($file: Upload!) {
                    addFile(file: $file, input: { name: "test.svg" }) {
                        id
                        path
                    }
                }
            ',
            'variables' => [
                'file' => null,
            ],
        ], [
            '0' => ['variables.file'],
        ], [
            '0' => $upload,
        ] );

        $result = $response->json( 'data.addFile' );
        $stored = \Illuminate\Support\Facades\Storage::disk( config( 'cms.disk', 'public' ) )->get( $result['path'] );

        $this->assertStringContainsString( '<rect', $stored );
        $this->assertStringNotContainsString( '<script', $stored );

        @unlink( $tmpFile );
    }
}
