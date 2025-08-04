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
        $fake = ImageResponseFake::make();

        Prism::fake( [$fake] );
        $image = \Prism\Prism\ValueObjects\Media\Image::fromUrl( $fake->firstImage()?->url )->base64();

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


    public function testTranscribe()
    {
        Prism::fake( [new \Prism\Prism\Audio\TextResponse( 'Fake transcription' )] );

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
                'transcribe' => 'Fake transcription'
            ]
        ] );
    }
}
