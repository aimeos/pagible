<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\Cms\Mcp\CmsServer;


class ToolsTest extends McpTestAbstract
{
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


    // ── Discovery & Configuration ──────────────────────────────────────

    public function testGetLocales()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GetLocales::class );

        $response->assertOk()->assertStructuredContent( ['locales' => ['en', 'de']] );
    }


    public function testGetSchemas()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GetSchemas::class );

        $response->assertOk()->assertSee( ['heading'] );
    }


    // ── AI Tools ───────────────────────────────────────────────────────

    public function testTranslateContent()
    {
        config(['cms.ai.translate.api_key' => 'test-key']);

        $texts = ['Hello', 'World'];
        $expected = ['Hallo', 'Welt'];

        $response = \Aimeos\Prisma\Responses\TextResponse::fromTexts( $expected );
        \Aimeos\Prisma\Prisma::fake( [$response] );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\TranslateContent::class, [
            'texts' => ['Hello World'],
            'to' => 'de',
        ] );

        $response->assertOk()->assertSee( ['translations'] );
    }
}
