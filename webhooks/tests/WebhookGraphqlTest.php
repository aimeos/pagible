<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Webhook;
use Aimeos\Cms\WebhookCircuit;
use Aimeos\Cms\WebhookClient;
use Aimeos\Cms\WebhookException;
use Aimeos\Cms\WebhookManager;
use Aimeos\Cms\WebhookResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;


class WebhookGraphqlTest extends WebhookTestAbstract
{
    use RefreshDatabase;


    public function testProvisionSaveQueryReplaceRotateAndDrop() : void
    {
        $response = $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
            mutation {
              addWebhook(input: {url: "https://example.com/hook", events: ["page.published"]}) {
                secret
                webhook { id endpoint status events }
              }
            }
        ' );
        $response->assertGraphQLErrorFree();
        $id = $response->json( 'data.addWebhook.webhook.id' );
        $secret = $response->json( 'data.addWebhook.secret' );

        $this->assertIsString( $secret );
        $this->assertGreaterThanOrEqual( 43, strlen( $secret ) );
        $this->assertFalse( $response->json( 'data.addWebhook.webhook.status' ) );

        $response = $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
            mutation ($id: ID!) {
              saveWebhook(id: $id, input: {events: ["page.deleted", "page.published"], status: true}) {
                id status events endpoint
              }
            }
        ', ['id' => $id] );
        $response->assertGraphQLErrorFree();
        $this->assertTrue( $response->json( 'data.saveWebhook.status' ) );

        $query = $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
            query {
              cmsWebhooks { id endpoint status events last_error { reason } last_success_at }
              cmsWebhookEvents
            }
        ' );
        $query->assertGraphQLErrorFree();
        $this->assertCount( 1, $query->json( 'data.cmsWebhooks' ) );
        $this->assertContains( 'file.purged', $query->json( 'data.cmsWebhookEvents' ) );

        $replacement = $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
            mutation ($id: ID!) {
              replaceWebhook(id: $id, url: "https://replacement.example/hook") {
                secret webhook { id endpoint status }
              }
            }
        ', ['id' => $id] );
        $replacement->assertGraphQLErrorFree();
        $this->assertFalse( $replacement->json( 'data.replaceWebhook.webhook.status' ) );
        $this->assertNotSame( $secret, $replacement->json( 'data.replaceWebhook.secret' ) );

        $rotated = $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
            mutation ($id: ID!) {
              rotateWebhook(id: $id) { secret webhook { id status } }
            }
        ', ['id' => $id] );
        $rotated->assertGraphQLErrorFree();
        $this->assertFalse( $rotated->json( 'data.rotateWebhook.webhook.status' ) );
        $this->assertNotSame( $replacement->json( 'data.replaceWebhook.secret' ), $rotated->json( 'data.rotateWebhook.secret' ) );

        $second = $this->webhook( ['url' => 'https://second.example/hook'] );
        $foreign = \Aimeos\Cms\Tenancy::run( 'other', fn() =>
            $this->webhook( ['url' => 'https://other.example/hook'] )
        );
        $dropped = $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
            mutation ($id: [ID!]!) { dropWebhook(id: $id) }
        ', ['id' => [$id, $second->id, $foreign->id]] );
        $dropped->assertGraphQLErrorFree();
        $this->assertSame( 2, $dropped->json( 'data.dropWebhook' ) );
        $this->assertSame( 1, Webhook::withoutTenancy()->count() );
        $this->assertTrue( Webhook::withoutTenancy()->whereKey( $foreign->id )->exists() );
    }


