<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\Cms\Mcp\CmsServer;
use Aimeos\Cms\Models\Element;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;


class ElementToolsTest extends McpTestAbstract
{
    use CmsWithMigrations;
    use DatabaseTruncation;

    protected $connectionsToTransact = [];


    protected function beforeTruncatingDatabase(): void
    {
        // In-memory SQLite databases don't persist across test classes
        RefreshDatabaseState::$migrated = false;
    }


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


    // ── Read Elements ──────────────────────────────────────────────────

    public function testGetElement()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
        $element = Element::where( 'name', 'Shared footer' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GetElement::class, [
            'id' => $element->id,
        ] );

        $response->assertOk()->assertSee( ['Shared footer', 'footer', 'latest_version'] );
    }


    public function testGetElementNotFound()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GetElement::class, [
            'id' => '00000000-0000-0000-0000-000000000000',
        ] );

        $response->assertOk()->assertSee( ['error'] );
    }


    public function testSearchElementsNoTerm()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SearchElements::class );

        $response->assertOk()->assertSee( ['Shared footer', 'footer'] );
    }


    public function testSearchElementsFilterType()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SearchElements::class, [
            'type' => 'footer',
        ] );

        $response->assertOk()->assertSee( ['Shared footer'] );
    }


    public function testSearchElements()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
        sleep( 5 ); // Wait for SQL Server to update fulltext index

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SearchElements::class, [
            'term' => 'footer',
        ] );
        $response->assertOk()->assertSee( ['Shared footer'] );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SearchElements::class, [
            'type' => 'footer',
        ] );
        $response->assertOk()->assertSee( ['Shared footer'] );
    }


    // ── Write Elements ─────────────────────────────────────────────────

    public function testAddElement()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\AddElement::class, [
            'type' => 'heading',
            'name' => 'Test heading',
            'lang' => 'en',
            'data' => ['title' => 'Hello', 'level' => '2'],
        ] );

        $response->assertOk()->assertSee( ['Test heading', 'heading'] );
    }


    public function testSaveElement()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
        $element = Element::where( 'name', 'Shared footer' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SaveElement::class, [
            'id' => $element->id,
            'name' => 'Updated footer',
        ] );

        $response->assertOk()->assertSee( ['Updated footer'] );
    }


    public function testSaveElementNotFound()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SaveElement::class, [
            'id' => '00000000-0000-0000-0000-000000000000',
            'name' => 'Nope',
        ] );

        $response->assertOk()->assertSee( ['error'] );
    }


    public function testPublishElement()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
        $element = Element::where( 'name', 'Shared footer' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\PublishElement::class, [
            'id' => [$element->id],
        ] );

        $response->assertOk()->assertSee( ['published'] );
    }


    public function testPublishElementScheduled()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
        $element = Element::where( 'name', 'Shared footer' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\PublishElement::class, [
            'id' => [$element->id],
            'at' => '2099-06-01 00:00:00',
        ] );

        $response->assertOk()->assertSee( ['scheduled_at', '2099-06-01'] );
    }


    public function testDropElement()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
        $element = Element::where( 'name', 'Shared footer' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\DropElement::class, [
            'id' => $element->id,
        ] );

        $response->assertOk()->assertSee( ['Shared footer'] );
        $this->assertSoftDeleted( 'cms_elements', ['id' => $element->id] );
    }


    public function testDropElementNotFound()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\DropElement::class, [
            'id' => '00000000-0000-0000-0000-000000000000',
        ] );

        $response->assertOk()->assertSee( ['error'] );
    }


    public function testRestoreElement()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
        $element = Element::where( 'name', 'Shared footer' )->first();
        $element->delete();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\RestoreElement::class, [
            'id' => $element->id,
        ] );

        $response->assertOk()->assertSee( ['Shared footer'] );
        $this->assertNull( Element::find( $element->id )->deleted_at );
    }


    public function testRestoreElementNotDeleted()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
        $element = Element::where( 'name', 'Shared footer' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\RestoreElement::class, [
            'id' => $element->id,
        ] );

        $response->assertOk()->assertSee( ['error', 'not deleted'] );
    }
}
