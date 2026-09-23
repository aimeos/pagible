<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Models\Webhook;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


/**
 * Applies tenant-safe webhook configuration changes under a tenant lock.
 */
class WebhookManager
{
    /** @var list<string> */
    public const EVENTS = [
        'page.published',
        'page.moved',
        'page.deleted',
        'page.restored',
        'page.purged',
        'element.published',
        'element.deleted',
        'element.restored',
        'element.purged',
        'file.published',
        'file.deleted',
        'file.restored',
        'file.purged',
    ];


    /** Maximum number of subscriptions purged at once, same as "purgeWebhook" in the GraphQL schema and the admin panel batches */
    private const PURGE_MAX = 100;

    /** Seconds the previous secret still signs deliveries after rotating the secret */
    private const ROTATION_GRACE = 86400;


    public function __construct( private readonly WebhookClient $client )
    {
    }


    /**
     * Creates a subscription and returns its one-time secret.
     *
     * @param list<string> $events
     * @return array{webhook: Webhook, secret: string}
     */
    public function add( string $url, array $events, bool $status, ?Authenticatable $user, string $name = '' ) : array
    {
        $tenant = $this->authorize( $user );
        $url = $this->canonical( trim( $url ) );
        $events = $this->events( $events );
        $name = $this->name( $name );
        $secret = $this->secret();
        $actor = Utils::editor( $user );

        $webhook = $this->locked( $tenant, function() use ( $actor, $events, $name, $secret, $status, $url ) {
            $this->assertLimit();

            return Webhook::forceCreate( [
                'status' => $status,
                'name' => $name,
                'url' => $url,
                'secrets' => [['secret' => $secret, 'until' => null]],
                'events' => $events,
                'last_error' => null,
                'last_success_at' => null,
                'editor' => $actor,
            ] );
        } );

        $this->changed( 'created', $actor, $webhook );
        return ['webhook' => $webhook, 'secret' => $secret];
    }


    /**
     * Permanently deletes subscriptions belonging to the current tenant.
     *
     * @param list<string> $ids
     */
    public function purge( array $ids, ?Authenticatable $user ) : int
    {
        $tenant = $this->authorize( $user );
        $ids = array_values( array_unique( array_filter( $ids, 'is_string' ) ) );

        // Independent of the limit because subscriptions above a lowered limit are listed too
        if( $ids === [] || count( $ids ) > self::PURGE_MAX ) {
            throw new Exception( 'Invalid webhook selection.' );
        }

        $actor = Utils::editor( $user );
        $webhooks = $this->locked( $tenant, function() use ( $ids ) {
            $webhooks = Webhook::query()->whereIn( 'id', $ids )->get();

            Webhook::query()
                ->whereIn( 'id', $webhooks->modelKeys() )
                ->delete();

            return $webhooks;
        } );

        foreach( $webhooks as $webhook ) {
            $this->changed( 'purged', $actor, $webhook );
        }

        return $webhooks->count();
    }


