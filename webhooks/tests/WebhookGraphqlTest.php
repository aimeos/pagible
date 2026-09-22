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


    public function testProvisionSaveQueryRotateAndDrop() : void
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

        $rotated = $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
            mutation ($id: ID!) {
              rotateWebhook(id: $id) { secret webhook { id status } }
            }
        ', ['id' => $id] );
        $rotated->assertGraphQLErrorFree();
        $this->assertTrue( $rotated->json( 'data.rotateWebhook.webhook.status' ) );
        $this->assertNotSame( $secret, $rotated->json( 'data.rotateWebhook.secret' ) );

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
            $this->assertSame( 503, $webhook->last_error['status'] );
            $this->assertTrue( $success->equalTo( $webhook->last_success_at ) );
            $this->assertSame( [$secret, self::secret( 'test' )], $webhook->secrets() );

            // The previous secret expires after the grace period
            Carbon::setTestNow( '2026-09-16 12:00:01 UTC' );
            $this->assertSame( [$secret], $webhook->secrets() );
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
            WebhookCircuit::webhook( 'test', $webhook->id )->open( 'timeout', null, [300] );

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


    public function testSavingKeepsThePause() : void
    {
        $webhook = $this->webhook();
        $mutation = /** @lang GraphQL */ '
            mutation ($id: ID!, $status: Boolean!) {
              saveWebhook(id: $id, input: {events: ["page.deleted", "page.published"], status: $status}) { paused_until }
            }
        ';
        Carbon::setTestNow( '2026-09-15 12:00:00 UTC' );

        try {
            WebhookCircuit::webhook( 'test', $webhook->id )->open( 'timeout', null, [300] );

            // Queued deliveries of the remaining events and the pause of the destination are kept
            $response = $this->actingAs( $this->user )->graphQL( $mutation, ['id' => $webhook->id, 'status' => true] );
            $response->assertGraphQLErrorFree();
            $this->assertSame( '2026-09-15T12:05:00.000000Z', $response->json( 'data.saveWebhook.paused_until' ) );

            // Deactivating and activating again doesn't end the pause
            foreach( [false, true] as $status ) {
                $response = $this->actingAs( $this->user )->graphQL( $mutation, ['id' => $webhook->id, 'status' => $status] );
                $response->assertGraphQLErrorFree();
                $this->assertSame( '2026-09-15T12:05:00.000000Z', $response->json( 'data.saveWebhook.paused_until' ) );
            }
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

        // Names are trimmed
        $response = $this->actingAs( $this->user )->graphQL( $add, ['name' => " Search indexer "] );
        $response->assertGraphQLErrorFree();
        $id = $response->json( 'data.addWebhook.webhook.id' );
        $this->assertSame( 'Search indexer', $response->json( 'data.addWebhook.webhook.name' ) );

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


    public function testSavingUnchangedValuesIsSkipped() : void
    {
        $webhook = $this->webhook( ['editor' => 'other@testbench', 'events' => ['page.deleted', 'page.published']] );
        // Compare with the stored value, SQL Server rounds fractional seconds
        $updated = $webhook->refresh()->updated_at;
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
        $query = '{ cmsWebhookServer { enabled blocked } }';

        $this->actingAs( new \App\Models\User( ['cmsperms' => []] ) )->graphQL( $query )
            ->assertGraphQLErrorMessage( 'Insufficient permissions' );

        $response = $this->actingAs( $this->user )->graphQL( $query );
        $response->assertGraphQLErrorFree();
        $this->assertSame( ['enabled' => true, 'blocked' => null], $response->json( 'data.cmsWebhookServer' ) );

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


    public function testAddActiveWebhook() : void
    {
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
            $blocked->assertGraphQLErrorMessage( 'Invalid or disallowed webhook URL.' );

            // Host names are only checked against the deny list when they are resolved,
            // deliveries are blocked until the deny list is fixed
            $host = $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
                mutation { addWebhook(input: {url: "https://example.org/hook", events: ["page.published"]}) { secret } }
            ' );
            $host->assertGraphQLErrorFree();
        } finally {
            config( ['cms.webhooks.deny_cidrs' => []] );
        }

        config( ['cms.webhooks.limit' => 1] );
        $this->webhook();

        try {
            $limited = $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
                mutation { addWebhook(input: {url: "https://example.org/hook", events: ["page.published"]}) { secret } }
            ' );
            $limited->assertGraphQLErrorMessage( 'The webhook limit has been reached.' );
        } finally {
            config( ['cms.webhooks.limit' => 25] );
        }
    }


    public function testSubscriptionsAboveLoweredLimitAreListed() : void
    {
        $this->webhook();
        $this->webhook( ['url' => 'https://example.com/hooks/second'] );
        config( ['cms.webhooks.limit' => 1] );

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
            config( ['cms.webhooks.limit' => 25] );
        }
    }


    public function testAddTrimsUrl() : void
    {
        // Surrounding whitespace is removed, also without Laravel's TrimStrings middleware
        $result = app( \Aimeos\Cms\WebhookManager::class )->add( " https://example.com/hooks/other\n", ['page.published'], false, $this->user );
        $this->assertSame( 'https://example.com/hooks/other', $result['webhook']->refresh()->url );
    }


    public function testUndecryptableSubscriptionIsListedRotatedAndDeleted() : void
    {
        $webhook = $this->undecryptable( $this->webhook( ['last_error' => ['reason' => 'invalid_encryption']] ) );
        $dropped = $this->undecryptable( $this->webhook( ['url' => 'https://example.com/hooks/dropped'] ) );
        $error = ['reason' => 'http_error', 'status' => 503, 'at' => '2026-09-15T14:00:00+02:00'];
        $other = $this->webhook( ['url' => 'https://example.org/other?token=secret', 'last_error' => $error] );
        $vars = ['id' => $webhook->id];

        // The other subscriptions are still listed
        $response = $this->actingAs( $this->user )->graphQL( '{ cmsWebhooks { id endpoint last_error { reason status at } } }' );
        $response->assertGraphQLErrorFree();
        $list = array_column( $response->json( 'data.cmsWebhooks' ), null, 'id' );

        $this->assertSame( ['reason' => 'invalid_encryption', 'status' => null, 'at' => null], $list[$webhook->id]['last_error'] );
        $this->assertSame( 'https://example.com/hooks/', $list[$webhook->id]['endpoint'] );
        $this->assertSame( array_replace( $error, ['at' => '2026-09-15T12:00:00.000000Z'] ), $list[$other->id]['last_error'] );
        $this->assertSame( 'https://example.org/', $list[$other->id]['endpoint'] );

        $response = $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
            mutation ($id: ID!) { pingWebhook(id: $id) { success status reason } }
        ', $vars );
        $this->assertSame( ['success' => false, 'status' => null, 'reason' => 'invalid_encryption'], $response->json( 'data.pingWebhook' ) );

        Log::spy();

        // The old secret can't be kept for the grace period and its error is fixed
        $rotated = $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
            mutation ($id: ID!) { rotateWebhook(id: $id) { secret webhook { status last_error { reason } } } }
        ', $vars );
        $rotated->assertGraphQLErrorFree();

        $this->assertSame( ['status' => true, 'last_error' => null], $rotated->json( 'data.rotateWebhook.webhook' ) );
        $this->assertSame( [$rotated->json( 'data.rotateWebhook.secret' )], $webhook->refresh()->secrets() );

        $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
            mutation ($id: [ID!]!) { dropWebhook(id: $id) }
        ', ['id' => [$dropped->id]] )->assertGraphQLErrorFree()->assertJsonPath( 'data.dropWebhook', 1 );

        $this->assertSame( 2, Webhook::count() );

        Log::shouldHaveReceived( 'warning' )->with( 'cms.webhook', \Mockery::on( fn( array $data ) =>
            $data['action'] === 'secret_rotated' && $data['webhook_id'] === $webhook->id
        ) )->once();
        Log::shouldHaveReceived( 'warning' )->with( 'cms.webhook', \Mockery::on( fn( array $data ) =>
            $data['action'] === 'deleted' && $data['webhook_id'] === $dropped->id && $data['endpoint'] === 'https://example.com/hooks/'
        ) )->once();
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
