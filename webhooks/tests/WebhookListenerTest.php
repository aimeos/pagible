<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Events\Bulk;
use Aimeos\Cms\Events\Dropped;
use Aimeos\Cms\Events\Moved;
use Aimeos\Cms\Events\Published;
use Aimeos\Cms\Events\Purged;
use Aimeos\Cms\Events\Restored;
use Aimeos\Cms\Jobs\DeliverWebhook;
use Aimeos\Cms\Models\Base;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;


class WebhookListenerTest extends WebhookTestAbstract
{
    use RefreshDatabase;


    public function testMatchingEventDispatchesEncryptedTenantBoundJob() : void
    {
        $webhook = $this->webhook();
        $id = '01995d6a-cb84-7218-9bb9-79063c4bf681';
        Queue::fake();

        event( new Published(
            'page', $id, 'version-1', 'editor@testbench', [
                'path' => 'webhook-page', 'domain' => 'example.com',
            ], true,
            null, null, null, 'test', 'graphql',
        ) );

        Queue::assertPushed( DeliverWebhook::class, function( DeliverWebhook $job ) use ( $id, $webhook ) {
            $payload = json_decode( $job->body, true, flags: JSON_THROW_ON_ERROR );

            return $job instanceof \Illuminate\Contracts\Queue\ShouldBeEncrypted
                && $job->webhookId === $webhook->id
                && $job->tenant === 'test'
                && $job->revision === 1
                && $payload['event'] === 'page.published'
                && $payload['tenant_id'] === 'test'
                && $payload['data'] === [
                    'id' => $id,
                    'version_id' => 'version-1',
                    'path' => 'webhook-page',
                    'domain' => 'example.com',
                ]
                && !isset( $payload['editor'] )
                && !str_contains( serialize( $job ), 'test-secret' )
                && !str_contains( serialize( $job ), 'example.com/hooks' );
        } );
    }


    public function testPublishedEventUsesProjectedVersionWhenLatestDraftIsNewer() : void
    {
        $this->webhook();
        Queue::fake();

        event( new Published(
            'page', 'page-id', 'future-version', '', [
                'path' => 'future-route', 'domain' => 'future.example',
            ], false,
            tenant: 'test', projection: [
                'version_id' => 'published-version',
                'path' => 'published-route',
                'domain' => 'published.example',
            ],
        ) );

        Queue::assertPushed( DeliverWebhook::class, function( DeliverWebhook $job ) {
            $payload = json_decode( $job->body, true, flags: JSON_THROW_ON_ERROR );

            return $job->event === 'page.published'
                && $payload['data'] === [
                    'id' => 'page-id',
                    'version_id' => 'published-version',
                    'path' => 'published-route',
                    'domain' => 'published.example',
                ];
        } );
    }


    public function testInactiveNonmatchingScheduledAndOtherTenantEventsAreSkipped() : void
    {
        $this->webhook( ['events' => ['file.deleted']] );
        $this->webhook( ['status' => false] );
        Queue::fake();

        event( new Published( 'page', 'page-id', 'v', '', [], true, tenant: 'test' ) );
        event( new Published( 'page', 'page-id', 'v', '', [], false, tenant: 'test' ) );
        event( new Published( 'page', 'page-id', 'v', '', [], true, tenant: 'other' ) );

        Queue::assertNothingPushed();
    }


    public function testBulkEventContainsOnlyOrderedReferences() : void
    {
        $this->webhook();
        Queue::fake();

        event( new Bulk(
            'page', ['page-2', 'page-1'], ['page-1' => 'version-1', 'page-2' => 'version-2'],
            ['published' => true], tenant: 'test', action: 'published',
            projected: ['page-2' => 'published-version-2'],
        ) );

        Queue::assertPushed( DeliverWebhook::class, function( DeliverWebhook $job ) {
            $payload = json_decode( $job->body, true, flags: JSON_THROW_ON_ERROR );

            return $payload['data'] === [
                ['id' => 'page-2', 'version_id' => 'published-version-2'],
                ['id' => 'page-1', 'version_id' => 'version-1'],
            ];
        } );
    }


    public function testMaximumBulkEventIsQueued() : void
    {
        $this->webhook();
        $ids = [];
        $latest = [];

        for( $i = 0; $i < Base::MAX_BULK; $i++ ) {
            $id = sprintf( '00000000-0000-4000-8000-%012d', $i );
            $ids[] = $id;
            $latest[$id] = sprintf( '00000000-0000-4000-8001-%012d', $i );
        }

        Queue::fake();
        event( new Bulk(
            'page', $ids, $latest, ['published' => true], tenant: 'test', action: 'published',
        ) );

        Queue::assertPushed( DeliverWebhook::class, function( DeliverWebhook $job ) {
            $payload = json_decode( $job->body, true, flags: JSON_THROW_ON_ERROR );

            return count( $payload['data'] ?? [] ) === Base::MAX_BULK;
        } );
    }


    public function testDroppedMapsToDeleted() : void
    {
        $webhook = $this->webhook( ['events' => ['page.deleted']] );
        Queue::fake();

        event( new Dropped(
            'page', 'page-id', 'version-id', '', ['path' => 'old-path', 'domain' => 'example.com'],
            tenant: 'test',
        ) );

        Queue::assertPushed( DeliverWebhook::class, function( DeliverWebhook $job ) use ( $webhook ) {
            $payload = json_decode( $job->body, true, flags: JSON_THROW_ON_ERROR );

            return $job->webhookId === $webhook->id
                && $job->event === 'page.deleted'
                && $payload['data'] === [
                    'id' => 'page-id',
                    'version_id' => 'version-id',
                    'path' => 'old-path',
                    'domain' => 'example.com',
                ];
        } );
    }


    public function testLifecycleNamesAreDerivedAndAllowlisted() : void
    {
        $this->webhook( ['events' => [
            'page.moved', 'element.restored', 'file.purged', 'file.deleted',
        ]] );
        Queue::fake();

        event( new Moved( 'page', 'page-id', 'version-id', '', [], tenant: 'test' ) );
        event( new Restored( 'element', 'element-id', 'version-id', '', [], tenant: 'test' ) );
        event( new Purged( 'file', 'file-id', 'version-id', '', [], tenant: 'test' ) );
        event( new Bulk(
            'file', ['file-id'], ['file-id' => 'version-id'], [], tenant: 'test', action: 'dropped',
        ) );

        foreach( ['page.moved', 'element.restored', 'file.purged', 'file.deleted'] as $name ) {
            Queue::assertPushed( DeliverWebhook::class, fn( DeliverWebhook $job ) => $job->event === $name );
        }
    }
}
