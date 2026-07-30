<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;


/**
 * Maintains the payment-derived access projection on the user.
 */
class CashierAccess
{
    public const BYTES = 131072;
    public const LIMIT = 128;
    public const SUBSCRIPTION_PREFIX = 'cms-access:';


    /**
     * Checks whether another checkout may add a payment source.
     */
    public function available( Authenticatable $user ) : bool
    {
        if( !$user instanceof Model || !$user->exists ) {
            return false;
        }

        $access = $this->values( $user->getAttribute( 'access' ) );

        return count( $access ) < self::LIMIT
            && strlen( json_encode( $access, JSON_THROW_ON_ERROR ) ) < self::BYTES;
    }

    /**
     * Adds or renews a permanent or expiring payment source.
     */
    public function grant( Authenticatable $user, string $tenant, string $role, string $provider,
        string $id, ?\DateTimeInterface $end, ?\DateTimeInterface $at = null
    ) : void
    {
        $tenant = $this->tenantId( $tenant );
        $role = self::text( $role, 100, 'access role' );
        $source = $this->source( $provider, $id );
        $key = $tenant . '|' . $source;
        $occurred = $at ? \DateTimeImmutable::createFromInterface( $at ) : new \DateTimeImmutable();
        $stamp = $this->stamp( $occurred );

        if( $end && $end->getTimestamp() <= time() ) {
            $this->remove( $user, $tenant, $provider, $id, $occurred );
            return;
        }

        $value = [
            'role' => $role,
            'end' => $end ? $this->stamp( $end ) : null,
            'at' => $stamp,
        ];

        $this->mutate( $user, function( array $access ) use ( $key, $stamp, $value ) {
            $current = $access[$key] ?? null;

            if( is_array( $current )
                && ( $current['at'] > $stamp || ( $current['at'] === $stamp && $current['role'] === null ) )
            ) {
                return $access;
            }

            $access[$key] = $value;
            return $access;
        } );
    }


    /**
     * Tests whether the user already contains a payment source, including its tombstone.
     */
    public function owns( Authenticatable $user, string $provider, string $id ) : bool
    {
        if( !$user instanceof Model ) {
            return false;
        }

        $source = $this->source( $provider, $id );

        foreach( array_keys( $this->values( $user->getAttribute( 'access' ) ) ) as $key )
        {
            if( str_ends_with( $key, '|' . $source ) ) {
                return true;
            }
        }

        return false;
    }


    /**
     * Removes a revoked, refunded, charged-back, or ended payment source.
     */
    public function remove( Authenticatable $user, ?string $tenant, string $provider, string $id,
        ?\DateTimeInterface $at = null
    ) : void
    {
        $tenant = $tenant === null ? null : $this->tenantId( $tenant );
        $source = $this->source( $provider, $id );
        $key = $tenant === null ? null : $tenant . '|' . $source;
        $stamp = $this->stamp( $at );

        $this->mutate( $user, function( array $access ) use ( $key, $source, $stamp ) {
            $keys = $key === null
                ? array_filter( array_keys( $access ), fn( string $item ) => str_ends_with( $item, '|' . $source ) )
                : [$key];

            foreach( $keys as $candidate )
            {
                $current = $access[$candidate] ?? null;

                if( ( $current['at'] ?? -1 ) <= $stamp ) {
                    $access[$candidate] = ['role' => null, 'end' => null, 'at' => $stamp];
                }
            }

            return $access;
        } );
    }


    /**
     * Returns active payment-derived access roles without querying billing tables.
     *
     * @return array<int, string>
     */
    public function roles( Authenticatable $user ) : array
    {
        if( !$user instanceof Model ) {
            return [];
        }

        $tenant = Tenancy::value();
        $roles = [];

        foreach( $this->values( $user->getAttribute( 'access' ) ) as $key => $source )
        {
            if( ( $parts = $this->parts( $key ) ) && $parts['tenant'] === $tenant && is_string( $source['role'] ) ) {
                $roles[] = $source['role'];
            }
        }

        return Access::normalize( $roles );
    }


    /**
     * Returns the canonical subscription prefix or complete tenant/role name.
     */
    public static function subscription( string $tenant, ?string $role = null ) : string
    {
        if( mb_strlen( $tenant ) > 250 ) {
            throw new \InvalidArgumentException( 'Invalid tenant.' );
        }

        $prefix = self::SUBSCRIPTION_PREFIX . hash( 'sha256', $tenant ) . ':';

        return $role === null ? $prefix : $prefix . self::text( $role, 100, 'access role' );
    }


    /**
     * Reads the access role from a canonical subscription name for the tenant.
     */
    public static function subscriptionAccess( string $name, string $tenant ) : ?string
    {
        if( mb_strlen( $tenant ) > 250
            || !str_starts_with( $name, $prefix = self::subscription( $tenant ) )
        ) {
            return null;
        }

        $role = trim( substr( $name, strlen( $prefix ) ) );

        return $role !== '' && mb_strlen( $role ) <= 100 ? $role : null;
    }


