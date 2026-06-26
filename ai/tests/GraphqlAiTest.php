<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Responses\FileResponse;
use Aimeos\Prisma\Responses\TextResponse;
use Illuminate\Http\UploadedFile;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;


class GraphqlAiTest extends AiTestAbstract
{
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
        ]);
        $this->user->cmsperms = \Aimeos\Cms\Permission::all();
    }


    public function testErase()
    {
        $image = file_get_contents( __DIR__ . '/assets/image.png' );
        Prisma::fake( [FileResponse::fromBinary( $image, 'image/png' )] );

        $response = $this->actingAs( $this->user )->multipartGraphQL( [
            'query' => '
                mutation($file: Upload!, $mask: Upload!) {
                    erase(file: $file, mask: $mask)
                }
            ',
            'variables' => [
                'file' => null,
                'mask' => null,
            ],
        ], [
            '0' => ['variables.file'],
            '1' => ['variables.mask'],
        ], [
            '0' => UploadedFile::fake()->createWithContent('test.png', $image),
            '1' => UploadedFile::fake()->createWithContent('test.png', $image),
        ] )->assertJson( [
            'data' => [
                'erase' => base64_encode( $image )
            ]
        ] );
    }


    public function testInpaint()
    {
        $image = file_get_contents( __DIR__ . '/assets/image.png' );
        Prisma::fake( [FileResponse::fromBinary( $image, 'image/png' )] );

        $response = $this->actingAs( $this->user )->multipartGraphQL( [
            'query' => '
                mutation($file: Upload!, $mask: Upload!, $prompt: String!) {
                    inpaint(file: $file, mask: $mask, prompt: $prompt)
                }
            ',
            'variables' => [
                'file' => null,
                'mask' => null,
                'prompt' => 'Test prompt',
            ],
        ], [
            '0' => ['variables.file'],
            '1' => ['variables.mask'],
        ], [
            '0' => UploadedFile::fake()->createWithContent('test.png', $image),
            '1' => UploadedFile::fake()->createWithContent('test.png', $image),
        ] )->assertJson( [
            'data' => [
                'inpaint' => base64_encode( $image )
            ]
        ] );
    }


    public function testIsolate()
    {
        $image = file_get_contents( __DIR__ . '/assets/image.png' );
        Prisma::fake( [FileResponse::fromBinary( $image, 'image/png' )] );

        $response = $this->actingAs( $this->user )->multipartGraphQL( [
            'query' => '
                mutation($file: Upload!) {
                    isolate(file: $file)
                }
            ',
            'variables' => [
                'file' => null,
            ],
        ], [
            '0' => ['variables.file'],
        ], [
            '0' => UploadedFile::fake()->createWithContent('test.png', $image),
        ] )->assertJson( [
            'data' => [
                'isolate' => base64_encode( $image )
            ]
        ] );
    }


    public function testRefine()
    {
        Prisma::fake( [
            TextResponse::fromText( '' )->withStructured( [
                'contents' => [[
                    'id' => 'content-1',
                    'type' => 'heading',
                    'group' => 'main',
                    'data' => [
                        'title' => 'Generated title',
                        'level' => '1',
                    ]
                ] ]
            ] )
        ] );

        $response = $this->actingAs( $this->user )->graphQL( '
            mutation($prompt: String!, $content: JSON!, $type: String, $context: String, $lang: String) {
                refine(prompt: $prompt, content: $content, type: $type, context: $context, lang: $lang)
            }
        ', [
            'prompt' => 'Refine this content',
            'context' => 'Testing refine mutation',
            'type' => 'content',
            'lang' => 'en',
            'content' => json_encode( [ [
                'id' => 'content-1',
                'type' => 'heading',
                'data' => [
                    'title' => 'Old title',
                    'level' => '2'
                ]
            ] ] ),
        ] );

        $response->assertJson( [
            'data' => [
                'refine' => json_encode( [ [
                    'id' => 'content-1',
                    'type' => 'heading',
                    'data' => [
                        'title' => 'Generated title',
                        'level' => '1'
                    ],
                    'group' => 'main'
                ] ] )
            ]
        ] );
    }


    public function testAiSchemaShape()
    {
        $ai = \Aimeos\Cms\JsonSchema::build( 'content', 'page' );

        $byType = [];
        foreach( $ai['properties']['contents']['items']['anyOf'] as $v ) {
            $byType[$v['properties']['type']['enum'][0] ?? '?'] = $v;
        }

        // group is always present on every variant
        $this->assertArrayHasKey( 'group', $byType['heading']['properties'] );
        $this->assertArrayHasKey( 'group', $byType['reference']['properties'] );

        // file references are {id,type} objects
        $file = $byType['image']['properties']['data']['properties']['file'];
        $this->assertSame( 'object', $file['type'] );
        $this->assertArrayHasKey( 'id', $file['properties'] );
        $this->assertSame( ['file'], $file['properties']['type']['enum'] );
    }


    public function testRefineDerivesFiles()
    {
        $uuid = '11111111-1111-4111-8111-111111111111';

        Prisma::fake( [
            TextResponse::fromText( '' )->withStructured( [
                'contents' => [[
                    'id' => 'img-1',
                    'type' => 'image',
                    'group' => 'main',
                    'data' => ['file' => ['id' => $uuid, 'type' => 'file']],
                ]]
            ] )
        ] );

        $response = $this->actingAs( $this->user )->graphQL( '
            mutation($prompt: String!, $content: JSON!) {
                refine(prompt: $prompt, content: $content)
            }
        ', [
            'prompt' => 'Add an image',
            'content' => json_encode( [] ),
        ] );

        $refine = json_decode( (string) $response->json( 'data.refine' ), true );

        $this->assertSame( ['id' => $uuid, 'type' => 'file'], $refine[0]['data']['file'] );
        $this->assertSame( [$uuid], $refine[0]['files'] );
    }


    public function testRefineInvalidResponse()
    {
        Prisma::fake( [
            TextResponse::fromText( '' )->withStructured( [
                'contents' => [[
                    'id' => 'content-1',
                    'type' => 'heading',
                    'group' => 'nonexistent',
                    'data' => ['title' => 'Generated title', 'level' => '1'],
                ]]
            ] )
        ] );

        $response = $this->actingAs( $this->user )->graphQL( '
            mutation($prompt: String!, $content: JSON!) {
                refine(prompt: $prompt, content: $content)
            }
        ', [
            'prompt' => 'Refine this content',
            'content' => json_encode( [] ),
        ] );

        $this->assertNull( $response->json( 'data.refine' ) );
        $this->assertStringContainsString( 'refine response', (string) $response->json( 'errors.0.message' ) );
    }


    public function testRefineMeta()
    {
        Prisma::fake( [
            TextResponse::fromText( '' )->withStructured( [
                'canonical' => ['url' => 'https://example.com/new'],
            ] )
        ] );

        $this->actingAs( $this->user )->graphQL( '
            mutation($prompt: String!, $content: JSON!, $type: String) {
                refine(prompt: $prompt, content: $content, type: $type)
            }
        ', [
            'prompt' => 'Refine meta',
            'type' => 'meta',
            'content' => json_encode( new \stdClass() ),
        ] )->assertOk()
            ->assertSee( 'canonical' )
            ->assertSee( 'example.com' );
    }


    public function testAiPageTypeGroups()
    {
        // bundled theme defines no sections -> fallback
        $this->assertSame( [], \Aimeos\Cms\Schema::sections( 'page' ) );

        $ai = \Aimeos\Cms\JsonSchema::build( 'content', 'page' );
        $variant = $ai['properties']['contents']['items']['anyOf'][0];
        $this->assertSame( ['main'], $variant['properties']['group']['enum'] );

        // a theme with page-type sections constrains the element groups
        $ref = new \ReflectionClass( \Aimeos\Cms\Schema::class );
        $themes = $ref->getProperty( 'themes' );
        $snapshot = $themes->getValue();

        try
        {
            \Aimeos\Cms\Schema::register( dirname( __DIR__, 2 ) . '/themes/pagible', 'pg' );

            $this->assertSame( ['main', 'footer'], \Aimeos\Cms\Schema::sections( 'page' ) );

            $ai = \Aimeos\Cms\JsonSchema::build( 'content', 'page' );
            $variant = $ai['properties']['contents']['items']['anyOf'][0];
            $reference = end( $ai['properties']['contents']['items']['anyOf'] );

            $this->assertSame( ['main', 'footer'], $variant['properties']['group']['enum'] );
            $this->assertSame( ['main', 'footer'], $reference['properties']['group']['enum'] );
        }
        finally
        {
            $themes->setValue( null, $snapshot );
            $ref->getProperty( 'schemas' )->setValue( null, [] );
        }
    }


    public function testAiNoUnsupportedFormat()
    {
        // "format" (e.g. uri-reference) is rejected by OpenAI strict; must not be emitted
        $json = (string) json_encode( \Aimeos\Cms\JsonSchema::build( 'content' ) );

        $this->assertStringNotContainsString( '"format"', $json );
        $this->assertStringContainsString( 'site-relative path', $json );
    }


    public function testContentGroupCoercion()
    {
        $ref = new \ReflectionClass( \Aimeos\Cms\Schema::class );
        $themes = $ref->getProperty( 'themes' );
        $snapshot = $themes->getValue();

        try
        {
            \Aimeos\Cms\Schema::register( dirname( __DIR__, 2 ) . '/themes/pagible', 'pg' );

            $result = \Aimeos\Cms\Validation::content( [
                ['type' => 'heading', 'group' => 'footer', 'data' => ['title' => 'A', 'level' => '2']],
                ['type' => 'heading', 'group' => 'bogus', 'data' => ['title' => 'B', 'level' => '2']],
                ['type' => 'heading', 'data' => ['title' => 'C', 'level' => '2']],
            ], 'page' );

            $this->assertSame( 'footer', $result[0]->group ); // valid section kept
            $this->assertSame( 'main', $result[1]->group );   // invalid group coerced
            $this->assertSame( 'main', $result[2]->group );   // missing group defaulted
        }
        finally
        {
            $themes->setValue( null, $snapshot );
            $ref->getProperty( 'schemas' )->setValue( null, [] );
        }
    }


    public function testTranslate()
    {
        $texts = ['Hello', 'World'];
        $expected = ['Hallo', 'Welt'];

        $response = TextResponse::fromText( $expected[0] )->add( $expected[1] );
        Prisma::fake( [$response] );

        $this->actingAs( $this->user )->graphQL( '
            mutation($texts: [String!]!, $to: String!, $from: String, $context: String) {
                translate(texts: $texts, to: $to, from: $from, context: $context)
            }
        ', [
            'texts' => $texts,
            'to' => 'de',
            'from' => 'en',
            'context' => 'General translation',
        ] )->assertJson( [
            'data' => [
                'translate' => $expected
            ]
        ] );
    }


    public function testTranslateEmptyTexts()
    {
        $this->actingAs( $this->user )->graphQL( '
            mutation($texts: [String!]!, $to: String!) {
                translate(texts: $texts, to: $to)
            }
        ', [
            'texts' => [],
            'to' => 'de',
        ] )->assertGraphQLErrorMessage( 'Input texts must not be empty' );
    }


    public function testTranscribe()
    {
        Prisma::fake( [
            TextResponse::fromText( 'test transcription' )->withStructured( [
                ['start' => 0, 'end' => 1, 'text' => 'test transcription'],
            ] )
        ] );

        $response = $this->actingAs( $this->user )->multipartGraphQL( [
            'query' => '
                mutation($file: Upload!) {
                    transcribe(file: $file)
                }
            ',
            'variables' => [
                'file' => null,
            ],
        ], [
            '0' => ['variables.file'],
        ], [
            '0' => UploadedFile::fake()->create('test.mp3', 500, 'audio/mpeg'),
        ] )->assertJson( [
            'data' => [
                'transcribe' => '[{"start":"00:00:00.000","end":"00:00:01.000","text":"test transcription"}]'
            ]
        ] );
    }


    // --- Permission denial tests ---

    public function testImagineNoPermission()
    {
        $user = $this->noPermUser();

        $this->actingAs( $user )->graphQL( '
            mutation {
                imagine(prompt: "test", context: "ctx")
            }
        ' )->assertGraphQLErrorMessage( 'Insufficient permissions' );
    }


    public function testInpaintNoPermission()
    {
        $user = $this->noPermUser();
        $image = file_get_contents( __DIR__ . '/assets/image.png' );

        $this->actingAs( $user )->multipartGraphQL( [
            'query' => '
                mutation($file: Upload!, $mask: Upload!, $prompt: String!) {
                    inpaint(file: $file, mask: $mask, prompt: $prompt)
                }
            ',
            'variables' => ['file' => null, 'mask' => null, 'prompt' => 'test'],
        ], [
            '0' => ['variables.file'],
            '1' => ['variables.mask'],
        ], [
            '0' => UploadedFile::fake()->createWithContent( 'test.png', $image ),
            '1' => UploadedFile::fake()->createWithContent( 'mask.png', $image ),
        ] )->assertGraphQLErrorMessage( 'Insufficient permissions' );
    }


    public function testIsolateNoPermission()
    {
        $user = $this->noPermUser();
        $image = file_get_contents( __DIR__ . '/assets/image.png' );

        $this->actingAs( $user )->multipartGraphQL( [
            'query' => '
                mutation($file: Upload!) {
                    isolate(file: $file)
                }
            ',
            'variables' => ['file' => null],
        ], [
            '0' => ['variables.file'],
        ], [
            '0' => UploadedFile::fake()->createWithContent( 'test.png', $image ),
        ] )->assertGraphQLErrorMessage( 'Insufficient permissions' );
    }


    public function testRepaintNoPermission()
    {
        $user = $this->noPermUser();
        $image = file_get_contents( __DIR__ . '/assets/image.png' );

        $this->actingAs( $user )->multipartGraphQL( [
            'query' => '
                mutation($file: Upload!, $prompt: String!) {
                    repaint(file: $file, prompt: $prompt)
                }
            ',
            'variables' => ['file' => null, 'prompt' => 'test'],
        ], [
            '0' => ['variables.file'],
        ], [
            '0' => UploadedFile::fake()->createWithContent( 'test.png', $image ),
        ] )->assertGraphQLErrorMessage( 'Insufficient permissions' );
    }


    public function testEraseNoPermission()
    {
        $user = $this->noPermUser();
        $image = file_get_contents( __DIR__ . '/assets/image.png' );

        $this->actingAs( $user )->multipartGraphQL( [
            'query' => '
                mutation($file: Upload!, $mask: Upload!) {
                    erase(file: $file, mask: $mask)
                }
            ',
            'variables' => ['file' => null, 'mask' => null],
        ], [
            '0' => ['variables.file'],
            '1' => ['variables.mask'],
        ], [
            '0' => UploadedFile::fake()->createWithContent( 'test.png', $image ),
            '1' => UploadedFile::fake()->createWithContent( 'mask.png', $image ),
        ] )->assertGraphQLErrorMessage( 'Insufficient permissions' );
    }


    public function testUncropNoPermission()
    {
        $user = $this->noPermUser();
        $image = file_get_contents( __DIR__ . '/assets/image.png' );

        $this->actingAs( $user )->multipartGraphQL( [
            'query' => '
                mutation($file: Upload!) {
                    uncrop(file: $file, top: 100, right: 100, bottom: 100, left: 100)
                }
            ',
            'variables' => ['file' => null],
        ], [
            '0' => ['variables.file'],
        ], [
            '0' => UploadedFile::fake()->createWithContent( 'test.png', $image ),
        ] )->assertGraphQLErrorMessage( 'Insufficient permissions' );
    }


    public function testUpscaleNoPermission()
    {
        $user = $this->noPermUser();
        $image = file_get_contents( __DIR__ . '/assets/image.png' );

        $this->actingAs( $user )->multipartGraphQL( [
            'query' => '
                mutation($file: Upload!) {
                    upscale(file: $file, factor: 2)
                }
            ',
            'variables' => ['file' => null],
        ], [
            '0' => ['variables.file'],
        ], [
            '0' => UploadedFile::fake()->createWithContent( 'test.png', $image ),
        ] )->assertGraphQLErrorMessage( 'Insufficient permissions' );
    }


    public function testWriteNoPermission()
    {
        $user = $this->noPermUser();

        $this->actingAs( $user )->graphQL( '
            mutation {
                write(prompt: "test")
            }
        ' )->assertGraphQLErrorMessage( 'Insufficient permissions' );
    }


    public function testRefineNoPermission()
    {
        $user = $this->noPermUser();

        $this->actingAs( $user )->graphQL( '
            mutation($prompt: String!, $content: JSON!) {
                refine(prompt: $prompt, content: $content)
            }
        ', [
            'prompt' => 'test',
            'content' => json_encode( [] ),
        ] )->assertGraphQLErrorMessage( 'Insufficient permissions' );
    }


    public function testTranslateNoPermission()
    {
        $user = $this->noPermUser();

        $this->actingAs( $user )->graphQL( '
            mutation($texts: [String!]!, $to: String!) {
                translate(texts: $texts, to: $to)
            }
        ', [
            'texts' => ['Hello'],
            'to' => 'de',
        ] )->assertGraphQLErrorMessage( 'Insufficient permissions' );
    }


    public function testTranscribeNoPermission()
    {
        $user = $this->noPermUser();

        $this->actingAs( $user )->multipartGraphQL( [
            'query' => '
                mutation($file: Upload!) {
                    transcribe(file: $file)
                }
            ',
            'variables' => ['file' => null],
        ], [
            '0' => ['variables.file'],
        ], [
            '0' => UploadedFile::fake()->create( 'test.mp3', 500, 'audio/mpeg' ),
        ] )->assertGraphQLErrorMessage( 'Insufficient permissions' );
    }


    // --- Input validation tests ---

    public function testImagineEmptyPrompt()
    {
        $this->actingAs( $this->user )->graphQL( '
            mutation {
                imagine(prompt: "")
            }
        ' )->assertGraphQLErrorMessage( 'Prompt must not be empty' );
    }


    public function testWriteEmptyPrompt()
    {
        $this->actingAs( $this->user )->graphQL( '
            mutation {
                write(prompt: "")
            }
        ' )->assertGraphQLErrorMessage( 'Prompt must not be empty' );
    }


    public function testRefineEmptyPrompt()
    {
        $this->actingAs( $this->user )->graphQL( '
            mutation {
                refine(prompt: "", content: "[]")
            }
        ' )->assertGraphQLErrorMessage( 'Prompt must not be empty' );
    }


    public function testDescribeEmptyFile()
    {
        $this->actingAs( $this->user )->graphQL( '
            mutation {
                describe(file: "", lang: "en")
            }
        ' )->assertGraphQLErrorMessage( 'File ID is required' );
    }


    protected function noPermUser(): \App\Models\User
    {
        $user = new \App\Models\User([
            'name' => 'No permission',
            'email' => 'noperm@testbench',
            'password' => 'secret',
        ]);
        $user->cmsperms = [];
        return $user;
    }
}