    public function testRotateKeepsStateAndSignsWithPreviousSecretDuringGracePeriod() : void
    {
        $success = Carbon::parse( '2026-09-14 12:00:00 UTC' );
        $webhook = $this->webhook( [
            'last_error' => ['reason' => 'http_error', 'status' => 503],
            'last_success_at' => $success,
        ] );
        Carbon::setTestNow( '2026-09-15 12:00:00 UTC' );

        try {
            $rotated = $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
                mutation ($id: ID!) {
                  rotateWebhook(id: $id) { secret webhook { id status } }
                }
            ', ['id' => $webhook->id] );

            $rotated->assertGraphQLErrorFree();
            $secret = $rotated->json( 'data.rotateWebhook.secret' );
            $webhook->refresh();

            $this->assertTrue( $rotated->json( 'data.rotateWebhook.webhook.status' ) );
            $this->assertSame( 1, $webhook->revision );
            $this->assertSame( 503, $webhook->last_error['status'] );
            $this->assertTrue( $success->equalTo( $webhook->last_success_at ) );
            $this->assertSame( [$secret, self::secret( 'test' )], $webhook->secrets() );

            // The previous secret expires after the grace period
            Carbon::setTestNow( '2026-09-16 12:00:01 UTC' );
            $this->assertSame( [$secret], $webhook->secrets() );

            // Without a grace period, only the new secret signs deliveries
            Carbon::setTestNow( '2026-09-15 12:00:00 UTC' );
            config( ['cms.webhooks.rotation_grace' => 0] );

            try {
                $rotated = $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
                    mutation ($id: ID!) { rotateWebhook(id: $id) { secret } }
                ', ['id' => $webhook->id] );
            } finally {
                config( ['cms.webhooks.rotation_grace' => 86400] );
            }

            $rotated->assertGraphQLErrorFree();
            $this->assertSame( [$rotated->json( 'data.rotateWebhook.secret' )], $webhook->refresh()->secrets() );
            $this->assertCount( 1, $webhook->secrets );
        } finally {
            Carbon::setTestNow();
        }
    }


    public function testRotateAgainDiscardsOlderSecret() : void
    {
        $webhook = $this->webhook( ['secrets' => self::secrets( 'test', ['old' => now()->addHour()] )] );

        $rotated = $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
            mutation ($id: ID!) { rotateWebhook(id: $id) { secret } }
        ', ['id' => $webhook->id] );

        $rotated->assertGraphQLErrorFree();
        $this->assertSame( [$rotated->json( 'data.rotateWebhook.secret' ), self::secret( 'test' )], $webhook->refresh()->secrets() );
    }


    public function testReplaceDiscardsPreviousSecret() : void
    {
        $webhook = $this->webhook( ['secrets' => self::secrets( 'test', ['old' => now()->addHour()] )] );

        $replaced = $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
            mutation ($id: ID!) {
              replaceWebhook(id: $id, url: "https://replacement.example/hook") { secret webhook { status } }
            }
        ', ['id' => $webhook->id] );

        $replaced->assertGraphQLErrorFree();
        $this->assertFalse( $replaced->json( 'data.replaceWebhook.webhook.status' ) );
        $this->assertSame( [$replaced->json( 'data.replaceWebhook.secret' )], $webhook->refresh()->secrets() );
        $this->assertSame( 2, $webhook->revision );
    }


    public function testPingSendsSignedTestEventWithoutChangingHealth() : void
    {
        $webhook = $this->webhook( ['status' => false, 'last_error' => ['reason' => 'timeout']] );
        $client = new class extends WebhookClient {
            /** @var list<array{string, string, string}> */
            public array $calls = [];
            public int|WebhookException $result = 204;
            public function send( array $target, string $deliveryId, string $body, ?int $deadline = null ) : WebhookResponse
            {
                $this->calls[] = [$target['url'], $deliveryId, $body];
                return is_int( $this->result ) ? new WebhookResponse( $this->result ) : throw $this->result;
            }
        };
        $this->app->instance( WebhookClient::class, $client );
        $this->app->forgetInstance( WebhookManager::class );
        $query = /** @lang GraphQL */ '
            mutation ($id: ID!) { pingWebhook(id: $id) { success status reason } }
        ';

        Carbon::setTestNow( '2026-09-15 12:00:00.123 UTC' );

        $response = $this->actingAs( $this->user )->graphQL( $query, ['id' => $webhook->id] );
        $response->assertGraphQLErrorFree();
        $this->assertSame( ['success' => true, 'status' => 204, 'reason' => null], $response->json( 'data.pingWebhook' ) );

        [$url, $deliveryId, $body] = $client->calls[0];
        $payload = json_decode( $body, true, flags: JSON_THROW_ON_ERROR );
        $this->assertSame( $webhook->url, $url );
        $this->assertTrue( Str::isUuid( $deliveryId ) );
        $this->assertSame( ['event' => 'webhook.ping', 'tenant_id' => 'test'], array_slice( $payload, 0, 2 ) );
        $this->assertSame( '2026-09-15T12:00:00.123+00:00', $payload['timestamp'] );
        $this->assertSame( ['id' => $webhook->id], $payload['data'] );

        Carbon::setTestNow( '2026-09-15 12:00:11 UTC' );
        $client->result = 500;
        $response = $this->actingAs( $this->user )->graphQL( $query, ['id' => $webhook->id] );
        $this->assertSame( ['success' => false, 'status' => 500, 'reason' => 'http_error'], $response->json( 'data.pingWebhook' ) );

        Carbon::setTestNow( '2026-09-15 12:00:22 UTC' );
        $client->result = new WebhookException( 'transport_error' );
        $response = $this->actingAs( $this->user )->graphQL( $query, ['id' => $webhook->id] );
        $this->assertSame( ['success' => false, 'status' => null, 'reason' => 'transport_error'], $response->json( 'data.pingWebhook' ) );

        $webhook->refresh();
        $this->assertSame( ['reason' => 'timeout'], $webhook->last_error );
        $this->assertNull( $webhook->last_success_at );
    }


