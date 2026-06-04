<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\Cms\Mcp\CmsServer;
use Aimeos\Cms\Models\Page;
use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Responses\TextResponse;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;


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
}
