<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\WebhookClient;
use Aimeos\Cms\WebhookConfig;
use Aimeos\Cms\WebhookException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;


class WebhookConfigTest extends WebhookTestAbstract
{
    protected function tearDown() : void
    {
        config( [
            'cache.default' => 'array',
            'cms.webhooks.deny_cidrs' => [],
            'cms.webhooks.endpoints' => [],
            'cms.webhooks.queue.connection' => null,
        ] );
        parent::tearDown();
    }


    public function testValidEndpointsHaveNoProblems() : void
    {
        config( ['cache.default' => 'file', 'cms.webhooks.endpoints' => [
            'indexer' => self::endpoint( [] ),
            'search_2' => self::endpoint( ['url' => 'https://search.internal/hook', 'tenants' => ['acme']] ),
            'ca-file' => self::endpoint( ['url' => 'https://10.0.0.5:8443/hook', 'ca' => __FILE__] ),
        ]] );

        $this->assertSame( [], app( WebhookConfig::class )->problems() );
    }


    /**
     * @param array<string, mixed>|string $endpoint
     */
    #[DataProvider( 'invalidEndpoints' )]
    public function testInvalidEndpointIsReported( int|string $name, array|string $endpoint, string $reason ) : void
    {
        config( ['cache.default' => 'file', 'cms.webhooks.endpoints' => [$name => $endpoint]] );

        $this->assertSame( ['cms.webhooks.endpoints.' . $name => $reason], app( WebhookConfig::class )->problems() );
    }


    /**
     * @return iterable<string, array{int|string, array<string, mixed>|string, string}>
     */
    public static function invalidEndpoints() : iterable
    {
        yield 'list' => [0, self::endpoint( [] ), 'invalid_name'];
        yield 'dotted name' => ['search.index', self::endpoint( [] ), 'invalid_name'];
        yield 'long name' => [str_repeat( 'n', 65 ), self::endpoint( [] ), 'invalid_name'];
        yield 'not an array' => ['indexer', 'http://indexer/hook', 'invalid_endpoint'];
        yield 'unknown key' => ['indexer', self::endpoint( ['headers' => []] ), 'invalid_endpoint'];
        yield 'missing url' => ['indexer', self::endpoint( ['url' => null] ), 'invalid_url'];
        yield 'invalid url' => ['indexer', self::endpoint( ['url' => 'ftp://indexer/hook'] ), 'invalid_url'];
        yield 'metadata url' => ['indexer', self::endpoint( ['url' => 'http://169.254.169.254/hook'] ), 'destination_not_allowed'];
        yield 'missing secret' => ['indexer', self::endpoint( ['secret' => null] ), 'invalid_secret'];
        yield 'plain secret' => ['indexer', self::endpoint( ['secret' => str_repeat( 's', 32 )] ), 'invalid_secret'];
        yield 'short secret' => ['indexer', self::endpoint( ['secret' => 'whsec_' . base64_encode( str_repeat( 's', 23 ) )] ), 'invalid_secret'];
        yield 'empty secrets' => ['indexer', self::endpoint( ['secret' => ['', null]] ), 'invalid_secret'];
        yield 'invalid secret in list' => ['indexer', self::endpoint( ['secret' => [self::secret( 's' ), 'whsec_short']] ), 'invalid_secret'];
        yield 'secret not a string' => ['indexer', self::endpoint( ['secret' => [self::secret( 's' ), ['nested']]] ), 'invalid_secret'];
        yield 'no events' => ['indexer', self::endpoint( ['events' => []] ), 'invalid_events'];
        yield 'unknown event' => ['indexer', self::endpoint( ['events' => ['page.saved']] ), 'invalid_events'];
        yield 'empty tenants' => ['indexer', self::endpoint( ['tenants' => []] ), 'invalid_tenants'];
        yield 'unset tenants variable' => ['indexer', self::endpoint( ['tenants' => ''] ), 'invalid_tenants'];
        yield 'tenant map' => ['indexer', self::endpoint( ['tenants' => ['a' => 'acme']] ), 'invalid_tenants'];
        yield 'missing ca' => ['indexer', self::endpoint( ['ca' => '/nonexistent/ca.pem'] ), 'invalid_ca'];
        yield 'ca not a string' => ['indexer', self::endpoint( ['ca' => false] ), 'invalid_ca'];
    }


