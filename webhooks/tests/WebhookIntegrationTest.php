<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Jobs\DeliverWebhook;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\Webhook;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;


class WebhookIntegrationTest extends WebhookTestAbstract
{
    use RefreshDatabase;


    public function testModelPublicationDispatchesDelivery() : void
    {
        $webhook = $this->webhook();
        $page = Page::forceCreate( [
            'name' => 'Integration',
            'title' => 'Integration',
            'path' => 'integration',
            'tag' => 'integration',
            'to' => '',
            'domain' => 'example.com',
            'lang' => 'en',
            'type' => '',
            'theme' => '',
            'cache' => 0,
            'status' => 1,
            'editor' => 'editor@testbench',
            'meta' => [],
            'config' => [],
            'content' => [],
        ] );
        $version = $page->versions()->forceCreate( [
            'data' => [
                'name' => 'Integration', 'title' => 'Integration', 'path' => 'integration',
                'tag' => 'integration', 'to' => '', 'domain' => 'example.com', 'lang' => 'en',
                'type' => '', 'theme' => '', 'cache' => 0, 'status' => 1,
            ],
            'aux' => ['content' => [], 'meta' => [], 'config' => []],
            'editor' => 'editor@testbench',
        ] );
        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        Queue::fake();

        $page->publish( $version );

        Queue::assertPushed( DeliverWebhook::class, function( DeliverWebhook $job ) use ( $page, $version, $webhook ) {
            $payload = json_decode( $job->body, true, flags: JSON_THROW_ON_ERROR );

            return $job->webhookId === $webhook->id
                && $job->event === 'page.published'
                && $payload['data'] === [
                    'id' => $page->id,
                    'version_id' => $version->id,
                    'path' => 'integration',
                    'domain' => 'example.com',
                ];
        } );
    }


    public function testNoSubscriptionsDispatchesNothing() : void
    {
        $page = Page::forceCreate( [
            'name' => 'No hook', 'title' => 'No hook', 'path' => 'no-hook', 'tag' => 'no-hook',
            'to' => '', 'domain' => '', 'lang' => 'en', 'type' => '', 'theme' => '',
            'cache' => 0, 'status' => 1, 'editor' => '', 'meta' => [], 'config' => [], 'content' => [],
        ] );
        Queue::fake();

        event( new \Aimeos\Cms\Events\Published(
            'page', $page->id, 'version', '', [], true, tenant: 'test',
        ) );

        Queue::assertNothingPushed();
    }


    public function testSingleFilePurgeDispatchesDelivery() : void
    {
        $webhook = $this->webhook( ['events' => ['file.purged']] );
        $file = File::forceCreate( [
            'lang' => 'en', 'mime' => 'text/plain', 'name' => 'purged.txt',
            'path' => '', 'editor' => 'editor@testbench',
        ] );
        $version = $file->versions()->forceCreate( [
            'lang' => 'en', 'editor' => 'editor@testbench',
            'data' => ['path' => '', 'previews' => []],
        ] );
        $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        Queue::fake();

        Resource::purge( File::class, [$file->id], $this->user );

        Queue::assertPushed( DeliverWebhook::class, function( DeliverWebhook $job ) use ( $file, $version, $webhook ) {
            $payload = json_decode( $job->body, true, flags: JSON_THROW_ON_ERROR );

            return $job->webhookId === $webhook->id
                && $job->event === 'file.purged'
                && $payload['data'] === [
                    'id' => $file->id,
                    'version_id' => $version->id,
                ];
        } );
    }


