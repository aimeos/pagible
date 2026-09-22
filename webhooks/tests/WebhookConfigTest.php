<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\WebhookConfig;
use Aimeos\Cms\WebhookException;
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
            'search_2' => self::endpoint( ['url' => 'https://search.internal/hook'] ),
            'private-ip' => self::endpoint( ['url' => 'https://10.0.0.5:8443/hook'] ),
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
        // Restricting tenants isn't supported, the endpoint mustn't silently receive the events of all tenants
        yield 'tenants key' => ['indexer', self::endpoint( ['tenants' => ['acme']] ), 'invalid_endpoint'];
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
            // The timeout plus 3 seconds for connecting, 5 for resolving the host name and 5 for recording the result
            config( ['queue.connections.database.retry_after' => 24] );
            $this->assertSame( [], app( WebhookConfig::class )->problems() );

            config( ['cms.webhooks.timeout' => 11] );
            $this->assertSame(
                ['queue.connections.database.retry_after' => 'invalid_retry_after'],
                app( WebhookConfig::class )->problems()
            );
        } finally {
            config( ['cms.webhooks.timeout' => 10, 'queue.connections.database.retry_after' => $retryAfter] );
        }
    }


    public function testProblemsReportNullCache() : void
    {
        config( ['cache.default' => 'null', 'cache.stores.null' => ['driver' => 'null']] );

        $this->assertSame( ['cache.default' => 'invalid_cache'], app( WebhookConfig::class )->problems() );
    }


    public function testSubscribedMatchesEvents() : void
    {
        config( ['cms.webhooks.endpoints' => [
            'indexer' => self::endpoint( [] ),
            'search' => self::endpoint( ['url' => 'https://search.internal/hook', 'events' => ['page.published', 'file.purged']] ),
        ]] );

        $config = app( WebhookConfig::class );

        $this->assertSame( ['indexer', 'search'], $config->subscribed( 'page.published' ) );
        $this->assertSame( ['indexer'], $config->subscribed( 'page.deleted' ) );
        $this->assertSame( ['search'], $config->subscribed( 'file.purged' ) );
        $this->assertSame( [], $config->subscribed( 'page.moved' ) );
    }


    public function testSubscribedSkipsAndLogsInvalidEndpointsOnce() : void
    {
        config( ['cms.webhooks.endpoints' => [
            'indexer' => self::endpoint( [] ),
            'broken' => self::endpoint( ['secret' => 'short'] ),
        ]] );
        Log::spy();

        $config = app( WebhookConfig::class );

        $this->assertSame( ['indexer'], $config->subscribed( 'page.published' ) );
        $this->assertSame( ['indexer'], $config->subscribed( 'page.deleted' ) );

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
        config( ['cms.webhooks.endpoints' => ['indexer' => self::endpoint( [] )]] );
        $config = app( WebhookConfig::class );

        $this->assertSame( [
            'url' => 'http://indexer:8080/hook',
            'secrets' => [self::secret( 's' )],
            'internal' => true,
        ], $config->endpoint( 'indexer', 'page.published' ) );

        $this->assertNull( $config->endpoint( 'indexer', 'file.purged' ) );
        $this->assertNull( $config->endpoint( 'search', 'page.published' ) );
    }


    public function testEndpointReturnsSecretListWithoutEmptyEntries() : void
    {
        $secrets = [self::secret( 't' ), '', null, self::secret( 's' )];
        config( ['cms.webhooks.endpoints' => ['indexer' => self::endpoint( ['secret' => $secrets] )]] );

        $this->assertSame(
            [self::secret( 't' ), self::secret( 's' )],
            app( WebhookConfig::class )->endpoint( 'indexer', 'page.published' )['secrets'] ?? null
        );
    }


    public function testEndpointThrowsIfInvalid() : void
    {
        config( ['cms.webhooks.endpoints' => ['indexer' => self::endpoint( ['events' => ['page.saved']] )]] );

        $this->expectException( WebhookException::class );
        app( WebhookConfig::class )->endpoint( 'indexer', 'page.published' );
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
