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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\Facades\Prism;


class GraphqlTest extends AiTestAbstract
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
        ]);
        $this->user->cmsperms = \Aimeos\Cms\Permission::all();
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


    public function testUncrop()
    {
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


    public function testDescribeNoPermission()
    {
        $this->seed( CmsSeeder::class );

        $user = new \App\Models\User( [
            'name' => 'No permission',
            'email' => 'noperm@testbench',
            'password' => 'secret',
            'cmsperms' => [],
        ] );

        $file = File::firstOrFail();

        $this->actingAs( $user )->graphQL( '
            mutation {
                describe(file: "' . $file->id . '", lang: "en")
            }
        ' )->assertGraphQLErrorMessage( 'Insufficient permissions' );
    }
}