    public function testProblemsNameTheSettingsToFix() : void
    {
        $default = config( 'queue.default' );
        config( [
            'cache.default' => 'file',
            'cms.webhooks.deny_cidrs' => ['not-a-cidr'],
            'cms.webhooks.endpoints' => ['indexer' => self::endpoint( ['url' => 'http://10.0.0.5:8080/hook'] )],
            'queue.default' => 'missing',
        ] );

        try {
            $problems = app( WebhookConfig::class )->problems();
        } finally {
            config( ['queue.default' => $default] );
        }

        // The endpoint isn't reported because only the deny list must be fixed
        $this->assertSame( [
            'cms.webhooks.deny_cidrs' => 'invalid_policy',
            'cms.webhooks.queue.connection' => 'invalid_queue',
        ], $problems );
    }


    public function testProblemsResolveHostsOnlyOnRequest() : void
    {
        $client = new StubWebhookClient();
        $client->addresses = [];
        $config = new WebhookConfig( $client );
        config( ['cache.default' => 'file', 'cms.webhooks.endpoints' => [
            'indexer' => self::endpoint( [] ),
            'metadata' => self::endpoint( ['url' => 'http://metadata.internal/hook'] ),
        ]] );

        $this->assertSame( [], $config->problems() );
        $this->assertSame( [
            'cms.webhooks.endpoints.indexer' => 'resolution_failed',
            'cms.webhooks.endpoints.metadata' => 'resolution_failed',
        ], $config->problems( true ) );

        $client->addresses = ['169.254.169.254'];

        $this->assertSame( [
            'cms.webhooks.endpoints.indexer' => 'destination_not_allowed',
            'cms.webhooks.endpoints.metadata' => 'destination_not_allowed',
        ], $config->problems( true ) );

        $client->addresses = ['10.0.0.5'];

        $this->assertSame( [], $config->problems( true ) );
    }


    public function testProblemsReportUnsuitableQueueTimesAndCache() : void
    {
        $retryAfter = config( 'queue.connections.database.retry_after' );
        config( ['queue.connections.database.retry_after' => 23] );

        try {
            $problems = app( WebhookConfig::class )->problems();
        } finally {
            config( ['queue.connections.database.retry_after' => $retryAfter] );
        }

        $this->assertSame( [
            'queue.connections.database.retry_after' => 'invalid_retry_after',
            'cache.default' => 'invalid_cache',
        ], $problems );
    }


    public function testProblemsReserveTimeForResolvingAndRecording() : void
    {
        $retryAfter = config( 'queue.connections.database.retry_after' );
        config( ['cache.default' => 'file'] );

        try {
            // Both timeouts plus 5 seconds for resolving the host name and 5 for recording the result
            config( ['queue.connections.database.retry_after' => 24] );
            $this->assertSame( [], app( WebhookConfig::class )->problems() );

            config( ['cms.webhooks.http.timeout' => 11] );
            $this->assertSame(
                ['queue.connections.database.retry_after' => 'invalid_retry_after'],
                app( WebhookConfig::class )->problems()
            );
        } finally {
            config( ['cms.webhooks.http.timeout' => 10, 'queue.connections.database.retry_after' => $retryAfter] );
        }
    }


