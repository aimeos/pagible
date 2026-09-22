<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Tenancy;
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
                ->expectsOutputToContain( 'queue.connections.database.retry_after: The value must be greater than the "cms.webhooks.timeout" setting plus 13 seconds' )
                ->expectsOutputToContain( 'cache.default: The cache store must be shared by all servers and queue workers' )
                ->assertFailed();
        } finally {
            config( ['queue.connections.database.retry_after' => $retryAfter] );
        }
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


    public function testWarnsAboutUndecryptableSubscriptionsWithoutFailing() : void
    {
        $this->undecryptable( $this->webhook() );
        $this->webhook( ['url' => 'https://example.com/hooks/other'] );
        Tenancy::run( 'other', fn() => $this->undecryptable( $this->webhook() ) );

        // Only the tenants can rotate their secrets, so the deploy isn't stopped
        $this->artisan( 'cms:webhooks:check' )
            ->expectsOutputToContain( 'app.key: 2 webhook subscription(s) can\'t be decrypted' )
            ->expectsOutputToContain( 'The webhook configuration is valid' )
            ->assertSuccessful();
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
