<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;


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
              cmsWebhooks { id endpoint status events last_error last_success_at }
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

        config( ['cms.webhooks.limits.total' => 1] );
        $this->webhook();
        $limited = $this->actingAs( $this->user )->graphQL( /** @lang GraphQL */ '
            mutation { addWebhook(input: {url: "https://example.org/hook", events: ["page.published"]}) { secret } }
        ' );
        $limited->assertGraphQLErrorMessage( 'The webhook limit has been reached.' );
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
