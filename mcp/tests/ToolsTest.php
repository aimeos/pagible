<?php

/**
 * @license MIT, https://opensource.org/license/mit
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

        $response->assertOk()
            ->assertSee( ['heading'] )
            ->assertSee( ['contents'] )
            ->assertSee( ['anyOf'] );
    }
}