    public function testStalledQueueIsNoConfigurationProblem() : void
    {
        $config = app( WebhookConfig::class );
        Carbon::setTestNow( '2026-09-15 12:00:00 UTC' );

        try {
            $config->queued();
            // Later deliveries don't change since when deliveries are waiting
            Carbon::setTestNow( '2026-09-15 12:05:00 UTC' );
            $config->queued();

            $this->assertNull( $config->stalled() );

            Carbon::setTestNow( '2026-09-15 12:10:00 UTC' );
            $this->assertSame( now()->subMinutes( 10 )->timestamp, $config->stalled() );
            $this->assertArrayNotHasKey( 'cms.webhooks.queue.name', $config->problems() );

            $config->processed();
            $this->assertNull( $config->stalled() );

            // Cache stores like Redis return the stored timestamp as string
            Cache::put( 'cms-webhooks-waiting', (string) now()->subMinutes( 10 )->timestamp, 60 );
            $this->assertSame( now()->subMinutes( 10 )->timestamp, $config->stalled() );
        } finally {
            Carbon::setTestNow();
        }
    }


    public function testProblemsReportNullCache() : void
    {
        config( ['cache.default' => 'null', 'cache.stores.null' => ['driver' => 'null']] );

        $this->assertSame( ['cache.default' => 'invalid_cache'], app( WebhookConfig::class )->problems() );
    }


    public function testSubscribedMatchesEventsAndTenants() : void
    {
        config( ['cms.webhooks.endpoints' => [
            'indexer' => self::endpoint( ['tenants' => ['acme']] ),
            'all' => self::endpoint( ['url' => 'https://search.internal/hook', 'events' => ['page.published', 'file.purged']] ),
        ]] );

        $config = app( WebhookConfig::class );

        $this->assertSame( ['indexer', 'all'], array_keys( $config->subscribed( 'page.published', 'acme' ) ) );
        $this->assertSame( ['all'], array_keys( $config->subscribed( 'page.published', 'other' ) ) );
        $this->assertSame( ['all'], array_keys( $config->subscribed( 'file.purged', 'acme' ) ) );
        $this->assertSame( [], $config->subscribed( 'page.moved', 'acme' ) );
    }


    public function testUnsetOptionalValuesAreIgnored() : void
    {
        // e.g. from environment variables which aren't set, the helper removes NULL values
        config( ['cms.webhooks.endpoints' => [
            'indexer' => self::endpoint( [] ) + ['tenants' => null, 'ca' => null],
            'search' => self::endpoint( ['url' => 'https://search.internal/hook', 'ca' => ''] ),
        ]] );

        $config = app( WebhookConfig::class );

        $this->assertSame( ['indexer', 'search'], array_keys( $config->subscribed( 'page.published', 'acme' ) ) );
        $this->assertNull( $config->endpoint( 'indexer', 'page.published', 'acme' )['ca'] );
        $this->assertNull( $config->endpoint( 'search', 'page.published', 'acme' )['ca'] );
    }


    public function testSubscribedRevisionChangesWithDestination() : void
    {
        config( ['cms.webhooks.endpoints' => ['indexer' => self::endpoint( [] )]] );
        $config = app( WebhookConfig::class );
        $revision = $config->subscribed( 'page.published', 'acme' )['indexer'];

        config( ['cms.webhooks.endpoints' => ['indexer' => self::endpoint( ['tenants' => ['acme'], 'ca' => __FILE__] )]] );
        $this->assertSame( $revision, $config->subscribed( 'page.published', 'acme' )['indexer'] );

        config( ['cms.webhooks.endpoints' => ['indexer' => self::endpoint( ['url' => 'http://indexer:9090/hook'] )]] );
        $this->assertNotSame( $revision, $config->subscribed( 'page.published', 'acme' )['indexer'] );

        // Changed secrets sign queued deliveries with the new secrets instead of cancelling them
        config( ['cms.webhooks.endpoints' => ['indexer' => self::endpoint( ['secret' => [self::secret( 't' ), self::secret( 's' )]] )]] );
        $this->assertSame( $revision, $config->subscribed( 'page.published', 'acme' )['indexer'] );
    }


