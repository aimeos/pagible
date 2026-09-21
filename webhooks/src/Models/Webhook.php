<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Models;

use Aimeos\Cms\Concerns\HasUuids;
use Aimeos\Cms\Concerns\Tenancy;
use Aimeos\Cms\WebhookCircuit;
use Aimeos\Cms\WebhookException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;


/**
 * Tenant-scoped webhook subscription.
 *
 * @property string $id
 * @property string $tenant_id
 * @property bool $status
 * @property int $revision
 * @property string $name
 * @property string $url
 * @property list<array{secret: string, until: int|null}> $secrets Current secret first, rotated ones with their expiry timestamp
 * @property list<string> $events
 * @property array<string, mixed>|null $last_error
 * @property \Illuminate\Support\Carbon|null $last_success_at
 * @property-read \Illuminate\Support\Carbon|null $paused_until
 * @property string $editor
 * @method static \Illuminate\Database\Eloquent\Builder<static> withoutTenancy()
 */
class Webhook extends Model
{
    use HasUuids;
    use Tenancy;

    protected $table = 'cms_webhooks';
    protected $hidden = ['url', 'secrets', 'revision'];
    protected $appends = ['endpoint'];

    private ?WebhookCircuit $circuit = null;
    private ?string $circuitKey = null;


    /**
     * Returns the delivery error as JSON for query updates, which don't apply the attribute casts.
     */
    public static function error( string $reason, ?int $status = null ) : string
    {
        return json_encode( array_filter( [
            'reason' => $reason,
            'status' => $status,
            'at' => now()->utc()->toIso8601String(),
        ], fn( mixed $value ) => $value !== null ), JSON_THROW_ON_ERROR );
    }


    /**
     * Returns the circuit which pauses the deliveries to the subscription or NULL if it isn't stored yet.
     */
    public function circuit() : ?WebhookCircuit
    {
        if( $this->id === null ) {
            return null;
        }

        // Reused so its state is read once, e.g. by WebhookCircuit::load() for several subscriptions
        if( $this->circuitKey !== ( $key = $this->tenant_id . "\n" . $this->id . "\n" . $this->revision ) )
        {
            $this->circuit = WebhookCircuit::webhook( $this->tenant_id, $this->id, $this->revision );
            $this->circuitKey = $key;
        }

        return $this->circuit;
    }


    /**
     * Tests if the destination and secrets can be decrypted, which fails after the application key
     * changed without keeping the old key in APP_PREVIOUS_KEYS.
     */
    public function decryptable() : bool
    {
        try
        {
            // Only loaded values are read, e.g. deliveries don't select the last error
            foreach( ['url', 'secrets'] as $key )
            {
                if( array_key_exists( $key, $this->attributes ) ) {
                    $this->getAttribute( $key );
                }
            }
        }
        catch( DecryptException )
        {
            return false;
        }

        return true;
    }


    /**
     * Returns the last delivery error for the admin panel, which reports subscriptions that can't be
     * decrypted instead of failing to list all of them.
     *
     * @return array<string, mixed>|null
     */
    public function failure() : ?array
    {
        if( !$this->decryptable() ) {
            return ['reason' => 'invalid_encryption'];
        }

        if( !is_array( $error = $this->getAttribute( 'last_error' ) ) ) {
            return null;
        }

        // Returned in the same format as the other dates
        try {
            $error['at'] = is_string( $error['at'] ?? null ) ? Carbon::parse( $error['at'] ) : null;
        } catch( \Throwable ) {
            $error['at'] = null;
        }

        return $error;
    }


    public function getConnectionName() : string
    {
        return config( 'cms.db', 'sqlite' );
    }


    public function getEndpointAttribute() : string
    {
        try {
            $parts = parse_url( $this->url );
            $scheme = strtolower( (string) ( $parts['scheme'] ?? 'https' ) );
            $host = strtolower( (string) ( $parts['host'] ?? '' ) );
            $port = isset( $parts['port'] ) ? ':' . $parts['port'] : '';

            return $host === '' ? '[invalid endpoint]' : $scheme . '://' . $host . $port . '/…';
        } catch( \Throwable ) {
            return '[invalid endpoint]';
        }
    }


    /**
     * Returns when deliveries resume after consecutive temporary failures or NULL if they aren't paused.
     */
    public function getPausedUntilAttribute() : ?\Illuminate\Support\Carbon
    {
        $pause = $this->circuit()?->paused();

        return $pause ? now()->setTimestamp( $pause['until'] ) : null;
    }


    /**
     * Values which can't be decrypted any more are overwritten instead of compared, e.g. when replacing
     * the destination.
     *
     * @param string $key
     */
    public function originalIsEquivalent( $key ) : bool
    {
        try {
            return parent::originalIsEquivalent( $key );
        } catch( DecryptException ) {
            return false;
        }
    }


    /**
     * Returns the signing secrets, the current one first and the rotated one during its grace period.
     *
     * @return list<string>
     */
    public function secrets() : array
    {
        return array_column( $this->validSecrets(), 'secret' );
    }


    /**
     * Returns the destination to send deliveries to.
     *
     * @return array{url: string, secrets: list<string>, ca: string|null, internal: bool}
     * @throws WebhookException If the destination can't be decrypted, which retrying doesn't fix
     *  until the old application key is added to APP_PREVIOUS_KEYS again
     */
    public function target() : array
    {
        if( !$this->decryptable() ) {
            throw new WebhookException( 'invalid_encryption' );
        }

        return ['url' => $this->url, 'secrets' => $this->secrets(), 'ca' => null, 'internal' => false];
    }


    /**
     * Returns the stored secrets without the rotated ones whose grace period is over.
     *
     * @return list<array{secret: string, until: int|null}>
     */
    public function validSecrets() : array
    {
        $now = now()->getTimestamp();

        return array_values( array_filter(
            $this->secrets ?? [],
            fn( array $entry ) => $entry['until'] === null || $entry['until'] > $now
        ) );
    }


    /**
     * @return array<string, string>
     */
    protected function casts() : array
    {
        return [
            'status' => 'boolean',
            'revision' => 'integer',
            'url' => 'encrypted',
            'secrets' => 'encrypted:array',
            'events' => 'array',
            'last_error' => 'array',
            'last_success_at' => 'datetime',
        ];
    }
}
