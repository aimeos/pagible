<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Mcp\CmsServer;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Responses\FileResponse;
use Aimeos\Prisma\Responses\TextResponse;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;


class AiToolsTest extends AiTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected $seeder = TestSeeder::class;


    protected function setUp(): void
    {
        parent::setUp();

        $this->user = new \App\Models\User([
            'name' => 'Test editor',
            'email' => 'editor@testbench',
            'password' => 'secret',
            'cmsperms' => \Aimeos\Cms\Permission::all()
        ]);
    }


	protected function getPackageProviders( $app )
	{
		return array_merge( parent::getPackageProviders( $app ), [
			'Aimeos\Cms\McpServiceProvider',
		] );
	}


    // ── RefineContent ─────────────────────────────────────────────────

    public function testRefineContent()
    {
        $page = Page::where( 'name', 'Home' )->first();

        Prisma::fake( [
            TextResponse::fromText( '' )->withStructured( [
                'contents' => [[
                    'id' => 'content-1',
                    'type' => 'text',
                    'group' => 'main',
                    'data' => [
                        'text' => 'Refined text',
                    ]
                ]]
            ] )
        ] );

        $response = CmsServer::actingAs( $this->user )->tool( \Aimeos\Cms\Tools\RefineContent::class, [
            'id' => $page->id,
            'prompt' => 'Make the content more engaging',
        ] );

        $response->assertOk()->assertSee( ['content'] );
    }


    public function testRefineContentNotFound()
    {
        Prisma::fake( [] );

        $response = CmsServer::actingAs( $this->user )->tool( \Aimeos\Cms\Tools\RefineContent::class, [
            'id' => '00000000-0000-0000-0000-000000000000',
            'prompt' => 'Refine this',
        ] );

        $response->assertOk()->assertStructuredContent( ['error' => 'Page not found.'] );
    }


    public function testRefineContentPermission()
    {
        $user = new \App\Models\User([
            'name' => 'No perms',
            'email' => 'noperms@testbench',
            'password' => 'secret',
            'cmsperms' => []
        ]);

        $response = CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\RefineContent::class, [
            'id' => 'test',
            'prompt' => 'Refine this',
        ] );

        $response->assertHasErrors();
    }


    public function testRefineContentRequiresPageView()
    {
        $user = new \App\Models\User([
            'name' => 'Refiner without view',
            'email' => 'refiner@testbench',
            'password' => 'secret',
            'cmsperms' => ['page:refine'],
        ]);

        $response = CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\RefineContent::class, [
            'id' => '00000000-0000-0000-0000-000000000000',
            'prompt' => 'Refine this',
        ] );

        $response->assertHasErrors();
    }


    // ── TranslateContent ──────────────────────────────────────────────

    public function testTranslateContent()
    {
        $response = TextResponse::fromTexts( ['Hallo', 'Welt'] );
        Prisma::fake( [$response] );

        $response = CmsServer::actingAs( $this->user )->tool( \Aimeos\Cms\Tools\TranslateContent::class, [
            'texts' => ['Hello', 'World'],
            'to' => 'de',
        ] );

        $response->assertOk()->assertSee( ['translations'] );
    }


    public function testTranslateContentPermission()
    {
        $user = new \App\Models\User([
            'name' => 'No perms',
            'email' => 'noperms@testbench',
            'password' => 'secret',
            'cmsperms' => []
        ]);

        $response = CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\TranslateContent::class, [
            'texts' => ['Hello'],
            'to' => 'de',
        ] );

        $response->assertHasErrors();
    }


    // ── GenerateImage ─────────────────────────────────────────────────

    public function testGenerateImage()
    {
        Storage::fake( 'public' );
        $image = $this->pngBinary();
        Prisma::fake( [FileResponse::fromBinary( $image, 'image/png' )] );

        $response = CmsServer::actingAs( $this->user )->tool( \Aimeos\Cms\Tools\GenerateImage::class, [
            'prompt' => 'A blue abstract hero banner',
            'name' => 'hero',
        ] );

        $response->assertOk()->assertSee( ['id'] );
        $this->assertTrue( File::where( 'name', 'hero.png' )->exists() );
    }


    public function testGenerateImageRemovesOriginalAfterPreviewFailure()
    {
        Storage::fake( 'public' );
        config( ['cms.image.preview-sizes' => [['width' => []]]] );
        Prisma::fake( [FileResponse::fromBinary( $this->pngBinary(), 'image/png' )] );

        $response = CmsServer::actingAs( $this->user )->tool( \Aimeos\Cms\Tools\GenerateImage::class, [
            'prompt' => 'An invalid image response',
            'name' => 'invalid',
        ] );

        $response->assertHasErrors();
        $this->assertEmpty( Storage::disk( 'public' )->allFiles() );
    }


    public function testGenerateImagePermission()
    {
        $user = new \App\Models\User([
            'name' => 'No perms',
            'email' => 'noperms@testbench',
            'password' => 'secret',
            'cmsperms' => []
        ]);

        $response = CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\GenerateImage::class, [
            'prompt' => 'A blue abstract hero banner',
        ] );

        $response->assertHasErrors();
    }


    public function testGenerateImageReferencesRequireFileViewPermission()
    {
        $user = new \App\Models\User([
            'name' => 'No file view',
            'email' => 'nofileview@testbench',
            'password' => 'secret',
            'cmsperms' => array_values( array_diff( \Aimeos\Cms\Permission::all(), ['file:view'] ) ),
        ]);

        $this->actingAs( $user );
        $this->assertTrue( $this->app->make( \Aimeos\Cms\Tools\GenerateImage::class )->eligibleForRegistration() );

        CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\GenerateImage::class, [
            'prompt' => 'Use the stored image as a reference',
            'files' => ['00000000-0000-0000-0000-000000000000'],
        ] )->assertHasErrors();
    }


    public function testImageToolsRequireFileMutationPermission()
    {
        $user = new \App\Models\User([
            'name' => 'Image only',
            'email' => 'imageonly@testbench',
            'password' => 'secret',
            'cmsperms' => [
                'image:erase',
                'image:imagine',
                'image:inpaint',
                'image:isolate',
                'image:repaint',
                'image:uncrop',
                'image:upscale',
            ],
        ]);

        $tools = [
            \Aimeos\Cms\Tools\EraseImage::class,
            \Aimeos\Cms\Tools\GenerateImage::class,
            \Aimeos\Cms\Tools\InpaintImage::class,
            \Aimeos\Cms\Tools\IsolateImage::class,
            \Aimeos\Cms\Tools\RepaintImage::class,
            \Aimeos\Cms\Tools\UncropImage::class,
            \Aimeos\Cms\Tools\UpscaleImage::class,
        ];

        $this->actingAs( $user );

        foreach( $tools as $class )
        {
            $tool = $this->app->make( $class );

            try {
                $tool->handle( new \Laravel\Mcp\Request() );
                $this->fail( sprintf( '%s accepted an image-only permission', $class ) );
            } catch( \Aimeos\Cms\Exception $e ) {
                $this->assertSame( 'Insufficient permissions', $e->getMessage() );
            }

            $this->assertFalse( $tool->eligibleForRegistration() );
        }

        $this->actingAs( $this->user );

        foreach( $tools as $class ) {
            $this->assertTrue( $this->app->make( $class )->eligibleForRegistration() );
        }
    }


    public function testStoredFileToolsRequireViewPermission()
    {
        $user = new \App\Models\User([
            'name' => 'No file view',
            'email' => 'stored-nofileview@testbench',
            'password' => 'secret',
            'cmsperms' => array_values( array_diff( \Aimeos\Cms\Permission::all(), ['file:view'] ) ),
        ]);
        $tools = [
            \Aimeos\Cms\Tools\DescribeFile::class,
            \Aimeos\Cms\Tools\EraseImage::class,
            \Aimeos\Cms\Tools\InpaintImage::class,
            \Aimeos\Cms\Tools\IsolateImage::class,
            \Aimeos\Cms\Tools\RepaintImage::class,
            \Aimeos\Cms\Tools\TranscribeAudio::class,
            \Aimeos\Cms\Tools\UncropImage::class,
            \Aimeos\Cms\Tools\UpscaleImage::class,
        ];

        $this->actingAs( $user );

        foreach( $tools as $class )
        {
            $tool = $this->app->make( $class );

            try {
                $tool->handle( new \Laravel\Mcp\Request() );
                $this->fail( sprintf( '%s accepted stored input without file:view', $class ) );
            } catch( \Aimeos\Cms\Exception $e ) {
                $this->assertSame( 'Insufficient permissions', $e->getMessage() );
            }

            $this->assertFalse( $tool->eligibleForRegistration() );
        }
    }


    // ── RepaintImage (edits create a new version) ─────────────────────

    public function testRepaintImageCreatesVersion()
    {
        Storage::fake( 'public' );
        $image = $this->pngBinary();
        $file = File::where( 'name', 'Test image' )->firstOrFail();
        $before = $file->versions()->count();

        Prisma::fake( [FileResponse::fromBinary( $image, 'image/png' )] );

        $response = CmsServer::actingAs( $this->user )->tool( \Aimeos\Cms\Tools\RepaintImage::class, [
            'file' => $file->id,
            'prompt' => 'Make the sky a sunset',
        ] );

        $response->assertOk()->assertSee( ['id'] );
        $this->assertGreaterThan( $before, $file->versions()->count() );
    }


    public function testRepaintImageNotFound()
    {
        Prisma::fake( [] );

        $response = CmsServer::actingAs( $this->user )->tool( \Aimeos\Cms\Tools\RepaintImage::class, [
            'file' => '00000000-0000-0000-0000-000000000000',
            'prompt' => 'Make the sky a sunset',
        ] );

        $response->assertOk()->assertStructuredContent( ['error' => 'Image file not found or not an image.'] );
    }


    // ── DescribeFile ──────────────────────────────────────────────────

    public function testDescribeFile()
    {
        $file = File::where( 'name', 'Test image' )->firstOrFail();
        Prisma::fake( [TextResponse::fromText( 'A scenic mountain view at sunset.' )] );

        $response = CmsServer::actingAs( $this->user )->tool( \Aimeos\Cms\Tools\DescribeFile::class, [
            'file' => $file->id,
            'lang' => 'en',
        ] );

        $response->assertOk()->assertSee( ['description'] );
    }


    public function testDescribeFileNotFound()
    {
        Prisma::fake( [] );

        $response = CmsServer::actingAs( $this->user )->tool( \Aimeos\Cms\Tools\DescribeFile::class, [
            'file' => '00000000-0000-0000-0000-000000000000',
        ] );

        $response->assertOk()->assertStructuredContent( ['error' => 'File not found.'] );
    }


    // ── TranscribeAudio ───────────────────────────────────────────────

    public function testTranscribeAudio()
    {
        $file = File::forceCreate( [
            'mime' => 'audio/mpeg',
            'lang' => 'en',
            'name' => 'Test audio',
            'path' => 'https://example.com/audio.mp3',
            'editor' => 'test',
        ] );

        Prisma::fake( [
            TextResponse::fromText( 'test transcription' )->withStructured( [
                ['start' => 0, 'end' => 1, 'text' => 'test transcription'],
            ] )
        ] );

        $response = CmsServer::actingAs( $this->user )->tool( \Aimeos\Cms\Tools\TranscribeAudio::class, [
            'file' => $file->id,
        ] );

        $response->assertOk()->assertSee( ['segments'] );
    }


    public function testTranscribeAudioWrongType()
    {
        $file = File::where( 'name', 'Test image' )->firstOrFail();
        Prisma::fake( [] );

        $response = CmsServer::actingAs( $this->user )->tool( \Aimeos\Cms\Tools\TranscribeAudio::class, [
            'file' => $file->id,
        ] );

        $response->assertOk()->assertSee( ['error'] );
    }
}
