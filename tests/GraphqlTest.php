<?php

namespace Tests;

use Aimeos\Cms\Models\File;
use Database\Seeders\CmsSeeder;
use Illuminate\Http\UploadedFile;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;
use Prism\Prism\Testing\ImageResponseFake;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\Prism;


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


    public function testCompose()
    {
        $this->seed( CmsSeeder::class );

        $file = File::firstOrFail();
        $expected = 'Generated content based on the prompt.';
        Prism::fake( [TextResponseFake::make()->withText( $expected )] );

        $response = $this->actingAs( $this->user )->graphQL( "
            mutation {
                compose(prompt: \"Generate content\", context: \"This is a test context.\", files: [\"" . $file->id . "\"])
            }
        " )->assertJson( [
            'data' => [
                'compose' => $expected
            ]
        ] );
    }


    public function testImagine()
    {
        $this->seed( CmsSeeder::class );

        $file = File::firstOrFail();
        $image = base64_encode( file_get_contents( __DIR__ . '/assets/image.png' ) );

        Prism::fake( [new \Prism\Prism\Images\Response(
            images: [
                new \Prism\Prism\ValueObjects\GeneratedImage(
                    revisedPrompt: null,
                    base64: $image,
                ),
            ],
            usage: new \Prism\Prism\ValueObjects\Usage(0, 0),
            meta: new \Prism\Prism\ValueObjects\Meta('fake', 'fake'),
            additionalContent: [],
        )] );

        $response = $this->actingAs( $this->user )->graphQL( "
            mutation {
                imagine(prompt: \"Generate content\", context: \"This is a test context.\", files: [\"" . $file->id . "\"])
            }
        " )->assertJson( [
            'data' => [
                'imagine' => [
                    'Generate content',
                    $image
                ]
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
                    new \Prism\Prism\ValueObjects\Usage(0, 0),
                    new \Prism\Prism\ValueObjects\Meta('fake', 'fake'),
                    [],
                    []
                ),
            ] ) )
            ->withText('This is the generated response.');

        \Prism\Prism\Prism::fake([$fake]);

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
        Prism::fake( [new \Prism\Prism\Audio\TextResponse( '[]' )] );

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
                'transcribe' => "WEBVTT\n"
            ]
        ] );
    }
}
