<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Jobs\BaseDelivery;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\Cache;


/**
 * Validates the operator configuration and returns the endpoints subscribed to events.
 *
 * Configuration problems never stop the application, they only stop the affected deliveries.
 */
class WebhookConfig
{
    /** @var array<string, string> Descriptions of the configuration problems */
    public const REASONS = [
        'destination_not_allowed' => 'The URL points to a denied address',
        'invalid_cache' => 'The cache store must be shared by all servers and queue workers to pause failing destinations and throttle log entries, the "array" and "null" drivers aren\'t',
        'invalid_encryption' => 'Queued deliveries can\'t be encrypted, check the APP_KEY setting',
        'invalid_endpoint' => 'The endpoint must be an array with "url", "secret" and "events" keys',
        'invalid_events' => 'The events must be a non-empty list of supported webhook events',
        'invalid_name' => 'The endpoints must use names as keys which may only contain up to 64 letters, digits, "_" and "-" and which must not be numbers',
        'invalid_policy' => 'The "deny_cidrs" setting contains an invalid IP address or range, all deliveries are blocked',
        'invalid_queue' => 'The queue connection doesn\'t exist or uses the "null" driver',
        'invalid_retry_after' => 'The value must be greater than the "cms.webhooks.timeout" setting plus 13 seconds for connecting, resolving the host name and recording the result, otherwise running deliveries are sent twice',
        'invalid_secret' => 'The secret must be a string or a list of strings, each "whsec_" followed by at least 24 base64 encoded bytes like from "openssl rand -base64 32", empty entries are ignored',
        'invalid_url' => 'The URL isn\'t a valid HTTP or HTTPS URL',
    ];

    /** @var list<string> */
    private const KEYS = ['url', 'secret', 'events'];

    /** Seconds until the same configuration problem is logged again */
    private const LOG_INTERVAL = 600;


    public function __construct( private WebhookClient $client )
    {
    }


    /**
     * Returns why all deliveries are blocked by the server configuration or NULL if they aren't.
     */
    public function blocked() : ?string
    {
        return $this->client->policyError() ?? ( $this->connection() ? null : 'invalid_queue' );
    }


    /**
     * Returns the delivery queue connection or NULL if deliveries can't be queued.
     */
    public function connection() : ?string
    {
        $connection = config( 'cms.webhooks.queue.connection' ) ?: config( 'queue.default' );
        $driver = is_string( $connection ) ? config( "queue.connections.{$connection}.driver" ) : null;

        return is_string( $connection ) && is_string( $driver ) && $driver !== 'null' ? $connection : null;
    }


    /**
     * Returns the endpoint if it's subscribed to the event or NULL if it was removed or unsubscribed.
     *
     * @return array{url: string, secrets: list<string>, internal: bool}|null
     * @throws WebhookException If the endpoint configuration is invalid
     */
    public function endpoint( string $name, string $event ) : ?array
    {
        $endpoints = (array) config( 'cms.webhooks.endpoints', [] );

        if( !array_key_exists( $name, $endpoints ) ) {
            return null;
        }

        return $this->target( $this->validate( $name, $endpoints[$name] ), $event );
    }


    /**
     * Returns the configuration problems with the config keys to fix.
     *
     * @return array<string, string> Config keys and reason codes, see REASONS
     */
    public function problems() : array
    {
        $problems = [];

        if( $reason = $this->client->policyError() ) {
            $problems['cms.webhooks.deny_cidrs'] = $reason;
        }

        foreach( (array) config( 'cms.webhooks.endpoints', [] ) as $name => $endpoint )
        {
            try {
                $this->validate( $name, $endpoint );
            } catch( WebhookException $e ) {
                // An invalid deny list is already reported as its own problem
                if( $e->reason !== 'invalid_policy' ) {
                    $problems['cms.webhooks.endpoints.' . $name] = $e->reason;
                }
            }
        }

        if( !( $connection = $this->connection() ) ) {
            $problems['cms.webhooks.queue.connection'] = 'invalid_queue';
        }

        $retryAfter = $connection ? config( "queue.connections.{$connection}.retry_after" ) : null;

        // Workers retry running jobs after "retry_after" seconds, so the job must end before
        if( is_numeric( $retryAfter ) && (int) $retryAfter <= WebhookClient::duration() + BaseDelivery::RECORD_TIME ) {
            $problems["queue.connections.{$connection}.retry_after"] = 'invalid_retry_after';
        }

        $store = config( 'cache.default' );

        if( in_array( is_string( $store ) ? config( "cache.stores.{$store}.driver" ) : null, ['array', 'null'], true ) ) {
            $problems['cache.default'] = 'invalid_cache';
        }

        try {
            app( Encrypter::class );
        } catch( \Throwable ) {
            $problems['app.key'] = 'invalid_encryption';
        }

        return $problems;
    }


