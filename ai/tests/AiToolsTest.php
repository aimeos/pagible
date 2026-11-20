<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Mcp\CmsServer;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\PageView;
use Aimeos\Prisma\Exceptions\PrismaException;
use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Responses\FileResponse;
use Aimeos\Prisma\Responses\TextResponse;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
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

    public function testDescribeUnsupported()
    {
        $file = \Aimeos\Cms\Models\File::forceCreate( [
            'mime' => 'image/svg+xml',
            'name' => 'Vector',
            'path' => 'https://example.com/image.svg',
            'editor' => 'test',
        ] );

        $this->expectException( \Aimeos\Cms\Exception::class );
        \Aimeos\Cms\Ai::describe( \Aimeos\Cms\Media::media( $file ) );
    }


    public function testFilesKeepOrder()
    {
        $ids = $urls = [];

        foreach( [1, 2] as $num )
        {
            $file = \Aimeos\Cms\Models\File::forceCreate( [
                'mime' => 'image/png',
                'name' => 'Image ' . $num,
                'path' => $urls[] = 'https://picsum.photos/id/' . $num . '/100/100',
                'editor' => 'test',
            ] );
            $ids[] = $file->id;
        }

        $files = \Aimeos\Cms\Permission::system( fn() => \Aimeos\Cms\Media::load( array_reverse( $ids ), null, \Aimeos\Prisma\Files\Image::class ) );

        $this->assertSame( array_reverse( $urls ), array_map( fn( $file ) => $file->url(), $files ) );

        // images in formats the providers don't support are rejected
        $ids[] = \Aimeos\Cms\Models\File::forceCreate( [
            'mime' => 'image/gif',
            'name' => 'Animation',
            'path' => 'https://example.com/image.gif',
            'editor' => 'test',
        ] )->id;

        $this->assertThrows( fn() => \Aimeos\Cms\Permission::system( fn() => \Aimeos\Cms\Media::load( $ids, null, \Aimeos\Prisma\Files\Image::class ) ),
            \Aimeos\Cms\Exception::class, 'Image not found or not a JPEG, PNG or WebP image' );
    }


    public function testFilesUseDraft()
    {
        $file = File::forceCreate( [
            'mime' => 'image/png',
            'name' => 'Draft image',
            'path' => 'https://example.com/published.png',
            'editor' => 'test',
        ] );
        $version = $file->versions()->forceCreate( [
            'editor' => 'test',
            'data' => ['mime' => 'image/webp', 'path' => 'https://example.com/draft.webp'],
        ] );
        $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();

        $images = \Aimeos\Cms\Permission::system( fn() => \Aimeos\Cms\Media::load( [$file->id], null, \Aimeos\Prisma\Files\Image::class ) );

        $this->assertSame( 'https://example.com/draft.webp', $images[0]->url() );
        $this->assertSame( 'image/webp', $images[0]->mimeType() );
    }


    public function testFilesSizeLimit()
    {
        \Illuminate\Support\Facades\Storage::fake( 'public' )->put( 'cms/large.mp3', str_repeat( 'x', 2048 ) );
        config( ['cms.ai.maxmedia' => 0.001] );

        $file = File::forceCreate( [
            'mime' => 'audio/mpeg',
            'name' => 'Large audio',
            'path' => 'cms/large.mp3',
            'editor' => 'test',
        ] );

        $this->assertThrows( fn() => \Aimeos\Cms\Media::media( $file ), \Aimeos\Cms\Exception::class, 'Files sent to the AI service must not exceed 0.001 MB' );

        config( ['cms.ai.maxmedia' => 0] );
        $this->assertInstanceOf( \Aimeos\Prisma\Files\Audio::class, \Aimeos\Cms\Media::media( $file ) );
    }


    public function testFilesTotalSizeLimit()
    {
        $storage = \Illuminate\Support\Facades\Storage::fake( 'public' );
        $ids = [];

        foreach( ['a', 'b'] as $name )
        {
            $storage->put( 'cms/' . $name . '.mp3', str_repeat( 'x', 800 ) );
            $ids[] = File::forceCreate( [
                'mime' => 'audio/mpeg',
                'name' => 'Audio ' . $name,
                'path' => 'cms/' . $name . '.mp3',
                'editor' => 'test',
            ] )->id;
        }

        // each file is below the limit but not both together
        config( ['cms.ai.maxmedia' => 0.001] );
        $load = fn() => \Aimeos\Cms\Permission::system( fn() => \Aimeos\Cms\Media::load( $ids, null, \Aimeos\Prisma\Files\File::class ) );

        $this->assertThrows( $load, \Aimeos\Cms\Exception::class, 'Files sent to the AI service must not exceed 0.001 MB' );

        config( ['cms.ai.maxmedia' => 0.002] );
        $this->assertCount( 2, $load() );
    }


    public function testMaskedTotalSizeLimit()
    {
        $storage = Storage::fake( 'public' );
        $ids = [];

        foreach( ['image', 'mask'] as $name )
        {
            $storage->put( 'cms/' . $name . '.png', $this->pngBinary() . str_repeat( "\0", 800 - strlen( $this->pngBinary() ) ) );
            $ids[$name] = File::forceCreate( [
                'mime' => 'image/png',
                'name' => 'Masked ' . $name,
                'path' => 'cms/' . $name . '.png',
                'editor' => 'test',
            ] )->id;
        }

        // the image and the mask are each below the limit but not both together
        config( ['cms.ai.maxmedia' => 0.001] );
        Prisma::fake( [] );

        $response = CmsServer::actingAs( $this->user )->tool( \Aimeos\Cms\Tools\EraseImage::class, [
            'file' => $ids['image'],
            'mask' => $ids['mask'],
            'latest_id' => '00000000-0000-0000-0000-000000000000',
        ] );

        $response->assertSee( ['must not exceed 0.001 MB'] );
    }


    public function testRateLimitDisabled()
    {
        config( ['cms.ai.limit' => 0] );
        ( new \Aimeos\Cms\AiServiceProvider( $this->app ) )->boot();

        // 0 disables the limit instead of refusing all requests
        $limit = \Illuminate\Support\Facades\RateLimiter::limiter( 'cms-ai' )( request() );
        $this->assertInstanceOf( \Illuminate\Cache\RateLimiting\Unlimited::class, $limit );
    }


    public function testMaskSameAsFile()
    {
        $fake = Prisma::fake( [] );

        $response = CmsServer::actingAs( $this->user )->tool( \Aimeos\Cms\Tools\EraseImage::class, [
            'file' => '00000000-0000-0000-0000-000000000001',
            'mask' => '00000000-0000-0000-0000-000000000001',
            'latest_id' => '00000000-0000-0000-0000-000000000000',
        ] );

        $response->assertHasErrors( ['The mask must be another image file than the one to edit.'] );
        $this->assertCount( 0, $fake->calls() );
    }


    public function testImagesMissing()
    {
        $this->expectException( \Aimeos\Cms\Exception::class );
        \Aimeos\Cms\Permission::system( fn() => \Aimeos\Cms\Media::load( ['00000000-0000-0000-0000-000000000000'], null ) );
    }


    public function testRefineContent()
    {
        $page = PageView::where( 'name', 'Home' )->first();

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

        $response = $this->assertExecutionLimitRestored( fn() => CmsServer::actingAs( $this->user )
            ->tool( \Aimeos\Cms\Tools\RefineContent::class, [
                'id' => $page->id,
                'prompt' => 'Make the content more engaging',
            ] )
        );

        $response->assertOk()->assertSee( ['content'] );
    }


    public function testRefineContentVariant()
    {
        $page = PageView::where( 'name', 'Home' )->firstOrFail();

        \Aimeos\Cms\Permission::system( function() use ( $page ) {
            \Aimeos\Cms\Facades\Variants::add( $page->id, 'de' );
            \Aimeos\Cms\Facades\Variants::save( $page->id, 'de', ['content' => [
                ['id' => 'de0001', 'type' => 'text', 'group' => 'main', 'data' => ['text' => 'Deutscher Text']],
            ]] );
        } );

        $fake = Prisma::fake( [
            TextResponse::fromText( '' )->withStructured( ['contents' => [
                ['id' => 'de0001', 'type' => 'text', 'group' => 'main', 'data' => ['text' => 'Verbesserter Text']],
            ]] )
        ] );

        CmsServer::actingAs( $this->user )->tool( \Aimeos\Cms\Tools\RefineContent::class, [
            'id' => $page->id,
            'prompt' => 'Make the content more engaging',
            'lang' => 'de',
        ] )->assertOk()->assertSee( 'Verbesserter Text' );

        // the content of the German variant is refined, not the source content
        $fake->assertCalled( 'structure', fn( $args ) => str_contains( $args[0], 'Deutscher Text' ) );
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


    public function testFilesChecksUser()
    {
        $user = new \App\Models\User( ['cmsperms' => ['page:view']] );
        $ids = ['00000000-0000-0000-0000-000000000000'];

        $this->assertThrows( fn() => \Aimeos\Cms\Media::load( $ids, null ), \Aimeos\Cms\Exceptions\PermissionException::class );
        $this->assertThrows( fn() => \Aimeos\Cms\Media::load( $ids, $user ), \Aimeos\Cms\Exceptions\PermissionException::class );
        $this->assertSame( [], \Aimeos\Cms\Media::load( [], null ) );
        $this->assertThrows( fn() => \Aimeos\Cms\Media::load( $ids, $this->user, \Aimeos\Prisma\Files\File::class ), \Aimeos\Cms\Exception::class, 'File not found or not supported' );
        $this->assertThrows( fn() => \Aimeos\Cms\Permission::system( fn() => \Aimeos\Cms\Media::load( $ids, null ) ), \Aimeos\Cms\Exception::class );
    }


    public function testStoreRejectsSvg()
    {
        $svg = base64_encode( '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>' );

        $this->assertThrows( fn() => \Aimeos\Cms\Media::store( $svg, 'image', fn() => null ), \Aimeos\Cms\Exception::class, 'The AI service returned an invalid image' );
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
        ] )->assertOk()->assertStructuredContent( ['error' => 'Insufficient permissions'] );
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
            'latest_id' => $file->latest_id,
        ] );

        $response->assertOk()->assertSee( ['id'] );
        $this->assertGreaterThan( $before, $file->versions()->count() );
    }


    public function testRepaintImageInvalidUpload()
    {
        Storage::fake( 'public' );
        config( ['cms.upload.filesize' => 0] );
        $file = File::where( 'name', 'Test image' )->firstOrFail();

        Prisma::fake( [FileResponse::fromBinary( $this->pngBinary(), 'image/png' )] );

        $response = CmsServer::actingAs( $this->user )->tool( \Aimeos\Cms\Tools\RepaintImage::class, [
            'file' => $file->id,
            'prompt' => 'Make the sky a sunset',
            'latest_id' => $file->latest_id,
        ] );

        $response->assertOk()->assertSee( ['error', 'exceeds the maximum'] );
    }


    public function testRepaintImageRequiresLatestId()
    {
        Prisma::fake( [] );
        $file = File::where( 'name', 'Test image' )->firstOrFail();

        $response = CmsServer::actingAs( $this->user )->tool( \Aimeos\Cms\Tools\RepaintImage::class, [
            'file' => $file->id,
            'prompt' => 'Make the sky a sunset',
        ] );

        $response->assertHasErrors( ['latest_id'] );
    }


    public function testRepaintImageNotFound()
    {
        Prisma::fake( [] );

        $response = CmsServer::actingAs( $this->user )->tool( \Aimeos\Cms\Tools\RepaintImage::class, [
            'file' => '00000000-0000-0000-0000-000000000000',
            'prompt' => 'Make the sky a sunset',
            'latest_id' => '00000000-0000-0000-0000-000000000000',
        ] );

        $response->assertOk()->assertStructuredContent( ['error' => 'Image not found or not a JPEG, PNG or WebP image'] );
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


    public function testDescribeFileProviderError()
    {
        config( ['app.debug' => false] );
        $file = File::where( 'name', 'Test image' )->firstOrFail();

        Prisma::fake( [new PrismaException( 'Your prepayment credits are depleted.' )] );
        Log::spy();

        $response = CmsServer::actingAs( $this->user )->tool( \Aimeos\Cms\Tools\DescribeFile::class, [
            'file' => $file->id,
        ] );

        $response->assertOk()->assertStructuredContent( ['error' => 'The AI service failed'] );
        Log::shouldHaveReceived( 'error' )->once()->with( 'AI service error', \Mockery::on( fn( $ctx ) => $ctx['tool'] === 'DescribeFile' ) );
    }


    public function testDescribeFileNotFound()
    {
        Prisma::fake( [] );

        $response = CmsServer::actingAs( $this->user )->tool( \Aimeos\Cms\Tools\DescribeFile::class, [
            'file' => '00000000-0000-0000-0000-000000000000',
        ] );

        $response->assertOk()->assertStructuredContent( ['error' => 'File not found or not supported, images must be JPEG, PNG or WebP'] );
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

        $response->assertOk()->assertStructuredContent( ['error' => 'Audio not found or not a file of type "audio"'] );
    }
}