    public function testPingIsLimitedForEachSubscription() : void
    {
        $webhook = $this->webhook();
        $other = $this->webhook( ['url' => 'https://example.com/hooks/other'] );
        $client = new class extends WebhookClient {
            public int $calls = 0;
            public function send( array $target, string $deliveryId, string $body, ?int $deadline = null ) : WebhookResponse
            {
                $this->calls++;
                return new WebhookResponse( 204 );
            }
        };
        $this->app->instance( WebhookClient::class, $client );
        $this->app->forgetInstance( WebhookManager::class );
        $query = /** @lang GraphQL */ '
            mutation ($id: ID!) { pingWebhook(id: $id) { success } }
        ';
        Carbon::setTestNow( '2026-09-15 12:00:00 UTC' );

        $this->actingAs( $this->user )->graphQL( $query, ['id' => $webhook->id] )->assertGraphQLErrorFree();
        $this->actingAs( $this->user )->graphQL( $query, ['id' => $webhook->id] )
            ->assertGraphQLErrorMessage( 'Please wait a few seconds before sending another test event.' );

        // Other subscriptions and replaced destinations aren't affected
        $this->actingAs( $this->user )->graphQL( $query, ['id' => $other->id] )->assertGraphQLErrorFree();
        $webhook->forceFill( ['revision' => 2] )->save();
        $this->actingAs( $this->user )->graphQL( $query, ['id' => $webhook->id] )->assertGraphQLErrorFree();

        Carbon::setTestNow( '2026-09-15 12:00:11 UTC' );
        $this->actingAs( $this->user )->graphQL( $query, ['id' => $other->id] )->assertGraphQLErrorFree();

        $this->assertSame( 4, $client->calls );
    }


    public function testPingIsDeniedForOtherTenantsMissingPermissionAndDisabledWebhooks() : void
    {
        $webhook = $this->webhook();
        $foreign = \Aimeos\Cms\Tenancy::run( 'other', fn() => $this->webhook() );
        $client = new class extends WebhookClient {
            public int $calls = 0;
            public function send( array $target, string $deliveryId, string $body, ?int $deadline = null ) : WebhookResponse
            {
                $this->calls++;
                return new WebhookResponse( 204 );
            }
        };
        $this->app->instance( WebhookClient::class, $client );
        $this->app->forgetInstance( WebhookManager::class );
        $query = /** @lang GraphQL */ '
            mutation ($id: ID!) { pingWebhook(id: $id) { success } }
        ';

        $this->actingAs( new \App\Models\User( ['cmsperms' => []] ) )->graphQL( $query, ['id' => $webhook->id] )
            ->assertGraphQLErrorMessage( 'Insufficient permissions' );
        $this->actingAs( $this->user )->graphQL( $query, ['id' => $foreign->id] )
            ->assertGraphQLErrorMessage( 'Webhook not found.' );

        config( ['cms.webhooks.enabled' => false] );

        try {
            $this->actingAs( $this->user )->graphQL( $query, ['id' => $webhook->id] )
                ->assertGraphQLErrorMessage( 'Webhooks are disabled by the server configuration.' );
        } finally {
            config( ['cms.webhooks.enabled' => true] );
        }

        $this->assertSame( 0, $client->calls );
    }


