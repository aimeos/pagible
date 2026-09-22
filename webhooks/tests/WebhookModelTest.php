<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Webhook;
use Aimeos\Cms\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;


class WebhookModelTest extends WebhookTestAbstract
{
    use RefreshDatabase;


    public function testModelAttributesAndCasts() : void
    {
        $webhook = $this->webhook( [
            'events' => ['page.published', 'page.deleted'],
            'last_error' => ['reason' => 'http_error', 'status' => 503],
            'last_success_at' => '2026-09-15 12:00:00',
        ] );
        $raw = Webhook::withoutTenancy()->whereKey( $webhook->id )->firstOrFail()->getAttributes();

        // Only the secrets are encrypted
        $this->assertSame( 'https://example.com/hooks/cms', $raw['url'] );
        $this->assertStringNotContainsString( self::secret( 'test' ), $raw['secrets'] );
        // Only reason, status and time, which don't need encryption
        $this->assertSame( ['reason' => 'http_error', 'status' => 503], json_decode( $raw['last_error'], true ) );
        $this->assertSame( ['page.published', 'page.deleted'], $webhook->events );
        $this->assertSame( 503, $webhook->last_error['status'] );
        $this->assertInstanceOf( Carbon::class, $webhook->last_success_at );
        $this->assertSame( 'https://example.com/hooks/', $webhook->endpoint );
        $this->assertNotEmpty( $webhook->id );
        $this->assertArrayNotHasKey( 'url', $webhook->toArray() );
        $this->assertArrayNotHasKey( 'secrets', $webhook->toArray() );
    }


    #[DataProvider( 'endpoints' )]
    public function testEndpointHidesCredentials( string $url, string $endpoint ) : void
    {
        $this->assertSame( $endpoint, ( new Webhook() )->forceFill( ['url' => $url] )->endpoint );
    }


    /**
     * @return iterable<string, array{string, string}>
     */
    public static function endpoints() : iterable
    {
        yield 'path' => ['https://hooks.slack.com/services/T0/B0/XXXX', 'https://hooks.slack.com/services/T0/B0/'];
        yield 'trailing slash' => ['https://example.com/hooks/token/', 'https://example.com/hooks/'];
        yield 'query' => ['https://example.com/hooks/cms?token=secret', 'https://example.com/hooks/'];
        yield 'root' => ['https://example.com/?token=secret', 'https://example.com/'];
        yield 'port' => ['https://example.com:8443/token', 'https://example.com:8443/'];
    }


    public function testPreviousSecretIsEncrypted() : void
    {
        $webhook = $this->webhook( ['secrets' => self::secrets( 'test', ['old' => now()->addHour()] )] );

        $this->assertStringNotContainsString( self::secret( 'old' ), $webhook->getRawOriginal( 'secrets' ) );
        $this->assertSame( [self::secret( 'test' ), self::secret( 'old' )], $webhook->refresh()->secrets() );
    }


    public function testUndecryptableValuesAreReportedAndOverwritten() : void
    {
        $webhook = $this->undecryptable( $this->webhook( ['last_error' => ['reason' => 'timeout']] ) )->refresh();

        $this->assertFalse( $webhook->decryptable() );
        $this->assertSame( ['reason' => 'invalid_encryption'], $webhook->failure() );
        $this->assertSame( 'https://example.com/hooks/', $webhook->endpoint );

        // New values aren't compared with the old ones
        $webhook->forceFill( ['secrets' => self::secrets( 'new' ), 'last_error' => null] )->save();
        $webhook->refresh();

        $this->assertTrue( $webhook->decryptable() );
        $this->assertNull( $webhook->failure() );
        $this->assertSame( [self::secret( 'new' )], $webhook->secrets() );
    }


    public function testFailureTimeIsReturnedAsDate() : void
    {
        $webhook = $this->webhook( ['last_error' => ['reason' => 'timeout', 'at' => '2026-09-15T14:00:00+02:00']] );

        $this->assertSame( '2026-09-15T12:00:00.000000Z', $webhook->failure()['at']?->toJSON() );
    }


    public function testTenantScopeIsolatesSubscriptions() : void
    {
        $this->webhook();
        Tenancy::run( 'other', fn() => $this->webhook( ['url' => 'https://other.example/hook'] ) );

        $this->assertCount( 1, Webhook::all() );
        $this->assertCount( 2, Webhook::withoutTenancy()->get() );
    }
}