    /**
     * Serializes one access projection mutation under a user-row lock.
     *
     * @param callable(array<string, mixed>): array<string, mixed> $callback Projection mutation
     */
    private function mutate( Authenticatable $user, callable $callback ) : void
    {
        if( !$user instanceof Model || !$user->exists ) {
            throw new \InvalidArgumentException( 'Cashier access requires a stored Eloquent user.' );
        }

        $user->getConnection()->transaction( function() use ( $user, $callback ) {
            /** @var Model|null $stored */
            $stored = $user->newQuery()->lockForUpdate()->find( $user->getKey() );

            if( !$stored ) {
                throw new \RuntimeException( 'Cashier user no longer exists.' );
            }

            $access = $callback( $this->values( $stored->getAttribute( 'access' ) ) );
            ksort( $access, SORT_STRING );
            $value = $access ?: null;

            $stored->setAttribute( 'access', $value );

            if( $stored->isDirty( 'access' ) ) {
                $stored->saveQuietly();
            }

            $user->setAttribute( 'access', $value );
            $user->syncOriginalAttribute( 'access' );
        } );

        app()->forgetInstance( Access::class );
    }


    /**
     * Splits a stored tenant/provider/source key into its canonical parts.
     *
     * @return array{tenant: string, provider: string, id: string}|null
     */
    private function parts( string $key ) : ?array
    {
        $parts = explode( '|', $key );

        if( count( $parts ) < 3 ) {
            return null;
        }

        $id = trim( (string) array_pop( $parts ) );
        $provider = trim( (string) array_pop( $parts ) );
        $tenant = implode( '|', $parts );

        if( mb_strlen( $tenant ) > 250 || $provider === '' || mb_strlen( $provider ) > 32
            || $id === '' || mb_strlen( $id ) > 255
        ) {
            return null;
        }

        return ['tenant' => $tenant, 'provider' => $provider, 'id' => $id];
    }


    /**
     * Removes malformed entries and expires elapsed grants.
     *
     * @param array<string, mixed> $access Raw access projection
     * @return array<string, array{role: string|null, end: int|null, at: int}>
     */
    private function prune( array $access ) : array
    {
        $result = [];
        $now = $this->stamp( null );

        foreach( $access as $key => $source )
        {
            if( !is_string( $key ) || !is_array( $source ) || !array_key_exists( 'role', $source )
                || !array_key_exists( 'end', $source )
            ) {
                continue;
            }

            if( !( $parts = $this->parts( $key ) ) ) {
                continue;
            }

            $role = is_string( $source['role'] ) ? trim( $source['role'] ) : null;
            $end = $source['end'];
            $at = $source['at'] ?? 0;

            if( !is_int( $at ) || $at < 0 || ( $role !== null && ( $role === '' || mb_strlen( $role ) > 100 ) )
                || ( $role === null && $end !== null ) || ( $role !== null && $end !== null && ( !is_int( $end ) || $end < 0 ) )
            ) {
                continue;
            }

            if( $role !== null && is_int( $end ) && $end <= $now ) {
                $role = $end = null;
            }

            $result[$parts['tenant'] . '|' . $parts['provider'] . '|' . $parts['id']] = [
                'role' => $role,
                'end' => $end,
                'at' => $at,
            ];
        }

        return $result;
    }


    /**
     * Returns a validated provider/source key suffix.
     */
    private function source( string $provider, string $id ) : string
    {
        $provider = self::text( $provider, 32, 'payment provider' );
        $id = self::text( $id, 255, 'payment source' );

        if( str_contains( $provider, '|' ) ) {
            throw new \InvalidArgumentException( 'Invalid payment provider.' );
        }

        if( str_contains( $id, '|' ) ) {
            throw new \InvalidArgumentException( 'Invalid payment source.' );
        }

        return $provider . '|' . $id;
    }


    /**
     * Returns provider event time in Unix milliseconds.
     */
    private function stamp( ?\DateTimeInterface $value ) : int
    {
        $value ??= new \DateTimeImmutable();
        return $value->getTimestamp() * 1000 + intdiv( (int) $value->format( 'u' ), 1000 );
    }


    /**
     * Validates and returns a tenant identifier for an access key.
     */
    private function tenantId( string $tenant ) : string
    {
        if( mb_strlen( $tenant ) > 250 ) {
            throw new \InvalidArgumentException( 'Invalid tenant.' );
        }

        return $tenant;
    }


    /**
     * Validates and normalizes a required access identifier.
     */
    private static function text( string $value, int $max, string $label ) : string
    {
        if( ( $value = trim( $value ) ) === '' || mb_strlen( $value ) > $max ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid %s.', $label ) );
        }

        return $value;
    }


    /**
     * Decodes and normalizes the stored user access projection.
     *
     * @return array<string, array{role: string|null, end: int|null, at: int}>
     */
    private function values( mixed $access ) : array
    {
        if( is_string( $access ) ) {
            $access = json_decode( $access, true );
        }

        return $this->prune( is_array( $access ) ? $access : [] );
    }
}
