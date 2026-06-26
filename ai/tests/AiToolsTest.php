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


    /**
     * Returns the binary data of a small valid PNG image.
     */
    protected function pngBinary() : string
    {
        $im = imagecreatetruecolor( 8, 8 );
        ob_start();
        imagepng( $im );
        $data = (string) ob_get_clean();
        imagedestroy( $im );

        return $data;
    }
}
