<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Commands\HandlesTenants;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\WebhookClient;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;


class CheckWebhooksTest extends WebhookTestAbstract
{
    use RefreshDatabase;


    protected function setUp() : void
    {
        parent::setUp();
        // A shared cache store, the "array" store is reported as problem
        config( ['cache.default' => 'file'] );
    }


    protected function tearDown() : void
    {
        config( [
            'cache.default' => 'array',
            'cms.webhooks.deny_cidrs' => [],
            'cms.webhooks.endpoints' => [],
        ] );
        parent::tearDown();
    }


    public function testValidConfiguration() : void
    {
        config( [
            'cms.webhooks.deny_cidrs' => ['10.0.0.0/8'],
            'cms.webhooks.endpoints' => ['indexer' => [
                'url' => 'http://indexer:8080/hook',
                'secret' => self::secret( 's' ),
                'events' => ['page.published'],
            ]],
        ] );

        $this->artisan( 'cms:webhooks:check' )
            ->expectsOutput( 'The webhook configuration is valid' )
            ->assertSuccessful();
    }


    public function testReportsEveryProblem() : void
    {
        $default = config( 'queue.default' );
        config( [
            'cms.webhooks.deny_cidrs' => ['not-a-cidr'],
            'cms.webhooks.endpoints' => [
                'indexer' => [
                    'url' => 'http://indexer:8080/hook',
                    'secret' => 'short',
                    'events' => ['page.published'],
                ],
                'search' => [
                    'url' => 'https://search.internal/hook',
                    'secret' => self::secret( 's' ),
                    'events' => ['page.saved'],
                ],
            ],
            'queue.default' => 'null',
            'queue.connections.null' => ['driver' => 'null'],
        ] );

        try {
            $this->artisan( 'cms:webhooks:check' )
                ->expectsOutputToContain( 'cms.webhooks.deny_cidrs: The "deny_cidrs" setting contains an invalid IP address or range' )
                ->expectsOutputToContain( 'cms.webhooks.endpoints.indexer: The secret must be a string or a list of strings, each "whsec_" followed by at least 24 base64 encoded bytes' )
                ->expectsOutputToContain( 'cms.webhooks.endpoints.search: The events must be a non-empty list' )
                ->expectsOutputToContain( 'cms.webhooks.queue.connection: The queue connection doesn\'t exist' )
                ->doesntExpectOutputToContain( 'short' )
                ->assertFailed();
        } finally {
            config( ['queue.default' => $default] );
        }
    }


    public function testReportsUnsuitableQueueTimesAndCache() : void
    {
        $retryAfter = config( 'queue.connections.database.retry_after' );
        config( [
            'cache.default' => 'array',
            'queue.connections.database.retry_after' => 23,
        ] );

        try {
            $this->artisan( 'cms:webhooks:check' )
                ->expectsOutputToContain( 'queue.connections.database.retry_after: The value must be greater than the sum of the "cms.webhooks.http.connect_timeout" and "cms.webhooks.http.timeout" settings' )
                ->expectsOutputToContain( 'cache.default: The cache store must be shared by all servers and queue workers' )
                ->assertFailed();
        } finally {
            config( ['queue.connections.database.retry_after' => $retryAfter] );
        }
    }


    public function testReportsMissingQueueEncryption() : void
    {
        $this->app->instance( Encrypter::class, null );

        $this->artisan( 'cms:webhooks:check' )
            ->expectsOutputToContain( 'app.key: Queued deliveries can\'t be encrypted' )
            ->assertFailed();
    }


    public function testReportsMissingApplicationKeyWithStoredSubscriptions() : void
    {
        $this->webhook();
        $key = config( 'app.key' );
        config( ['app.key' => ''] );
        $this->app->forgetInstance( 'encrypter' );
        Crypt::clearResolvedInstance( 'encrypter' );

        try {
            // Stored subscriptions can't be checked without a key, which is reported instead of failing
            $this->artisan( 'cms:webhooks:check' )
                ->expectsOutputToContain( 'app.key: Queued deliveries can\'t be encrypted' )
                ->doesntExpectOutputToContain( 'can\'t be decrypted' )
                ->assertFailed();
        } finally {
            config( ['app.key' => $key] );
            $this->app->forgetInstance( 'encrypter' );
            Crypt::clearResolvedInstance( 'encrypter' );
        }
    }


