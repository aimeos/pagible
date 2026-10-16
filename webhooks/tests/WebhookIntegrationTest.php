<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Jobs\DeliverWebhook;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Models\Webhook;
use Aimeos\Cms\Facades\Files;
use Aimeos\Cms\Facades\Pages;
use Aimeos\Cms\Facades\Variants;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;


class WebhookIntegrationTest extends WebhookTestAbstract
{
    use RefreshDatabase;


    public function testModelPublicationDispatchesDelivery() : void
    {
        $webhook = $this->webhook();
        $node = new Page( ['source' => 'en'] );
        $node->save();

        $page = $node->variants()->create( [
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
        ] )->view;
        $version = $page->versions()->forceCreate( [
            'data' => [
                'name' => 'Integration', 'title' => 'Integration', 'path' => 'integration',
                'tag' => 'integration', 'to' => '', 'domain' => 'example.com', 'lang' => 'en',
                'type' => '', 'theme' => '', 'cache' => 0, 'status' => 1,
            ],
            'aux' => ['content' => [], 'meta' => [], 'config' => []],
            'editor' => 'editor@testbench',
        ] );
        $page->variant->update( ['latest_id' => $version->id] );
        $page->refresh();
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
                    'lang' => 'en',
                ];
        } );
    }


    public function testVariantPublicationUsesVariantRoute() : void
    {
        $webhook = $this->webhook();
        $page = Pages::add( [
            'name' => 'Variant', 'title' => 'Variant', 'path' => 'variant',
            'domain' => 'example.com', 'lang' => 'en', 'status' => 1,
        ], $this->user );

        Variants::add( $page->id, 'de', $this->user );
        Variants::save( $page->id, 'de', ['path' => 'variante', 'status' => 1], $this->user );
        Queue::fake();

        Variants::publishMany( [$page->id], 'de', $this->user );

        Queue::assertPushed( DeliverWebhook::class, function( DeliverWebhook $job ) use ( $page, $webhook ) {
            $payload = json_decode( $job->body, true, flags: JSON_THROW_ON_ERROR );

            return $job->webhookId === $webhook->id
                && $job->event === 'page.published'
                && $payload['data']['id'] === $page->id
                && $payload['data']['path'] === 'variante'
                && $payload['data']['domain'] === 'example.com'
                && $payload['data']['lang'] === 'de';
        } );
    }


    public function testNoSubscriptionsDispatchesNothing() : void
    {
        $node = new Page( ['source' => 'en'] );
        $node->save();

        $page = $node->variants()->create( [
            'name' => 'No hook', 'title' => 'No hook', 'path' => 'no-hook', 'tag' => 'no-hook',
            'to' => '', 'domain' => '', 'lang' => 'en', 'type' => '', 'theme' => '',
            'cache' => 0, 'status' => 1, 'editor' => '', 'meta' => [], 'config' => [], 'content' => [],
        ] )->view;
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

        Files::purgeMany( [$file->id], $this->user );

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
        $node = new Page( ['source' => 'en'] );
        $node->save();

        $page = $node->variants()->create( [
            'name' => 'Before', 'title' => 'Before', 'path' => 'before', 'tag' => 'before',
            'to' => '', 'domain' => 'before.example', 'lang' => 'en', 'type' => '', 'theme' => '',
            'cache' => 0, 'status' => 1, 'editor' => '', 'meta' => [], 'config' => [], 'content' => [],
        ] )->view;
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
        $page->variant->update( ['latest_id' => $future->id] );
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
                    'lang' => 'en',
                ];
        } );
    }
}
