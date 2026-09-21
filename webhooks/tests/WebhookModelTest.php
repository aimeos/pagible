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
        $this->assertStringNotContainsString( self::secret( 'test' ), $raw['secrets'] );
        // Only reason, status and time, which don't need encryption
        $this->assertSame( ['reason' => 'http_error', 'status' => 503], json_decode( $raw['last_error'], true ) );
        $this->assertSame( ['page.published', 'page.deleted'], $webhook->events );
        $this->assertSame( 503, $webhook->last_error['status'] );
        $this->assertInstanceOf( Carbon::class, $webhook->last_success_at );
        $this->assertSame( 'https://example.com/…', $webhook->endpoint );
        $this->assertNotEmpty( $webhook->id );
        $this->assertArrayNotHasKey( 'url', $webhook->toArray() );
        $this->assertArrayNotHasKey( 'secrets', $webhook->toArray() );
    }


    public function testPreviousSecretIsEncryptedAndReencrypted() : void
    {
        $webhook = $this->webhook( [
            'secrets' => self::secrets( 'test', ['old' => now()->addHour()] ),
            'updated_at' => '2026-09-14 12:00:00',
        ] );
        $before = $webhook->getRawOriginal( 'secrets' );

        $this->assertStringNotContainsString( self::secret( 'old' ), $before );

        $this->artisan( 'cms:webhooks:reencrypt', ['--tenant' => 'test'] )->assertSuccessful();
        $webhook->refresh();

        $this->assertNotSame( $before, $webhook->getRawOriginal( 'secrets' ) );
        $this->assertSame( [self::secret( 'test' ), self::secret( 'old' )], $webhook->secrets() );
        $this->assertSame( '2026-09-14 12:00:00', $webhook->updated_at?->format( 'Y-m-d H:i:s' ) );
    }


    public function testUndecryptableValuesAreReportedAndOverwritten() : void
    {
        $webhook = $this->undecryptable( $this->webhook( ['last_error' => ['reason' => 'timeout']] ) )->refresh();

        $this->assertFalse( $webhook->decryptable() );
        $this->assertSame( ['reason' => 'invalid_encryption'], $webhook->failure() );
        $this->assertSame( '[invalid endpoint]', $webhook->endpoint );

        // New values aren't compared with the old ones
        $webhook->forceFill( ['url' => 'https://example.com/hooks/new', 'secrets' => self::secrets( 'new' ), 'last_error' => null] )->save();
        $webhook->refresh();

        $this->assertTrue( $webhook->decryptable() );
        $this->assertNull( $webhook->failure() );
        $this->assertSame( 'https://example.com/hooks/new', $webhook->url );
        $this->assertSame( [self::secret( 'new' )], $webhook->secrets() );
    }


    public function testFailureTimeIsReturnedAsDate() : void
    {
        $webhook = $this->webhook( ['last_error' => ['reason' => 'timeout', 'at' => '2026-09-15T14:00:00+02:00']] );

        $this->assertSame( '2026-09-15T12:00:00.000000Z', $webhook->failure()['at']?->toJSON() );

        // Invalid values don't prevent listing all subscriptions
        $webhook->last_error = ['reason' => 'timeout', 'at' => 'invalid'];

        $this->assertSame( ['reason' => 'timeout', 'at' => null], $webhook->failure() );
    }


    public function testTenantScopeIsolatesSubscriptions() : void
    {
        $this->webhook();
        Tenancy::run( 'other', fn() => $this->webhook( ['url' => 'https://other.example/hook'] ) );

        $this->assertCount( 1, Webhook::all() );
        $this->assertCount( 2, Webhook::withoutTenancy()->get() );
    }
}