    public function testResolvesEndpointHostsOnRequest() : void
    {
        $client = new StubWebhookClient();
        $client->addresses = ['169.254.169.254'];
        $this->app->instance( WebhookClient::class, $client );
        config( ['cms.webhooks.endpoints' => ['indexer' => [
            'url' => 'http://indexer:8080/hook',
            'secret' => self::secret( 's' ),
            'events' => ['page.published'],
        ]]] );

        $this->artisan( 'cms:webhooks:check' )->assertSuccessful();
        $this->artisan( 'cms:webhooks:check --resolve' )
            ->expectsOutputToContain( 'cms.webhooks.endpoints.indexer: The URL points to a denied address' )
            ->assertFailed();
    }


    public function testWarnsAboutStalledQueueWithoutFailing() : void
    {
        \Illuminate\Support\Carbon::setTestNow( now()->subMinutes( 11 ) );

        try {
            app( \Aimeos\Cms\WebhookConfig::class )->queued();
        } finally {
            \Illuminate\Support\Carbon::setTestNow();
        }

        try {
            // The deploy which starts the queue workers mustn't be stopped by the stalled queue
            $this->artisan( 'cms:webhooks:check' )
                ->expectsOutputToContain( 'cms.webhooks.queue.name: No queued delivery was processed for more than 10 minutes' )
                ->expectsOutputToContain( 'The webhook configuration is valid' )
                ->assertSuccessful();
        } finally {
            app( \Aimeos\Cms\WebhookConfig::class )->processed();
        }
    }


    public function testWarnsAboutUndecryptableSubscriptionsWithoutFailing() : void
    {
        $this->undecryptable( $this->webhook() );
        $this->webhook( ['url' => 'https://example.com/hooks/other'] );
        Tenancy::run( 'other', fn() => $this->undecryptable( $this->webhook() ) );

        // Only the tenants can replace them, so the deploy isn't stopped and operators know whom to notify
        $this->artisan( 'cms:webhooks:check' )
            ->expectsOutputToContain( 'app.key: 2 webhook subscription(s) can\'t be decrypted' )
            ->expectsOutput( 'Affected tenants: other (1), test (1)' )
            ->expectsOutputToContain( 'The webhook configuration is valid' )
            ->assertSuccessful();
    }


    public function testListsTenantsWithMostSubscriptionsFirst() : void
    {
        $list = new class extends \Illuminate\Console\Command {
            use HandlesTenants;

            /**
             * @param array<array-key, int> $counts
             */
            public function list( array $counts ) : ?string
            {
                return $this->tenants( $counts );
            }
        };

        // Single-tenant setups have nobody else to notify
        $this->assertNull( $list->list( ['' => 3] ) );
        $this->assertSame( 'test (2), [default] (1), 123 (1), other (1)', $list->list( ['other' => 1, '' => 1, '123' => 1, 'test' => 2] ) );

        $counts = [];

        for( $i = 1; $i <= 22; $i++ ) {
            $counts[sprintf( 'tenant-%02d', $i )] = 1;
        }

        // Long lists are cut off to keep the output readable
        $this->assertStringEndsWith( 'tenant-20 (1) and 2 more', (string) $list->list( $counts ) );
        $this->assertStringNotContainsString( 'tenant-21', (string) $list->list( $counts ) );
    }


    public function testWarnsIfWebhooksAreDisabled() : void
    {
        config( ['cms.webhooks.enabled' => false] );

        try {
            $this->artisan( 'cms:webhooks:check' )
                ->expectsOutput( 'The webhook configuration is valid but webhooks are disabled' )
                ->assertSuccessful();
        } finally {
            config( ['cms.webhooks.enabled' => true] );
        }
    }
}