    public function testScheduledPublicationUsesProjectedVersionRoute() : void
    {
        $webhook = $this->webhook();
        $page = Page::forceCreate( [
            'name' => 'Before', 'title' => 'Before', 'path' => 'before', 'tag' => 'before',
            'to' => '', 'domain' => 'before.example', 'lang' => 'en', 'type' => '', 'theme' => '',
            'cache' => 0, 'status' => 1, 'editor' => '', 'meta' => [], 'config' => [], 'content' => [],
        ] );
        $published = $page->versions()->forceCreate( [
            'data' => [
                'name' => 'Published', 'title' => 'Published', 'path' => 'published-route',
                'tag' => 'published', 'to' => '', 'domain' => 'published.example', 'lang' => 'en',
                'type' => '', 'theme' => '', 'cache' => 0, 'status' => 1,
            ],
            'aux' => ['content' => [], 'meta' => [], 'config' => []],
            'publish_at' => now()->subMinute(),
            'editor' => 'scheduler@testbench',
        ] );
        $future = $page->versions()->forceCreate( [
            'data' => [
                'name' => 'Future', 'title' => 'Future', 'path' => 'future-route',
                'tag' => 'future', 'to' => '', 'domain' => 'future.example', 'lang' => 'en',
                'type' => '', 'theme' => '', 'cache' => 0, 'status' => 1,
            ],
            'aux' => ['content' => [], 'meta' => [], 'config' => []],
            'publish_at' => now()->addDay(),
            'editor' => 'scheduler@testbench',
        ] );
        $page->forceFill( ['latest_id' => $future->id] )->saveQuietly();
        Queue::fake();

        $this->artisan( 'cms:publish' )->assertSuccessful();

        Queue::assertPushed( DeliverWebhook::class, function( DeliverWebhook $job ) use ( $page, $published, $webhook ) {
            $payload = json_decode( $job->body, true, flags: JSON_THROW_ON_ERROR );

            return $job->webhookId === $webhook->id
                && $payload['data'] === [
                    'id' => $page->id,
                    'version_id' => $published->id,
                    'path' => 'published-route',
                    'domain' => 'published.example',
                ];
        } );
    }


    public function testMaintenanceCommandsReencryptAndPurgeTenant() : void
    {
        $webhook = $this->webhook();
        $before = $webhook->getRawOriginal( 'secrets' );
        $revision = $webhook->revision;

        $this->artisan( 'cms:webhooks:reencrypt', ['--tenant' => 'test'] )
            ->expectsOutput( 'Re-encrypted 1 webhook subscription(s).' )
            ->assertSuccessful();

        $webhook->refresh();
        $this->assertNotSame( $before, $webhook->getRawOriginal( 'secrets' ) );
        $this->assertSame( [self::secret( 'test' )], $webhook->secrets() );
        $this->assertSame( $revision, $webhook->revision );

        Log::spy();

        $this->artisan( 'cms:webhooks:purge', ['tenant' => 'test'] )
            ->expectsOutput( 'Deleted 1 webhook subscription(s) for tenant test.' )
            ->assertSuccessful();
        $this->assertDatabaseCount( 'cms_webhooks', 0, 'testing' );

        Log::shouldHaveReceived( 'warning' )->once()->with( 'cms.webhook', \Mockery::on( fn( array $data ) =>
            $data['action'] === 'purged' && $data['actor'] === 'cli'
                && $data['tenant_id'] === 'test' && $data['webhook_count'] === 1
        ) );

        // Nothing is logged if there's nothing to delete
        $this->artisan( 'cms:webhooks:purge', ['tenant' => 'test'] )
            ->expectsOutput( 'Deleted 0 webhook subscription(s) for tenant test.' )
            ->assertSuccessful();

        Log::shouldHaveReceived( 'warning' )->once();
    }


    public function testPurgeReportsBusyConfiguration() : void
    {
        $this->webhook();
        $lock = \Illuminate\Support\Facades\Cache::lock( 'cms_webhooks_' . hash( 'sha256', 'test' ), 10 );
        $default = config( 'cms.lock' );
        $this->assertTrue( $lock->get() );
        config( ['cms.lock' => 1] );

        try {
            $this->artisan( 'cms:webhooks:purge', ['tenant' => 'test'] )
                ->expectsOutput( 'Webhook configuration is busy; retry purging.' )
                ->assertFailed();
        } finally {
            config( ['cms.lock' => $default] );
            $lock->release();
        }

        $this->assertDatabaseCount( 'cms_webhooks', 1, 'testing' );
    }


    public function testCommandsRejectInvalidTenants() : void
    {
        $this->webhook();

        $this->artisan( 'cms:webhooks:purge', ['tenant' => 'in valid'] )
            ->expectsOutput( 'Invalid tenant ID' )
            ->assertFailed();
        $this->artisan( 'cms:webhooks:purge', ['tenant' => ''] )
            ->expectsOutput( 'The tenant argument must not be empty.' )
            ->assertFailed();
        $this->artisan( 'cms:webhooks:reencrypt', ['--tenant' => 'in valid'] )
            ->expectsOutput( 'Invalid tenant ID' )
            ->assertFailed();

        // Unset variables in deploy scripts must not silently re-encrypt all tenants
        $this->artisan( 'cms:webhooks:reencrypt', ['--tenant' => ''] )
            ->expectsOutput( 'The tenant option must not be empty.' )
            ->assertFailed();

        $this->assertDatabaseCount( 'cms_webhooks', 1, 'testing' );
    }


