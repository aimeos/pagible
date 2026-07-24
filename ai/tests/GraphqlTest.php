<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\File;
use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Responses\FileResponse;
use Aimeos\Prisma\Responses\TextResponse;
use Database\Seeders\TestSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;


class GraphqlTest extends AiTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;
    use MakesGraphQLRequests;
    use RefreshesSchemaCache;

    protected $seeder = TestSeeder::class;


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
        $file = File::firstOrFail();
        $image = base64_encode( $this->pngBinary() );
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


    public function testUncrop()
    {
        $image = $this->pngBinary();
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
        $image = $this->pngBinary();
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
        $file = File::firstOrFail();
        $expected = 'Generated content based on the prompt.';
        Prisma::fake( [TextResponse::fromText( $expected )] );

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