    public function testSubscribedSkipsAndLogsInvalidEndpointsOnce() : void
    {
        config( ['cms.webhooks.endpoints' => [
            'indexer' => self::endpoint( [] ),
            'broken' => self::endpoint( ['secret' => 'short'] ),
        ]] );
        Log::spy();

        $config = app( WebhookConfig::class );

        $this->assertSame( ['indexer'], array_keys( $config->subscribed( 'page.published', 'acme' ) ) );
        $this->assertSame( ['indexer'], array_keys( $config->subscribed( 'page.deleted', 'acme' ) ) );

        Log::shouldHaveReceived( 'warning' )->once()->with(
            'cms.webhook.endpoint_invalid', \Mockery::on( fn( array $data ) =>
                $data['endpoint'] === 'broken' && $data['reason'] === 'invalid_secret'
                && !in_array( 'short', $data, true )
            )
        );
    }


    public function testWarnLogsDifferentProblemsSeparately() : void
    {
        Log::spy();
        $config = app( WebhookConfig::class );

        $config->warn( 'cms.webhook.delivery_blocked', ['reason' => 'invalid_policy'] );
        $config->warn( 'cms.webhook.delivery_blocked', ['reason' => 'invalid_policy'] );
        $config->warn( 'cms.webhook.delivery_blocked', ['reason' => 'invalid_queue'] );

        // Names from the configuration may contain invalid UTF-8 which isn't JSON encodable
        $config->warn( 'cms.webhook.endpoint_invalid', ['endpoint' => "first\xB1"] );
        $config->warn( 'cms.webhook.endpoint_invalid', ['endpoint' => "second\xB1"] );

        Log::shouldHaveReceived( 'warning' )->times( 4 );
    }


    public function testEndpointReturnsSubscribedDestination() : void
    {
        config( ['cms.webhooks.endpoints' => ['indexer' => self::endpoint( ['tenants' => ['acme'], 'ca' => __FILE__] )]] );
        $config = app( WebhookConfig::class );

        $this->assertSame( [
            'url' => 'http://indexer:8080/hook',
            'secrets' => [self::secret( 's' )],
            'ca' => __FILE__,
            'internal' => true,
        ], $config->endpoint( 'indexer', 'page.published', 'acme' ) );

        $this->assertNull( $config->endpoint( 'indexer', 'file.purged', 'acme' ) );
        $this->assertNull( $config->endpoint( 'indexer', 'page.published', 'other' ) );
        $this->assertNull( $config->endpoint( 'search', 'page.published', 'acme' ) );
    }


    public function testEndpointReturnsSecretListWithoutEmptyEntries() : void
    {
        $secrets = [self::secret( 't' ), '', null, self::secret( 's' )];
        config( ['cms.webhooks.endpoints' => ['indexer' => self::endpoint( ['secret' => $secrets] )]] );

        $this->assertSame(
            [self::secret( 't' ), self::secret( 's' )],
            app( WebhookConfig::class )->endpoint( 'indexer', 'page.published', 'acme' )['secrets'] ?? null
        );
    }


    public function testEndpointThrowsIfInvalid() : void
    {
        config( ['cms.webhooks.endpoints' => ['indexer' => self::endpoint( ['events' => ['page.saved']] )]] );

        $this->expectException( WebhookException::class );
        app( WebhookConfig::class )->endpoint( 'indexer', 'page.published', 'acme' );
    }


    public function testConnectionRejectsMissingAndNullQueues() : void
    {
        $config = app( WebhookConfig::class );

        config( ['cms.webhooks.queue.connection' => 'sync'] );
        $this->assertSame( 'sync', $config->connection() );

        config( ['cms.webhooks.queue.connection' => 'missing'] );
        $this->assertNull( $config->connection() );

        config( [
            'cms.webhooks.queue.connection' => 'none',
            'queue.connections.none' => ['driver' => 'null'],
        ] );
        $this->assertNull( $config->connection() );

        config( ['cms.webhooks.queue.connection' => null] );
        $this->assertSame( config( 'queue.default' ), $config->connection() );
    }


    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private static function endpoint( array $values ) : array
    {
        return array_filter( $values + [
            'url' => 'http://indexer:8080/hook',
            'secret' => self::secret( 's' ),
            'events' => ['page.published', 'page.deleted'],
        ], fn( mixed $value ) => $value !== null );
    }
}