    /**
     * Sends a signed test event to a subscription, also if it's inactive.
     *
     * The result doesn't change the delivery health of the subscription but a successful test
     * event resumes paused deliveries immediately.
     *
     * @return array{success: bool, status: int|null, reason: string|null}
     */
    public function ping( string $id, ?Authenticatable $user ) : array
    {
        $tenant = $this->authorize( $user );

        if( !(bool) config( 'cms.webhooks.enabled', false ) ) {
            throw new Exception( 'Webhooks are disabled by the server configuration.' );
        }

        $webhook = $this->find( $id );

        $body = json_encode( [
            'event' => 'webhook.ping',
            'tenant_id' => $tenant,
            'timestamp' => now()->utc()->toRfc3339String( true ),
            'data' => ['id' => $webhook->id],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES );

        try {
            $response = $this->client->send( $webhook->target(), (string) Str::uuid(), $body );
        } catch( WebhookException $e ) {
            return ['success' => false, 'status' => null, 'reason' => $e->reason];
        }

        if( !$response->successful() ) {
            return ['success' => false, 'status' => $response->status, 'reason' => 'http_error'];
        }

        $webhook->circuit()?->close();
        return ['success' => true, 'status' => $response->status, 'reason' => null];
    }


    /**
     * Rotates a secret while the subscription keeps its state and queued deliveries.
     *
     * Deliveries are signed with the current and the previous secret during the grace period
     * so receivers can switch to the new secret without losing deliveries.
     *
     * @return array{webhook: Webhook, secret: string}
     */
    public function rotate( string $id, ?Authenticatable $user ) : array
    {
        $tenant = $this->authorize( $user );
        $secret = $this->secret();
        $actor = Utils::editor( $user );

        $webhook = $this->locked( $tenant, function() use ( $actor, $id, $secret ) {
            $webhook = $this->find( $id );

            // Only the replaced secret stays valid, a secret rotated before isn't accepted any more
            $secrets = [['secret' => $secret, 'until' => null]];

            // Secrets which can't be decrypted any more are replaced immediately
            if( $webhook->decryptable() ) {
                $secrets[] = ['secret' => $webhook->secrets()[0], 'until' => now()->addSeconds( self::ROTATION_GRACE )->getTimestamp()];
            }

            $webhook->forceFill( ['secrets' => $secrets, 'editor' => $actor] );

            // The new secret fixes deliveries which failed because the old one couldn't be decrypted
            if( ( $webhook->last_error['reason'] ?? null ) === 'invalid_encryption' ) {
                $webhook->last_error = null;
            }

            $webhook->save();

            return $webhook;
        } );

        $this->changed( 'secret_rotated', $actor, $webhook );

        return ['webhook' => $webhook, 'secret' => $secret];
    }


    /**
     * Changes only name, subscriptions and active state; URL and secret have dedicated operations.
     *
     * @param list<string> $events
     * @param string|null $name New name or NULL to keep the current one
     */
    public function save( string $id, array $events, bool $status, ?Authenticatable $user, ?string $name = null ) : Webhook
    {
        $tenant = $this->authorize( $user );
        $events = $this->events( $events );
        $name = $name !== null ? $this->name( $name ) : null;
        $actor = Utils::editor( $user );

        [$webhook, $changes] = $this->locked( $tenant, function() use ( $actor, $events, $id, $name, $status ) {
            $webhook = $this->find( $id );

            $webhook->forceFill( [
                'name' => $name ?? $webhook->name,
                'events' => $events,
                'status' => $status,
            ] );

            // Unchanged subscriptions keep their editor and position in the list and aren't logged
            if( !$changes = array_keys( $webhook->getDirty() ) ) {
                return [$webhook, []];
            }

            $webhook->forceFill( ['editor' => $actor] )->save();

            return [$webhook, $changes];
        } );

        if( $changes ) {
            $this->changed( 'updated', $actor, $webhook, changes: $changes );
        }

        return $webhook;
    }


    private function assertLimit() : void
    {
        if( Webhook::query()->count() >= max( 1, (int) config( 'cms.webhooks.limit', 25 ) ) ) {
            throw new Exception( 'The webhook limit has been reached.' );
        }
    }


    private function authorize( ?Authenticatable $user ) : string
    {
        if( !Permission::can( 'config:webhook', $user ) ) {
            throw new Exception( 'Permission denied.' );
        }

        return Tenancy::value();
    }


    private function canonical( string $url ) : string
    {
        try {
            return $this->client->canonical( $url );
        } catch( WebhookException ) {
            throw new Exception( 'Invalid or disallowed webhook URL.' );
        }
    }


    /**
     * Logs the endpoint instead of the URL, which may contain credentials.
     *
     * @param list<string>|null $changes Names of the changed fields
     */
    private function changed( string $action, string $actor, Webhook $webhook, ?array $changes = null ) : void
    {
        $fields = [
            'action' => $action,
            'actor' => $actor,
            'webhook_id' => (string) $webhook->id,
            'status' => (bool) $webhook->status,
            'event_count' => count( (array) $webhook->events ),
            'tenant_id' => (string) $webhook->tenant_id,
            'endpoint' => $webhook->endpoint,
            'changes' => $changes,
        ];

        DB::connection( config( 'cms.db', 'sqlite' ) )->afterCommit(
            fn() => Watch::warn( 'cms.webhook', $fields )
        );
    }


    /**
     * @param list<string> $events
     * @return list<string>
     */
    private function events( array $events ) : array
    {
        $events = array_values( array_unique( array_filter( $events, 'is_string' ) ) );

        if( $events === [] || array_diff( $events, self::EVENTS ) !== [] ) {
            throw new Exception( 'Invalid webhook events.' );
        }

        sort( $events );
        return $events;
    }


    private function find( string $id ) : Webhook
    {
        return Webhook::query()->find( $id ) ?? throw new Exception( 'Webhook not found.' );
    }


    /**
     * @template T
     * @param \Closure(): T $callback
     * @return T
     */
    private function locked( string $tenant, \Closure $callback ) : mixed
    {
        $seconds = max( 1, (int) config( 'cms.lock', 30 ) );
        $key = 'cms_webhooks_' . hash( 'sha256', $tenant );

        return Cache::lock( $key, $seconds )->block(
            $seconds,
            fn() => Utils::transaction( $callback ),
        );
    }


    private function name( string $name ) : string
    {
        if( mb_strlen( $name = trim( $name ) ) > 100 ) {
            throw new Exception( 'Invalid webhook name.' );
        }

        return $name;
    }


    /**
     * Returns a new secret in the Standard Webhooks format.
     */
    private function secret() : string
    {
        return 'whsec_' . base64_encode( random_bytes( 32 ) );
    }
}