    /**
     * Returns the valid endpoints subscribed to the event, they receive the events of all tenants.
     *
     * Invalid endpoints are skipped and logged so a misconfiguration only stops that endpoint.
     *
     * @return list<string> Endpoint names
     */
    public function subscribed( string $event ) : array
    {
        $result = [];

        foreach( (array) config( 'cms.webhooks.endpoints', [] ) as $name => $endpoint )
        {
            try {
                $target = $this->target( $this->validate( $name, $endpoint ), $event );
            } catch( WebhookException $e ) {
                $this->warn( 'cms.webhook.endpoint_invalid', ['endpoint' => (string) $name, 'reason' => $e->reason] );
                continue;
            }

            if( $target ) {
                $result[] = (string) $name;
            }
        }

        return $result;
    }


    /**
     * Logs a configuration problem at most once per interval instead of once per event.
     *
     * @param array<string, mixed> $fields Structured entry fields which identify the problem
     */
    public function warn( string $message, array $fields ) : void
    {
        try {
            // Values from the configuration may contain invalid UTF-8 which json_encode() can't distinguish
            $key = hash( 'sha256', $message . serialize( $fields ) );
            $new = Cache::add( 'cms-webhooks-warn:' . $key, true, self::LOG_INTERVAL );
        } catch( \Throwable ) {
            $new = true;
        }

        if( $new ) {
            Watch::warn( $message, $fields );
        }
    }


    /**
     * Returns the endpoint destination if it's subscribed to the event.
     *
     * @param array{url: string, secrets: list<string>, events: list<string>} $endpoint
     * @return array{url: string, secrets: list<string>, internal: bool}|null
     */
    private function target( array $endpoint, string $event ) : ?array
    {
        if( !in_array( $event, $endpoint['events'], true ) ) {
            return null;
        }

        return ['url' => $endpoint['url'], 'secrets' => $endpoint['secrets'], 'internal' => true];
    }


    /**
     * Returns the validated endpoint configuration.
     *
     * @return array{url: string, secrets: list<string>, events: list<string>}
     * @throws WebhookException If the endpoint configuration is invalid
     */
    private function validate( mixed $name, mixed $endpoint ) : array
    {
        // Endpoints configured as a list instead of a map get integer keys, so their name is missing
        if( !is_string( $name ) || !preg_match( '/^[A-Za-z0-9_-]{1,64}$/', $name ) ) {
            throw new WebhookException( 'invalid_name' );
        }

        if( !is_array( $endpoint ) || array_diff( array_keys( $endpoint ), self::KEYS ) !== [] ) {
            throw new WebhookException( 'invalid_endpoint' );
        }

        if( !is_string( $url = $endpoint['url'] ?? null ) ) {
            throw new WebhookException( 'invalid_url' );
        }

        $url = $this->client->canonical( $url, true );

        // Several secrets allow rotation, empty ones allow optional environment variables
        $secrets = array_values( array_filter(
            is_array( $secret = $endpoint['secret'] ?? null ) ? $secret : [$secret],
            fn( mixed $value ) => $value !== null && $value !== '',
        ) );

        if( $secrets === [] || array_filter( $secrets, fn( mixed $value ) => !is_string( $value ) || $this->client->key( $value ) === null ) !== [] ) {
            throw new WebhookException( 'invalid_secret' );
        }

        /** @var list<string> $secrets */

        $events = $endpoint['events'] ?? null;

        if( !$this->validList( $events ) || array_diff( $events, WebhookManager::EVENTS ) !== [] ) {
            throw new WebhookException( 'invalid_events' );
        }

        return ['url' => $url, 'secrets' => $secrets, 'events' => $events];
    }


    /**
     * @phpstan-assert-if-true non-empty-list<string> $value
     */
    private function validList( mixed $value ) : bool
    {
        return is_array( $value ) && $value !== [] && array_is_list( $value )
            && array_filter( $value, fn( mixed $item ) => !is_string( $item ) ) === [];
    }
}