    public function testReencryptSkipsUndecryptableSubscriptions() : void
    {
        $broken = $this->undecryptable( $this->webhook( ['secrets' => self::secrets( 'test', ['old' => now()->addHour()] )] ) );
        $webhook = $this->webhook( ['url' => 'https://example.com/hooks/other'] );
        $before = $webhook->getRawOriginal( 'secrets' );
        Tenancy::run( 'other', fn() => $this->undecryptable( $this->webhook() ) );

        // The other subscriptions are still re-encrypted and operators know which tenants to notify
        $this->artisan( 'cms:webhooks:reencrypt' )
            ->expectsOutput( 'Re-encrypted 1 webhook subscription(s).' )
            ->expectsOutput( '2 webhook subscription(s) can\'t be decrypted; add their key to APP_PREVIOUS_KEYS and retry, or replace their URLs.' )
            ->expectsOutput( 'Affected tenants: other (1), test (1)' )
            ->assertFailed();

        $this->assertNotSame( $before, $webhook->refresh()->getRawOriginal( 'secrets' ) );
        $this->assertSame( [self::secret( 'test' )], $webhook->secrets() );
        $this->assertFalse( $broken->refresh()->decryptable() );
    }


    public function testReencryptSkipsBusyTenants() : void
    {
        $this->webhook();
        Tenancy::run( 'other', fn() => $this->webhook() );

        $lock = \Illuminate\Support\Facades\Cache::lock( 'cms_webhooks_' . hash( 'sha256', 'test' ), 10 );
        $default = config( 'cms.lock' );
        $this->assertTrue( $lock->get() );
        config( ['cms.lock' => 1] );

        try {
            // A tenant whose subscriptions are changed at the same time doesn't stop the other tenants
            $this->artisan( 'cms:webhooks:reencrypt' )
                ->expectsOutput( 'Re-encrypted 1 webhook subscription(s).' )
                ->expectsOutput( '1 webhook subscription(s) were changed at the same time and weren\'t re-encrypted; retry re-encryption.' )
                ->expectsOutput( 'Affected tenants: test (1)' )
                ->assertFailed();
        } finally {
            config( ['cms.lock' => $default] );
            $lock->release();
        }
    }


    public function testReencryptKeepsLastError() : void
    {
        $error = ['reason' => 'timeout', 'at' => '2026-09-15T12:00:00+00:00'];
        $webhook = $this->webhook( ['last_error' => $error] );

        $this->artisan( 'cms:webhooks:reencrypt' )
            ->expectsOutput( 'Re-encrypted 1 webhook subscription(s).' )
            ->assertSuccessful();

        $this->assertSame( $error, $webhook->refresh()->last_error );
    }


    public function testReencryptReportsUnexpectedErrors() : void
    {
        $this->webhook();
        \Illuminate\Support\Facades\Exceptions::fake();

        $this->mock( \Aimeos\Cms\WebhookManager::class, fn( $mock ) => $mock
            ->shouldReceive( 'reencrypt' )->andThrow( new \RuntimeException( 'Database unavailable' ) )
        );

        $this->artisan( 'cms:webhooks:reencrypt' )
            ->expectsOutput( 'Re-encryption failed: Database unavailable' )
            ->assertFailed();

        \Illuminate\Support\Facades\Exceptions::assertReported( \RuntimeException::class );
    }


    public function testReencryptRemovesExpiredPreviousSecret() : void
    {
        $expired = $this->webhook( ['secrets' => self::secrets( 'test', ['old' => now()->subSecond()] )] );
        $current = $this->webhook( [
            'url' => 'https://example.com/hooks/current',
            'secrets' => $secrets = self::secrets( 'test', ['old' => now()->addHour()] ),
        ] );

        $this->artisan( 'cms:webhooks:reencrypt', ['--tenant' => 'test'] )
            ->expectsOutput( 'Re-encrypted 2 webhook subscription(s).' )
            ->assertSuccessful();

        $expired->refresh();
        $current->refresh();

        $this->assertSame( self::secrets(), $expired->secrets );
        $this->assertSame( $secrets, $current->secrets );
    }
}
