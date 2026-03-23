<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\Cms\Models\File;
use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Responses\FileResponse;
use Aimeos\Prisma\Responses\TextResponse;
use Database\Seeders\CmsSeeder;
use Illuminate\Http\UploadedFile;
use Aimeos\AnalyticsBridge\Facades\Analytics;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;
use Prism\Prism\Testing\ImageResponseFake;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\Facades\Prism;


class GraphqlTest extends TestAbstract
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

        $this->user = \App\Models\User::create([
            'name' => 'Test editor',
            'email' => 'editor@testbench',
            'password' => 'secret',
            'cmsperms' => \Aimeos\Cms\Permission::all()
        ]);
    }


    public function testDescribe()
    {
        $this->seed( CmsSeeder::class );

        $file = File::firstOrFail();
        $expected = 'Description of the file content.';
        Prisma::fake( [TextResponse::fromText( $expected )] );

        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                describe(file: "' . $file->id . '", lang: "en")
            }
        ' )->assertJson( [
            'data' => [
                'describe' => $expected
            ]
        ] );
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


    public function testImagine()
    {
        $this->seed( CmsSeeder::class );

        $file = File::firstOrFail();
        $image = base64_encode( file_get_contents( __DIR__ . '/assets/image.png' ) );
        Prisma::fake( [FileResponse::fromBase64( $image, 'image/png' )] );

        $response = $this->actingAs( $this->user )->graphQL( "
            mutation {
                imagine(prompt: \"Generate content\", context: \"This is a test context.\", files: [\"" . $file->id . "\"])
            }
        " )->assertJson( [
            'data' => [
                'imagine' => $image
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
        Prism::fake( [
            \Prism\Prism\Testing\StructuredResponseFake::make()->withStructured( [
                'contents' => [[
                    'id' => 'content-1',
                    'type' => 'text',
                    'data' => [
                        ['name' => 'title', 'value' => 'Generated title'],
                        ['name' => 'body', 'value' => 'Generated body content'],
                    ]
                ] ]
            ] )
        ] );

        $response = $this->actingAs( $this->user )->graphQL( '
            mutation($prompt: String!, $content: JSON!, $type: String, $context: String) {
                refine(prompt: $prompt, content: $content, type: $type, context: $context)
            }
        ', [
            'prompt' => 'Refine this content',
            'context' => 'Testing refine mutation',
            'type' => 'content',
            'content' => json_encode( [ [
                'id' => 'content-1',
                'type' => 'text',
                'data' => [
                    'title' => 'Old title',
                    'body' => 'Old body'
                ]
            ] ] ),
        ] );

        $response->assertJson( [
            'data' => [
                'refine' => json_encode( [ [
                    'id' => 'content-1',
                    'type' => 'text',
                    'data' => [
                        'title' => 'Generated title',
                        'body' => 'Generated body content'
                    ]
                ] ] )
            ]
        ] );
    }


    public function testSynthesize()
    {
        $this->seed( CmsSeeder::class );

        $file = File::firstOrFail();
        $fake = \Prism\Prism\Testing\TextResponseFake::make()
            ->withSteps( collect( [
                new \Prism\Prism\Text\Step(
                    'text',
                    \Prism\Prism\Enums\FinishReason::Stop,
                    [
                        new \Prism\Prism\ValueObjects\ToolCall( '1', 'summarize', ['text' => str_repeat( 'A', 80 )] ),
                        new \Prism\Prism\ValueObjects\ToolCall( '2', 'classify', ['category' => 'example'] ),
                    ],
                    [],
                    [],
                    new \Prism\Prism\ValueObjects\Usage(0, 0),
                    new \Prism\Prism\ValueObjects\Meta('fake', 'fake'),
                    [],
                    [],
                    []
                ),
            ] ) )
            ->withText('This is the generated response.');

        Prism::fake([$fake]);

        $response = $this->actingAs($this->user)->graphQL('
            mutation($prompt: String!, $context: String, $files: [String!]) {
                synthesize(prompt: $prompt, context: $context, files: $files)
            }
        ', [
            'prompt' => 'Refine this content',
            'context' => 'Testing synthesize mutation',
            'files'   => [$file->id],
        ]);

        $json = $response->json();

        $this->assertStringStartsWith("Done\n---\n", $json['data']['synthesize']);
        $this->assertStringContainsString('summarize', $json['data']['synthesize']);
        $this->assertStringContainsString('classify', $json['data']['synthesize']);
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


    public function testUncrop()
    {
        $this->seed( CmsSeeder::class );

        $image = file_get_contents( __DIR__ . '/assets/image.png' );
        Prisma::fake( [FileResponse::fromBinary( $image, 'image/png' )] );

        $response = $this->actingAs( $this->user )->multipartGraphQL( [
            'query' => '
                mutation($file: Upload!) {
                    uncrop(file: $file, top: 100, right: 100, bottom: 100, left: 100)
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
                'uncrop' => base64_encode( $image )
            ]
        ] );
    }


    public function testUpscale()
    {
        $this->seed( CmsSeeder::class );

        $image = file_get_contents( __DIR__ . '/assets/image.png' );
        Prisma::fake( [FileResponse::fromBinary( $image, 'image/png' )] );

        $response = $this->actingAs( $this->user )->multipartGraphQL( [
            'query' => '
                mutation($file: Upload!) {
                    upscale(file: $file, factor: 2)
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
                'upscale' => base64_encode( $image )
            ]
        ] );
    }


    public function testWrite()
    {
        $this->seed( CmsSeeder::class );

        $file = File::firstOrFail();
        $expected = 'Generated content based on the prompt.';
        Prism::fake( [TextResponseFake::make()->withText( $expected )] );

        $response = $this->actingAs( $this->user )->graphQL( "
            mutation {
                write(prompt: \"Generate content\", context: \"This is a test context.\", files: [\"" . $file->id . "\"])
            }
        " )->assertJson( [
            'data' => [
                'write' => $expected
            ]
        ] );
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


    public function testMetrics()
    {
        $expected = [
            'views' => [
                ['key' => '2025-08-01', 'value' => 10],
                ['key' => '2025-08-02', 'value' => 20],
            ],
            'visits' => [
                ['key' => '2025-08-01', 'value' => 5],
                ['key' => '2025-08-02', 'value' => 15],
            ],
            'durations' => [
                ['key' => '2025-08-01', 'value' => 60],
                ['key' => '2025-08-02', 'value' => 90],
            ],
            'countries' => [
                ['key' => 'Germany', 'value' => 12],
                ['key' => 'USA', 'value' => 8],
            ],
            'referrers' => [
                ['key' => 'google.com', 'value' => 6],
                ['key' => 'bing.com', 'value' => 3],
            ],
        ];

        $pagespeed = [
            ['key' => 'time_to_first_byte', 'value' => 250]
        ];

        // Mock Analytics facade
        Analytics::shouldReceive('driver->stats')
            ->once()
            ->with('/test', 30)
            ->andReturn($expected);

        Analytics::shouldReceive('pagespeed')
            ->once()
            ->with('/test')
            ->andReturn($pagespeed);


        $response = $this->actingAs($this->user)->graphQL('
            mutation {
                metrics(url: "/test", days: 30) {
                    errors
                    views { key value }
                    visits { key value }
                    durations { key value }
                    countries { key value }
                    referrers { key value }
                    pagespeed { key value }
                }
            }
        ');

        $response->assertJson([
            'data' => [
                'metrics' => $expected + ['pagespeed' => $pagespeed],
            ],
        ]);
    }


    public function testMetricsEmptyUrl()
    {
        $this->actingAs($this->user)->graphQL('
            mutation {
                metrics(url: "", days: 30) {
                    views { key value }
                }
            }
        ')->assertGraphQLErrorMessage('URL must be a non-empty string');
    }


    public function testMetricsInvalidDays()
    {
        $this->actingAs($this->user)->graphQL('
            mutation {
                metrics(url: "/test", days: 100) {
                    views { key value }
                }
            }
        ')->assertGraphQLErrorMessage('Number of days must be an integer between 1 and 90, got "100"');
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


    public function testSynthesizeNoPermission()
    {
        $user = $this->noPermUser();

        $this->actingAs( $user )->graphQL( '
            mutation($prompt: String!) {
                synthesize(prompt: $prompt)
            }
        ', [
            'prompt' => 'test',
        ] )->assertGraphQLErrorMessage( 'Insufficient permissions' );
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


    public function testDescribeNoPermission()
    {
        $this->seed( CmsSeeder::class );
        $user = $this->noPermUser();
        $file = File::firstOrFail();

        $this->actingAs( $user )->graphQL( '
            mutation {
                describe(file: "' . $file->id . '", lang: "en")
            }
        ' )->assertGraphQLErrorMessage( 'Insufficient permissions' );
    }


    public function testMetricsNoPermission()
    {
        $user = $this->noPermUser();

        $this->actingAs( $user )->graphQL( '
            mutation {
                metrics(url: "/test", days: 30) {
                    views { key value }
                }
            }
        ' )->assertGraphQLErrorMessage( 'Insufficient permissions' );
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


    public function testSynthesizeEmptyPrompt()
    {
        $this->actingAs( $this->user )->graphQL( '
            mutation {
                synthesize(prompt: "")
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
        return \App\Models\User::create( [
            'name' => 'No permission',
            'email' => 'noperm-' . \Aimeos\Cms\Utils::uid() . '@testbench',
            'password' => 'secret',
            'cmsperms' => [],
        ] );
    }
}
