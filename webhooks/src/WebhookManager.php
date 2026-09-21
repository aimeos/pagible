<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Models\Webhook;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Encryption\DecryptException;
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


    /** Maximum number of subscriptions deleted at once, same as "dropWebhook" in the GraphQL schema and the admin panel batches */
    private const DROP_MAX = 100;

    /** Seconds until the next test event can be sent to the same subscription */
    private const PING_INTERVAL = 10;


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

            if( $status ) {
                $this->assertLimit( active: true );
            }

            return Webhook::forceCreate( [
                'status' => $status,
                'revision' => 1,
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
     * Deletes subscriptions belonging to the current tenant.
     *
     * @param list<string> $ids
     */
    public function drop( array $ids, ?Authenticatable $user ) : int
    {
        $tenant = $this->authorize( $user );
        $ids = array_values( array_unique( array_filter( $ids, 'is_string' ) ) );

        // Independent of the total limit because subscriptions above a lowered limit are listed too
        if( $ids === [] || count( $ids ) > self::DROP_MAX ) {
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
            $this->changed( 'deleted', $actor, $webhook );
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
        $this->cooldown( $webhook );

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
     * Deletes all subscriptions of a tenant under the same tenant lock as admin mutations.
     */
    public function purge( string $tenant ) : int
    {
        $count = $this->locked( $tenant, fn() => Webhook::withoutTenancy()->where( 'tenant_id', $tenant )->delete() );

        // One entry for all subscriptions, as their destinations may not be decryptable any more
        if( $count > 0 )
        {
            $fields = ['action' => 'purged', 'actor' => 'cli', 'tenant_id' => $tenant, 'webhook_count' => $count];

            DB::connection( config( 'cms.db', 'sqlite' ) )->afterCommit(
                fn() => Watch::warn( 'cms.webhook', $fields )
            );
        }

        return $count;
    }


    /**
     * Re-encrypts one subscription under the same tenant lock as admin mutations.
     *
     * @throws DecryptException If the stored values can't be decrypted with the configured keys
     * @throws LockTimeoutException If the subscriptions of the tenant are changed at the same time
     */
    public function reencrypt( string $tenant, string $id ) : bool
    {
        return $this->locked( $tenant, function() use ( $id, $tenant ) {
            $webhook = Webhook::withoutTenancy()
                ->where( 'tenant_id', $tenant )
                ->where( 'id', $id )
                ->first();

            if( !$webhook ) {
                return false;
            }

            // Rotated secrets whose grace period is over are removed instead of re-encrypted
            $raw = ( new Webhook() )->forceFill( [
                'url' => $webhook->url,
                'secrets' => $webhook->validSecrets(),
            ] )->getAttributes();

            // Re-encryption isn't a configuration change, so "updated_at" is left untouched
            return (bool) Webhook::withoutTenancy()
                ->where( ['tenant_id' => $tenant, 'id' => $id] )
                ->toBase()
                ->update( ['url' => $raw['url'], 'secrets' => $raw['secrets']] );
        } );
    }


    /**
     * Replaces a destination, rotates its secret and leaves it inactive.
     *
     * Queued deliveries to the old destination are cancelled and the delivery health is reset.
     *
     * @return array{webhook: Webhook, secret: string}
     */
    public function replace( string $id, string $url, ?Authenticatable $user ) : array
    {
        $tenant = $this->authorize( $user );
        $url = $this->canonical( trim( $url ) );
        $secret = $this->secret();
        $actor = Utils::editor( $user );

        [$webhook, $oldEndpoint] = $this->locked( $tenant, function() use ( $actor, $id, $secret, $url ) {
            $webhook = $this->find( $id );
            $oldEndpoint = $webhook->endpoint;

            $webhook->forceFill( [
                'url' => $url,
                'secrets' => [['secret' => $secret, 'until' => null]],
                'status' => false,
                'revision' => $webhook->revision + 1,
                'last_error' => null,
                'last_success_at' => null,
                'editor' => $actor,
            ] )->save();

            return [$webhook, $oldEndpoint];
        } );

        $this->changed( 'destination_replaced', $actor, $webhook, $oldEndpoint );

        return ['webhook' => $webhook, 'secret' => $secret];
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
        $grace = max( 0, (int) config( 'cms.webhooks.rotation_grace', 86400 ) );
        $secret = $this->secret();
        $actor = Utils::editor( $user );

        $webhook = $this->locked( $tenant, function() use ( $actor, $grace, $id, $secret ) {
            $webhook = $this->find( $id );

            // A new secret doesn't help if the destination can't be decrypted any more
            $this->assertDecryptable( $webhook );

            // Only the replaced secret stays valid, a secret rotated before isn't accepted any more
            $secrets = [['secret' => $secret, 'until' => null]];

            if( $grace ) {
                $secrets[] = ['secret' => $webhook->secrets()[0], 'until' => now()->addSeconds( $grace )->getTimestamp()];
            }

            $webhook->forceFill( ['secrets' => $secrets, 'editor' => $actor] )->save();

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
            $active = $webhook->status;

            if( $status && !$active )
            {
                // Deliveries would fail until the destination is replaced
                $this->assertDecryptable( $webhook );
                $this->assertLimit( active: true );
            }

            $webhook->forceFill( [
                'name' => $name ?? $webhook->name,
                'events' => $events,
                'status' => $status,
            ] );

            // Unchanged subscriptions keep their editor and position in the list and aren't logged
            if( !$changes = array_keys( $webhook->getDirty() ) ) {
                return [$webhook, []];
            }

            // Deactivating cancels the queued deliveries so they aren't sent after activating again,
            // removed events are checked before sending
            $webhook->forceFill( [
                'revision' => $active && !$status ? $webhook->revision + 1 : $webhook->revision,
                'editor' => $actor,
            ] )->save();

            return [$webhook, $changes];
        } );

        if( $changes ) {
            $this->changed( 'updated', $actor, $webhook, changes: $changes );
        }

        return $webhook;
    }


    private function assertDecryptable( Webhook $webhook ) : void
    {
        if( !$webhook->decryptable() ) {
            throw new Exception( 'The webhook can\'t be decrypted, replace its URL instead.' );
        }
    }


    /**
     * @param bool $active Checks the limit of active subscriptions instead of all
     */
    private function assertLimit( bool $active = false ) : void
    {
        $limit = $active ? config( 'cms.webhooks.limits.active', 25 ) : config( 'cms.webhooks.limits.total', 100 );
        $count = Webhook::query()->when( $active, fn( $query ) => $query->where( 'status', 1 ) )->count();

        if( $count >= max( 1, (int) $limit ) ) {
            throw new Exception( $active ? 'The active webhook limit has been reached.' : 'The webhook limit has been reached.' );
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
        // Host names are only checked against the deny list when they are resolved for a delivery
        if( $this->client->policyError() ) {
            throw new Exception( 'Webhooks are blocked by the server configuration.' );
        }

        try {
            return $this->client->canonical( $url );
        } catch( WebhookException ) {
            throw new Exception( 'Invalid or disallowed webhook URL.' );
        }
    }


    /**
     * Logs the destination without its path, which may contain credentials.
     *
     * @param string|null $oldEndpoint Endpoint before the destination was replaced
     * @param list<string>|null $changes Names of the changed fields
     */
    private function changed( string $action, string $actor, Webhook $webhook, ?string $oldEndpoint = null, ?array $changes = null ) : void
    {
        $fields = [
            'action' => $action,
            'actor' => $actor,
            'webhook_id' => (string) $webhook->id,
            'status' => (bool) $webhook->status,
            'event_count' => count( (array) $webhook->events ),
            'tenant_id' => (string) $webhook->tenant_id,
            'endpoint' => $webhook->endpoint,
            'old_endpoint' => $oldEndpoint,
            'changes' => $changes,
        ];

        DB::connection( config( 'cms.db', 'sqlite' ) )->afterCommit(
            fn() => Watch::warn( 'cms.webhook', $fields )
        );
    }


    /**
     * Limits the test events for each subscription because each one keeps a server process busy
     * until the destination answered.
     *
     * A replaced destination can be tested immediately.
     */
    private function cooldown( Webhook $webhook ) : void
    {
        $key = 'cms-webhooks-ping:' . hash( 'sha256', $webhook->tenant_id . "\n" . $webhook->id . "\n" . $webhook->revision );

        try {
            $allowed = Cache::add( $key, true, self::PING_INTERVAL );
        } catch( \Throwable ) {
            $allowed = true; // an unavailable cache doesn't prevent test events
        }

        if( !$allowed ) {
            throw new Exception( 'Please wait a few seconds before sending another test event.' );
        }
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


    /**
     * Names are shown in one line, so line breaks and other control characters are replaced.
     * Bidirectional text controls are removed because they can display names reversed.
     */
    private function name( string $name ) : string
    {
        $name = preg_replace(
            ['/[\p{Cc}\x{2028}\x{2029}]+/u', '/[\x{202A}-\x{202E}\x{2066}-\x{2069}]+/u'],
            [' ', ''],
            $name
        );

        if( $name === null || mb_strlen( $name = trim( $name ) ) > 100 ) {
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
