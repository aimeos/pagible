<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Webhook;
use Aimeos\Cms\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;


class WebhookModelTest extends WebhookTestAbstract
{
    use RefreshDatabase;


    public function testEncryptedModelAttributesAndCasts() : void
    {
        $webhook = $this->webhook( [
            'events' => ['page.published', 'page.deleted'],
            'last_error' => ['reason' => 'http_error', 'status' => 503],
            'last_success_at' => '2026-09-15 12:00:00',
        ] );
        $raw = Webhook::withoutTenancy()->whereKey( $webhook->id )->firstOrFail()->getAttributes();

        $this->assertNotSame( 'https://example.com/hooks/cms', $raw['url'] );
        $this->assertNotSame( 'test-secret', $raw['secret'] );
        $this->assertSame( ['page.published', 'page.deleted'], $webhook->events );
        $this->assertSame( 503, $webhook->last_error['status'] );
        $this->assertInstanceOf( Carbon::class, $webhook->last_success_at );
        $this->assertSame( 'https://example.com/…', $webhook->endpoint );
        $this->assertNotEmpty( $webhook->id );
        $this->assertArrayNotHasKey( 'url', $webhook->toArray() );
        $this->assertArrayNotHasKey( 'secret', $webhook->toArray() );
    }


    public function testTenantScopeIsolatesSubscriptions() : void
    {
        $this->webhook();
        Tenancy::run( 'other', fn() => $this->webhook( ['url' => 'https://other.example/hook'] ) );

        $this->assertCount( 1, Webhook::all() );
        $this->assertCount( 2, Webhook::withoutTenancy()->get() );
    }
}
