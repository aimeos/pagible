<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Events\WebhookChanged;
use Aimeos\Cms\Models\Webhook;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;


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


    public function __construct( private readonly WebhookClient $client )
    {
    }


    /**
     * Creates an inactive subscription and returns its one-time secret.
     *
     * @param array<string, mixed> $input
     * @return array{webhook: Webhook, secret: string}
     */
    public function add( array $input, ?Authenticatable $user ) : array
    {
        $tenant = $this->authorize( $user );
        $url = $this->canonical( $this->string( $input['url'] ?? null, 'URL' ) );
        $events = $this->events( $input['events'] ?? null );
        $secret = $this->secret();
        $actor = Utils::editor( $user );

        $webhook = $this->locked( $tenant, function() use ( $actor, $events, $secret, $tenant, $url ) {
            $count = Webhook::withoutTenancy()->where( 'tenant_id', $tenant )->count();

            if( $count >= max( 1, (int) config( 'cms.webhooks.limits.total', 100 ) ) ) {
                throw new Exception( 'The webhook limit has been reached.' );
            }

            $webhook = new Webhook();
            $webhook->forceFill( [
                'tenant_id' => $tenant,
                'status' => false,
                'revision' => 1,
                'failures' => 0,
                'url' => $url,
                'secret' => $secret,
                'events' => $events,
                'last_error' => null,
                'editor' => $actor,
            ] );
            $webhook->save();

            return $webhook;
        } );

        $this->changed( 'created', $actor, $webhook, '', $this->host( $url ) );
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

        if( $ids === [] || count( $ids ) > max( 1, (int) config( 'cms.webhooks.limits.total', 100 ) ) ) {
            throw new Exception( 'Invalid webhook selection.' );
        }

        $actor = Utils::editor( $user );
        $webhooks = $this->locked( $tenant, function() use ( $ids, $tenant ) {
            $webhooks = Webhook::withoutTenancy()
                ->where( 'tenant_id', $tenant )
                ->whereIn( 'id', $ids )
                ->get();

            Webhook::withoutTenancy()
                ->where( 'tenant_id', $tenant )
                ->whereIn( 'id', $webhooks->modelKeys() )
                ->delete();

            return $webhooks;
        } );

        foreach( $webhooks as $webhook ) {
            $this->changed( 'deleted', $actor, $webhook, $this->host( $webhook->url ) );
        }

        return $webhooks->count();
    }


    /**
     * Re-encrypts one subscription under the same tenant lock as admin mutations.
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

            $encrypted = new Webhook();
            $encrypted->setAttribute( 'url', $webhook->url );
            $encrypted->setAttribute( 'secret', $webhook->secret );
            $encrypted->setAttribute( 'last_error', $webhook->last_error );
            $raw = $encrypted->getAttributes();

            Webhook::withoutTenancy()
                ->where( 'tenant_id', $tenant )
                ->where( 'id', $id )
                ->update( [
                    'url' => $raw['url'],
                    'secret' => $raw['secret'],
                    'last_error' => $raw['last_error'] ?? null,
                    'updated_at' => now(),
                ] );

            return true;
        } );
    }


    /**
     * Replaces a destination, rotates its secret and leaves it inactive.
     *
     * @return array{webhook: Webhook, secret: string}
     */
    public function replace( string $id, string $url, ?Authenticatable $user ) : array
    {
        return $this->renew( $id, $user, $url );
    }


    /**
     * Rotates a secret and leaves the subscription inactive.
     *
     * @return array{webhook: Webhook, secret: string}
     */
    public function rotate( string $id, ?Authenticatable $user ) : array
    {
        return $this->renew( $id, $user );
    }


    /**
     * Changes only subscriptions and active state; URL and secret have dedicated operations.
     *
     * @param array<string, mixed> $input
     */
    public function save( string $id, array $input, ?Authenticatable $user ) : Webhook
    {
        $tenant = $this->authorize( $user );
        $events = $this->events( $input['events'] ?? null );
        $status = array_key_exists( 'status', $input )
            ? filter_var( $input['status'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE )
            : null;

        if( $status === null ) {
            throw new Exception( 'Invalid webhook status.' );
        }

        $actor = Utils::editor( $user );
        $webhook = $this->locked( $tenant, function() use ( $actor, $events, $id, $status, $tenant ) {
            $webhook = $this->find( $id, $tenant );

            if( $status && !$webhook->status ) {
                $active = Webhook::withoutTenancy()
                    ->where( 'tenant_id', $tenant )
                    ->where( 'status', 1 )
                    ->count();

                if( $active >= max( 1, (int) config( 'cms.webhooks.limits.active', 25 ) ) ) {
                    throw new Exception( 'The active webhook limit has been reached.' );
                }
            }

            $webhook->forceFill( [
                'events' => $events,
                'status' => $status,
                'revision' => $webhook->revision + 1,
                'editor' => $actor,
            ] )->save();

            return $webhook;
        } );

        $host = $this->host( $webhook->url );
        $this->changed( 'updated', $actor, $webhook, $host, $host );
        return $webhook;
    }


    private function authorize( ?Authenticatable $user ) : string
    {
        if( !Permission::can( 'config:webhook', $user ) ) {
            throw new Exception( 'Permission denied.' );
        }

        $tenant = Tenancy::value();

        if( $tenant === '' && Tenancy::$callback !== null ) {
            throw new Exception( 'No tenant is active.' );
        }

        return $tenant;
    }


    private function canonical( string $url ) : string
    {
        try {
            return $this->client->canonical( $url );
        } catch( WebhookException ) {
            throw new Exception( 'Invalid or disallowed webhook URL.' );
        }
    }


    private function changed( string $action, string $actor, Webhook $webhook,
        string $oldHost = '', string $newHost = '' ) : void
    {
        Watch::dispatch( WebhookChanged::class, fn() => new WebhookChanged(
            action: $action,
            actor: $actor,
            webhookId: (string) $webhook->id,
            eventCount: count( (array) $webhook->events ),
            tenant: (string) $webhook->tenant_id,
            oldHost: $oldHost,
            newHost: $newHost,
        ) );
    }


    /**
     * @return list<string>
     */
    private function events( mixed $events ) : array
    {
        if( !is_array( $events ) ) {
            throw new Exception( 'Invalid webhook events.' );
        }

        $events = array_values( array_unique( array_filter( $events, 'is_string' ) ) );

        if( $events === [] || count( $events ) > count( self::EVENTS )
            || array_diff( $events, self::EVENTS ) !== []
        ) {
            throw new Exception( 'Invalid webhook events.' );
        }

        sort( $events );
        return $events;
    }


    private function find( string $id, string $tenant ) : Webhook
    {
        $webhook = Webhook::withoutTenancy()
            ->where( 'tenant_id', $tenant )
            ->where( 'id', $id )
            ->first();

        if( !$webhook ) {
            throw new Exception( 'Webhook not found.' );
        }

        return $webhook;
    }


    private function host( string $url ) : string
    {
        return strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
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
     * Rotates credentials, optionally replacing the destination, and leaves the subscription inactive.
     *
     * @return array{webhook: Webhook, secret: string}
     */
    private function renew( string $id, ?Authenticatable $user, ?string $url = null ) : array
    {
        $tenant = $this->authorize( $user );
        $url = $url !== null ? $this->canonical( $url ) : null;
        $secret = $this->secret();
        $actor = Utils::editor( $user );

        [$webhook, $oldHost, $newHost] = $this->locked( $tenant,
            function() use ( $actor, $id, $secret, $tenant, $url ) {
                $webhook = $this->find( $id, $tenant );
                $current = $webhook->url;
                $replacement = $url ?? $current;
                $attributes = [
                    'secret' => $secret,
                    'status' => false,
                    'revision' => $webhook->revision + 1,
                    'failures' => 0,
                    'last_error' => null,
                    'editor' => $actor,
                ];

                if( $url !== null ) {
                    $attributes['url'] = $url;
                }

                $webhook->forceFill( $attributes )->save();

                return [$webhook, $this->host( $current ), $this->host( $replacement )];
            },
        );

        $this->changed( $url === null ? 'secret_rotated' : 'destination_replaced',
            $actor, $webhook, $oldHost, $newHost );

        return ['webhook' => $webhook, 'secret' => $secret];
    }


    private function secret() : string
    {
        return rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
    }


    private function string( mixed $value, string $name ) : string
    {
        if( !is_string( $value ) || trim( $value ) === '' ) {
            throw new Exception( "Invalid webhook {$name}." );
        }

        return trim( $value );
    }
}
