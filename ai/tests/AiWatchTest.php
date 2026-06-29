<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Concerns\Watch;
use Aimeos\Cms\Events\Generated;
use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Responses\FileResponse;
use Aimeos\Prisma\Responses\TextResponse;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;


class AiWatchTest extends AiTestAbstract
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


    public function testWriteDispatchesGenerated() : void
    {
        Prisma::fake( [TextResponse::fromText( 'Generated content based on the prompt.' )] );
        Event::fake( [Generated::class] );

        $this->actingAs( $this->user )->graphQL( '
            mutation { write(prompt: "Write something") }
        ' );

        Event::assertDispatched( Generated::class, fn( Generated $e ) =>
            $e->mutation === 'write'
            && $e->success === true
            && $e->provider === 'gemini'
            && $e->editor === 'editor@testbench'
            && $e->durationMs >= 0
        );
    }


    public function testImagineDispatchesGenerated() : void
    {
        $image = base64_encode( (string) file_get_contents( __DIR__ . '/assets/image.png' ) );
        Prisma::fake( [FileResponse::fromBase64( $image, 'image/png' )] );
        Event::fake( [Generated::class] );

        $this->actingAs( $this->user )->graphQL( '
            mutation { imagine(prompt: "A cat") }
        ' );

        Event::assertDispatched( Generated::class, fn( Generated $e ) =>
            $e->mutation === 'imagine' && $e->success === true
        );
    }


    public function testFailureDispatchesGeneratedAndRethrows() : void
    {
        Event::fake( [Generated::class] );

        // Mirrors the tool idiom: a failing provider call dispatches a failure audit event
        // and re-throws the original exception so the MCP layer can surface it.
        $obj = new class {
            use Watch;

            public function run() : mixed
            {
                $start = hrtime( true );

                try {
                    throw new \RuntimeException( 'provider exploded' );
                } catch( \Throwable $e ) {
                    $this->generated( 'write', 'gemini', 'model-x', $start, false, $e->getMessage(), editor: 'editor@testbench' );
                    throw $e;
                }
            }
        };

        try {
            $obj->run();
            $this->fail( 'Expected exception to propagate' );
        } catch( \RuntimeException $e ) {
            $this->assertSame( 'provider exploded', $e->getMessage() );
        }

        Event::assertDispatched( Generated::class, fn( Generated $e ) =>
            $e->mutation === 'write'
            && $e->success === false
            && $e->error === 'provider exploded'
            && $e->editor === 'editor@testbench'
        );
    }
}