    public function testPausedDestinationIsShownAndResumedBySuccessfulPing() : void
    {
        $webhook = $this->webhook();
        $client = new class extends WebhookClient {
            public function send( array $target, string $deliveryId, string $body, ?int $deadline = null ) : WebhookResponse
            {
                return new WebhookResponse( 204 );
            }
        };
        $this->app->instance( WebhookClient::class, $client );
        $this->app->forgetInstance( WebhookManager::class );
        Carbon::setTestNow( '2026-09-15 12:00:00 UTC' );

        try {
            WebhookCircuit::webhook( 'test', $webhook->id, $webhook->revision )->open( 'timeout', null, [300] );

            $response = $this->actingAs( $this->user )->graphQL( '{ cmsWebhooks { id paused_until } }' );
            $response->assertGraphQLErrorFree();
            $this->assertSame( '2026-09-15T12:05:00.000000Z', $response->json( 'data.cmsWebhooks.0.paused_until' ) );

            $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
                mutation ($id: ID!) { pingWebhook(id: $id) { success } }
            ', ['id' => $webhook->id] )->assertGraphQLErrorFree();

            $response = $this->actingAs( $this->user )->graphQL( '{ cmsWebhooks { id paused_until } }' );
            $this->assertNull( $response->json( 'data.cmsWebhooks.0.paused_until' ) );
        } finally {
            Carbon::setTestNow();
        }
    }


    public function testSavingKeepsTheRevisionUnlessDeactivated() : void
    {
        $webhook = $this->webhook();
        $mutation = /** @lang GraphQL */ '
            mutation ($id: ID!, $status: Boolean!) {
              saveWebhook(id: $id, input: {events: ["page.deleted", "page.published"], status: $status}) { paused_until }
            }
        ';
        Carbon::setTestNow( '2026-09-15 12:00:00 UTC' );

        try {
            WebhookCircuit::webhook( 'test', $webhook->id, $webhook->revision )->open( 'timeout', null, [300] );

            // Queued deliveries of the remaining events and the pause of the destination are kept
            $response = $this->actingAs( $this->user )->graphQL( $mutation, ['id' => $webhook->id, 'status' => true] );
            $response->assertGraphQLErrorFree();
            $this->assertSame( '2026-09-15T12:05:00.000000Z', $response->json( 'data.saveWebhook.paused_until' ) );
            $this->assertSame( 1, $webhook->refresh()->revision );

            // Deactivating cancels the queued deliveries so they aren't sent after activating again
            $response = $this->actingAs( $this->user )->graphQL( $mutation, ['id' => $webhook->id, 'status' => false] );
            $response->assertGraphQLErrorFree();
            $this->assertNull( $response->json( 'data.saveWebhook.paused_until' ) );
            $this->assertSame( 2, $webhook->refresh()->revision );

            $response = $this->actingAs( $this->user )->graphQL( $mutation, ['id' => $webhook->id, 'status' => true] );
            $response->assertGraphQLErrorFree();
            $this->assertSame( 2, $webhook->refresh()->revision );
        } finally {
            Carbon::setTestNow();
        }
    }


    public function testNamesAreAddedSavedAndKept() : void
    {
        $add = /** @lang GraphQL */ '
            mutation ($name: String) {
              addWebhook(input: {url: "https://example.com/hook", events: ["page.published"], name: $name}) { webhook { id name } }
            }
        ';
        $save = /** @lang GraphQL */ '
            mutation ($id: ID!, $name: String) {
              saveWebhook(id: $id, input: {events: ["page.published"], status: false, name: $name}) { name }
            }
        ';

        // Names are shown in one line
        $response = $this->actingAs( $this->user )->graphQL( $add, ['name' => " Search\nindexer "] );
        $response->assertGraphQLErrorFree();
        $id = $response->json( 'data.addWebhook.webhook.id' );
        $this->assertSame( 'Search indexer', $response->json( 'data.addWebhook.webhook.name' ) );

        // Line separators are replaced and bidi controls which display names reversed are removed
        $this->actingAs( $this->user )->graphQL( $save, ['id' => $id, 'name' => "Search\u{2028}\u{202E}indexer\u{2066}"] )
            ->assertGraphQLErrorFree()->assertJsonPath( 'data.saveWebhook.name', 'Search indexer' );

        // The name is kept if it's omitted
        $this->actingAs( $this->user )->graphQL( $save, ['id' => $id] )
            ->assertGraphQLErrorFree()->assertJsonPath( 'data.saveWebhook.name', 'Search indexer' );

        $this->actingAs( $this->user )->graphQL( $save, ['id' => $id, 'name' => 'Cache purger'] )
            ->assertGraphQLErrorFree()->assertJsonPath( 'data.saveWebhook.name', 'Cache purger' );

        $this->actingAs( $this->user )->graphQL( $save, ['id' => $id, 'name' => str_repeat( 'a', 101 )] )
            ->assertGraphQLValidationKeys( ['input.name'] );

        // Empty strings are converted to NULL by Laravel's middleware
        $this->actingAs( $this->user )->graphQL( $save, ['id' => $id, 'name' => ''] )
            ->assertGraphQLErrorFree()->assertJsonPath( 'data.saveWebhook.name', '' );

        // Subscriptions without a name
        $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
            mutation { addWebhook(input: {url: "https://example.org/hook", events: ["page.published"]}) { webhook { name } } }
        ' )->assertGraphQLErrorFree()->assertJsonPath( 'data.addWebhook.webhook.name', '' );
    }


    public function testUndecryptableSubscriptionCantBeActivated() : void
    {
        $webhook = $this->undecryptable( $this->webhook( ['status' => false] ) );
        $active = $this->undecryptable( $this->webhook( ['url' => 'https://example.com/hooks/active'] ) );
        $mutation = /** @lang GraphQL */ '
            mutation ($id: ID!, $status: Boolean!) {
              saveWebhook(id: $id, input: {events: ["page.published"], status: $status}) { status }
            }
        ';

        // Deliveries would fail until the destination is replaced
        $this->actingAs( $this->user )->graphQL( $mutation, ['id' => $webhook->id, 'status' => true] )
            ->assertGraphQLErrorMessage( 'The webhook can\'t be decrypted, replace its URL instead.' );
        $this->assertFalse( $webhook->refresh()->status );

        $this->actingAs( $this->user )->graphQL( $mutation, ['id' => $active->id, 'status' => false] )
            ->assertGraphQLErrorFree()->assertJsonPath( 'data.saveWebhook.status', false );
    }


    public function testSavingUnchangedValuesIsSkipped() : void
    {
        $webhook = $this->webhook( ['editor' => 'other@testbench', 'events' => ['page.deleted', 'page.published']] );
        $updated = $webhook->updated_at;
        $mutation = /** @lang GraphQL */ '
            mutation ($id: ID!, $events: [String!]!, $status: Boolean!) {
              saveWebhook(id: $id, input: {events: $events, status: $status}) { editor }
            }
        ';
        Log::spy();
        Carbon::setTestNow( now()->addMinute() );

        try {
            // The editor, the position in the list and the audit log don't change
            $this->actingAs( $this->user )->graphQL( $mutation, ['id' => $webhook->id, 'events' => ['page.published', 'page.deleted'], 'status' => true] )
                ->assertGraphQLErrorFree()->assertJsonPath( 'data.saveWebhook.editor', 'other@testbench' );

            $this->assertEquals( $updated, $webhook->refresh()->updated_at );
            Log::shouldNotHaveReceived( 'warning' );

            $this->actingAs( $this->user )->graphQL( $mutation, ['id' => $webhook->id, 'events' => ['page.published'], 'status' => false] )
                ->assertGraphQLErrorFree()->assertJsonPath( 'data.saveWebhook.editor', 'editor@testbench' );
        } finally {
            Carbon::setTestNow();
        }

        $this->assertTrue( $webhook->refresh()->updated_at->gt( $updated ) );
        Log::shouldHaveReceived( 'warning' )->once()->with( 'cms.webhook', \Mockery::on( fn( array $data ) =>
            $data['action'] === 'updated' && $data['status'] === false
                && $data['changes'] === ['status', 'events'] && $data['event_count'] === 1
        ) );
    }


    public function testDatesAreReturnedInUtc() : void
    {
        $timezone = date_default_timezone_get();
        date_default_timezone_set( 'Europe/Berlin' );

        try {
            $this->webhook( ['last_success_at' => Carbon::parse( '2026-09-15 12:00:00 UTC' )->setTimezone( 'Europe/Berlin' )] );

            $response = $this->actingAs( $this->user )->graphQL( '{ cmsWebhooks { last_success_at } }' );

            $response->assertGraphQLErrorFree();
            $this->assertSame( '2026-09-15T12:00:00.000000Z', $response->json( 'data.cmsWebhooks.0.last_success_at' ) );
        } finally {
            date_default_timezone_set( $timezone );
        }
    }


    public function testServerStatusReportsDisabledAndBlockedWebhooks() : void
    {
        $query = '{ cmsWebhookServer { enabled blocked stalled_since } }';

        $this->actingAs( new \App\Models\User( ['cmsperms' => []] ) )->graphQL( $query )
            ->assertGraphQLErrorMessage( 'Insufficient permissions' );

        $response = $this->actingAs( $this->user )->graphQL( $query );
        $response->assertGraphQLErrorFree();
        $this->assertSame( ['enabled' => true, 'blocked' => null, 'stalled_since' => null], $response->json( 'data.cmsWebhookServer' ) );

        // Queued deliveries weren't processed for more than 10 minutes
        Carbon::setTestNow( '2026-09-15 12:00:00 UTC' );

        try {
            app( \Aimeos\Cms\WebhookConfig::class )->queued();
            Carbon::setTestNow( '2026-09-15 12:09:59 UTC' );
            $this->assertNull( $this->actingAs( $this->user )->graphQL( $query )->json( 'data.cmsWebhookServer.stalled_since' ) );

            Carbon::setTestNow( '2026-09-15 12:10:00 UTC' );
            $response = $this->actingAs( $this->user )->graphQL( $query );
            $this->assertSame( '2026-09-15T12:00:00.000000Z', $response->json( 'data.cmsWebhookServer.stalled_since' ) );
        } finally {
            Carbon::setTestNow();
            app( \Aimeos\Cms\WebhookConfig::class )->processed();
        }

        config( [
            'cms.webhooks.enabled' => false,
            'cms.webhooks.queue.connection' => 'none',
            'queue.connections.none' => ['driver' => 'null'],
        ] );

        try {
            $response = $this->actingAs( $this->user )->graphQL( $query );
            $this->assertSame( 'invalid_queue', $response->json( 'data.cmsWebhookServer.blocked' ) );
            $this->assertFalse( $response->json( 'data.cmsWebhookServer.enabled' ) );

            config( ['cms.webhooks.deny_cidrs' => ['not-a-cidr']] );
            $response = $this->actingAs( $this->user )->graphQL( $query );
            $this->assertSame( 'invalid_policy', $response->json( 'data.cmsWebhookServer.blocked' ) );
        } finally {
            config( [
                'cms.webhooks.enabled' => true,
                'cms.webhooks.deny_cidrs' => [],
                'cms.webhooks.queue.connection' => null,
            ] );
        }
    }


    public function testAddActiveWebhookAndActiveLimit() : void
    {
        config( ['cms.webhooks.limits.active' => 1, 'cms.webhooks.limits.total' => 100] );

        try {
            $response = $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
                mutation {
                  addWebhook(input: {url: "https://example.com/hook", events: ["page.published"], status: true}) {
                    secret
                    webhook { id status }
                  }
                }
            ' );
            $response->assertGraphQLErrorFree();
            $this->assertTrue( $response->json( 'data.addWebhook.webhook.status' ) );
            $this->assertIsString( $response->json( 'data.addWebhook.secret' ) );

            $limited = $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
                mutation {
                  addWebhook(input: {url: "https://example.org/hook", events: ["page.published"], status: true}) {
                    secret
                  }
                }
            ' );
            $limited->assertGraphQLErrorMessage( 'The active webhook limit has been reached.' );

            $inactive = $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
                mutation {
                  addWebhook(input: {url: "https://example.org/hook", events: ["page.published"], status: false}) {
                    webhook { status }
                  }
                }
            ' );
            $inactive->assertGraphQLErrorFree();
            $this->assertFalse( $inactive->json( 'data.addWebhook.webhook.status' ) );
            $this->assertSame( 2, Webhook::query()->count() );
        } finally {
            config( ['cms.webhooks.limits.active' => 25] );
        }
    }


    public function testEventListSizeDoesntDependOnTheNumberOfEvents() : void
    {
        // Duplicates are removed, so the list may contain more entries than there are events
        $events = [...WebhookManager::EVENTS, 'page.published'];

        $response = $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
            mutation ($events: [String!]!) {
              addWebhook(input: {url: "https://example.com/hook", events: $events}) {
                webhook { id events }
              }
            }
        ', ['events' => $events] );
        $response->assertGraphQLErrorFree();
        $this->assertEqualsCanonicalizing( WebhookManager::EVENTS, $response->json( 'data.addWebhook.webhook.events' ) );

        $response = $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
            mutation ($id: ID!, $events: [String!]!) {
              saveWebhook(id: $id, input: {events: $events, status: false}) { events }
            }
        ', ['id' => $response->json( 'data.addWebhook.webhook.id' ), 'events' => $events] );
        $response->assertGraphQLErrorFree();
        $this->assertEqualsCanonicalizing( WebhookManager::EVENTS, $response->json( 'data.saveWebhook.events' ) );
    }


    public function testPermissionAndTenantIsolation() : void
    {
        $this->webhook();
        $denied = new \App\Models\User( ['cmsperms' => []] );

        $this->actingAs( $denied )->graphQL( '{ cmsWebhooks { id } }' )
            ->assertGraphQLErrorMessage( 'Insufficient permissions' );

        \Aimeos\Cms\Tenancy::run( 'other', function() {
            $user = new \App\Models\User( ['cmsperms' => \Aimeos\Cms\Permission::all()] );
            $user->setAttribute( 'tenant_id', 'other' );
            $response = $this->actingAs( $user )->graphQL( '{ cmsWebhooks { id } }' );
            $response->assertGraphQLErrorFree();
            $this->assertSame( [], $response->json( 'data.cmsWebhooks' ) );
        } );
    }


    public function testRejectsPrivateUrlAndWebhookLimit() : void
    {
        $private = $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
            mutation { addWebhook(input: {url: "https://127.0.0.1/hook", events: ["page.published"]}) { secret } }
        ' );
        $private->assertGraphQLErrorMessage( 'Invalid or disallowed webhook URL.' );

        config( ['cms.webhooks.deny_cidrs' => ['not-a-cidr']] );

        try {
            $blocked = $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
                mutation { addWebhook(input: {url: "https://93.184.216.34/hook", events: ["page.published"]}) { secret } }
            ' );
            $blocked->assertGraphQLErrorMessage( 'Webhooks are blocked by the server configuration.' );

            // Host names are only checked against the deny list when they are resolved
            $host = $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
                mutation { addWebhook(input: {url: "https://example.org/hook", events: ["page.published"]}) { secret } }
            ' );
            $host->assertGraphQLErrorMessage( 'Webhooks are blocked by the server configuration.' );
        } finally {
            config( ['cms.webhooks.deny_cidrs' => []] );
        }

        config( ['cms.webhooks.limits.total' => 1] );
        $this->webhook();

        try {
            $limited = $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
                mutation { addWebhook(input: {url: "https://example.org/hook", events: ["page.published"]}) { secret } }
            ' );
            $limited->assertGraphQLErrorMessage( 'The webhook limit has been reached.' );
        } finally {
            config( ['cms.webhooks.limits.total' => 100] );
        }
    }


    public function testSubscriptionsAboveLoweredLimitAreListed() : void
    {
        $this->webhook();
        $this->webhook( ['url' => 'https://example.com/hooks/second'] );
        config( ['cms.webhooks.limits.total' => 1] );

        try {
            // They still receive events
            $response = $this->actingAs( $this->user )->graphQL( '{ cmsWebhooks { id } }' );
            $response->assertGraphQLErrorFree();
            $this->assertCount( 2, $ids = $response->json( 'data.cmsWebhooks.*.id' ) );

            // All listed subscriptions can be deleted at once
            $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
                mutation ($id: [ID!]!) { dropWebhook(id: $id) }
            ', ['id' => $ids] )->assertJsonPath( 'data.dropWebhook', 2 );
        } finally {
            config( ['cms.webhooks.limits.total' => 100] );
        }
    }


    public function testReplaceTrimsUrl() : void
    {
        $webhook = $this->webhook();

        // Surrounding whitespace is removed like when adding, also without Laravel's TrimStrings middleware
        app( \Aimeos\Cms\WebhookManager::class )->replace( $webhook->id, " https://example.com/hooks/other\n", $this->user );
        $this->assertSame( 'https://example.com/hooks/other', $webhook->refresh()->url );
    }


    public function testUndecryptableSubscriptionIsListedReplacedAndDeleted() : void
    {
        $webhook = $this->undecryptable( $this->webhook( ['last_error' => ['reason' => 'timeout']] ) );
        $dropped = $this->undecryptable( $this->webhook( ['url' => 'https://example.com/hooks/dropped'] ) );
        $error = ['reason' => 'http_error', 'status' => 503, 'at' => '2026-09-15T14:00:00+02:00'];
        $other = $this->webhook( ['url' => 'https://example.com/hooks/other', 'last_error' => $error] );
        $vars = ['id' => $webhook->id];

        // The other subscriptions are still listed
        $response = $this->actingAs( $this->user )->graphQL( '{ cmsWebhooks { id endpoint last_error { reason status at } } }' );
        $response->assertGraphQLErrorFree();
        $list = array_column( $response->json( 'data.cmsWebhooks' ), null, 'id' );

        $this->assertSame( ['reason' => 'invalid_encryption', 'status' => null, 'at' => null], $list[$webhook->id]['last_error'] );
        $this->assertSame( '[invalid endpoint]', $list[$webhook->id]['endpoint'] );
        $this->assertSame( array_replace( $error, ['at' => '2026-09-15T12:00:00.000000Z'] ), $list[$other->id]['last_error'] );
        $this->assertSame( 'https://example.com/…', $list[$other->id]['endpoint'] );

        $response = $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
            mutation ($id: ID!) { pingWebhook(id: $id) { success status reason } }
        ', $vars );
        $this->assertSame( ['success' => false, 'status' => null, 'reason' => 'invalid_encryption'], $response->json( 'data.pingWebhook' ) );

        $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
            mutation ($id: ID!) { rotateWebhook(id: $id) { secret } }
        ', $vars )->assertGraphQLErrorMessage( 'The webhook can\'t be decrypted, replace its URL instead.' );

        Log::spy();

        $replaced = $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
            mutation ($id: ID!) { replaceWebhook(id: $id, url: "https://example.com/hooks/new") { secret webhook { endpoint last_error { reason } } } }
        ', $vars );
        $replaced->assertGraphQLErrorFree();

        $this->assertSame( ['endpoint' => 'https://example.com/…', 'last_error' => null], $replaced->json( 'data.replaceWebhook.webhook' ) );
        $this->assertSame( [$replaced->json( 'data.replaceWebhook.secret' )], $webhook->refresh()->secrets() );

        $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
            mutation ($id: [ID!]!) { dropWebhook(id: $id) }
        ', ['id' => [$dropped->id]] )->assertGraphQLErrorFree()->assertJsonPath( 'data.dropWebhook', 1 );

        $this->assertSame( 2, Webhook::count() );

        // The old destinations are unknown
        Log::shouldHaveReceived( 'warning' )->with( 'cms.webhook', \Mockery::on( fn( array $data ) =>
            $data['action'] === 'destination_replaced' && $data['old_endpoint'] === '[invalid endpoint]'
                && $data['endpoint'] === 'https://example.com/…'
        ) )->once();
        Log::shouldHaveReceived( 'warning' )->with( 'cms.webhook', \Mockery::on( fn( array $data ) =>
            $data['action'] === 'deleted' && $data['webhook_id'] === $dropped->id && $data['endpoint'] === '[invalid endpoint]'
                && !isset( $data['old_endpoint'] )
        ) )->once();
    }


    public function testPausesOfAllSubscriptionsAreReadAtOnce() : void
    {
        $webhooks = [
            $this->webhook(),
            $this->webhook( ['url' => 'https://example.com/hooks/second'] ),
            $this->webhook( ['url' => 'https://example.com/hooks/third'] ),
        ];
        $reads = [];
        Carbon::setTestNow( '2026-09-15 12:00:00 UTC' );

        try {
            WebhookCircuit::webhook( 'test', $webhooks[1]->id, $webhooks[1]->revision )->open( 'timeout', null, [300] );

            \Illuminate\Support\Facades\Event::listen(
                [\Illuminate\Cache\Events\RetrievingKey::class, \Illuminate\Cache\Events\RetrievingManyKeys::class],
                function( object $event ) use ( &$reads ) {
                    $keys = $event instanceof \Illuminate\Cache\Events\RetrievingManyKeys ? $event->keys : [$event->key];
                    $keys = array_values( array_filter( $keys, fn( string $key ) => str_starts_with( $key, 'cms-webhooks-circuit:' ) ) );

                    if( $keys ) {
                        $reads[] = $keys;
                    }
                }
            );

            $response = $this->actingAs( $this->user )->graphQL( '{ cmsWebhooks { id paused_until } }' );
            $response->assertGraphQLErrorFree();
        } finally {
            Carbon::setTestNow();
        }

        $paused = array_column( $response->json( 'data.cmsWebhooks' ), 'paused_until', 'id' );

        $this->assertCount( 1, $reads );
        $this->assertCount( 3, $reads[0] );
        $this->assertNull( $paused[$webhooks[0]->id] );
        $this->assertSame( '2026-09-15T12:05:00.000000Z', $paused[$webhooks[1]->id] );
        $this->assertNull( $paused[$webhooks[2]->id] );
    }


    public function testNormalWebhookTypeHasNoSecretOrUrlField() : void
    {
        $this->webhook();

        $response = $this->actingAs( $this->user )->graphQL( '{ cmsWebhooks { id secret url } }' );

        $this->assertStringContainsString( 'Cannot query field "secret"',
            (string) $response->json( 'errors.0.message' ) );
        $this->assertStringContainsString( 'Cannot query field "url"',
            (string) $response->json( 'errors.1.message' ) );
    }
}
