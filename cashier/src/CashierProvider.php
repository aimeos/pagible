<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;


/**
 * Shared mechanics for concrete Cashier provider drivers.
 *
 * @phpstan-import-type ProductData from CashierProduct
 * @phpstan-import-type TokenData from CashierToken
 */
abstract class CashierProvider
{
    protected string $provider;

    /**
     * Creates a provider with the shared access projection and token services.
     */
    public function __construct( protected CashierAccess $access, protected CashierToken $tokens )
    {
    }


    /**
     * Schedules cancellation at the paid-through period end.
     */
    abstract public function cancel( Authenticatable $user, string $subscription ) : void;


    /**
     * Starts checkout with metadata created by the trusted provider boundary.
     *
     * @param ProductData $product
     */
    final public function checkout( Authenticatable $user, array $product ) : mixed
    {
        if( !$this->access->available( $user ) ) {
            abort( 409 );
        }

        $metadata = ['cms' => $this->tokens->create( $user, $this->provider, $product )];
        $product['url'] = url( $product['url'] );

        return $this->start( $user, $product, $metadata );
    }


    /**
     * Cancels a CMS-created local Cashier subscription owned by the user.
     */
    protected function cancelSubscription( Authenticatable $user, string $subscription, string $column, string $marker = 'type' ) : void
    {
        if( !method_exists( $user, 'subscriptions' ) ) {
            abort( 404 );
        }

        $prefix = CashierAccess::subscription( Tenancy::value() );

        $user->subscriptions()
            ->where( $column, $subscription )
            ->where( $marker, 'like', $prefix . '%' )
            ->firstOrFail()
            ->cancel();
    }


    /**
     * Converts a provider timestamp or date into an immutable UTC value.
     */
    protected function end( mixed $value ) : ?\DateTimeImmutable
    {
        try
        {
            if( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) {
                return ( new \DateTimeImmutable( '@' . $value ) )->setTimezone( new \DateTimeZone( 'UTC' ) );
            }

            if( $value instanceof \DateTimeInterface ) {
                return \DateTimeImmutable::createFromInterface( $value )->setTimezone( new \DateTimeZone( 'UTC' ) );
            }

            return is_string( $value ) && $value !== ''
                ? ( new \DateTimeImmutable( $value ) )->setTimezone( new \DateTimeZone( 'UTC' ) )
                : null;
        }
        catch( \Exception )
        {
            return null;
        }
    }


    /**
     * Grants access when signed metadata and provider state agree.
     *
     * @param array<string, mixed>|object $data
     */
    protected function grant( array|object $data, string $id, string $kind, mixed $end = null, mixed $at = null ) : void
    {
        $provider = $this->provider;
        $meta = $this->meta( $data );

        if( !$meta || $meta['provider'] !== $provider || $meta['kind'] !== $kind
            || $id === '' || !( $user = $this->user( $meta ) ) || !$this->verify( $data, $meta, $user )
        ) {
            return;
        }

        $date = $this->end( $end );
        $occurred = $this->end( $at );

        if( ( $kind === 'subscription' && !$date ) || ( $kind === 'once' && $end !== null )  || ( $at !== null && !$occurred ) ) {
            return;
        }

        $this->access->grant(
            $user,
            $meta['tenant'],
            $meta['access'],
            $provider,
            $id,
            $date,
            $occurred ?? new \DateTimeImmutable(),
        );
    }


    /**
     * Reads signed CMS metadata from a provider object.
     *
     * @param array<string, mixed>|object $data
     * @return TokenData|null
     */
    protected function meta( array|object $data ) : ?array
    {
        $token = data_get( $data, 'metadata.cms' );

        return is_string( $token ) ? $this->tokens->read( $token ) : null;
    }


    /**
     * Resolves a payment source owner when signed CMS metadata is unavailable.
     *
     * @param array<string, mixed>|object $data
     */
    protected function owner( array|object $data, string $id ) : ?Authenticatable
    {
        return null;
    }


    /**
     * Returns the previous URL on the application's own host.
     */
    protected function previous() : string
    {
        $parts = (array) parse_url( url()->previous( '/' ) );
        $path = '/' . ltrim( $parts['path'] ?? '', '/\\' );
        $query = isset( $parts['query'] ) ? '?' . $parts['query'] : '';
        $fragment = isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '';

        return url( $path . $query . $fragment );
    }


    /**
     * Resolves provider-controlled CMS metadata for an otherwise unknown source.
     *
     * @param array<string, mixed>|object $data
     * @return array<string, mixed>|object
     */
    protected function proof( array|object $data, string $id ) : array|object
    {
        return $data;
    }


    /**
     * Revokes access after resolving and verifying the payment-source owner.
     *
     * @param array<string, mixed>|object $data
     */
    protected function remove( array|object $data, string $id, mixed $at = null ) : void
    {
        if( $id === '' ) {
            return;
        }

        $provider = $this->provider;
        $occurred = $this->end( $at );

        if( $at !== null && !$occurred ) {
            return;
        }

        $meta = $this->meta( $data );
        $user = $meta && $meta['provider'] === $provider ? $this->user( $meta ) : null;
        $verified = $user && $this->verifyRemove( $data, $meta, $user, $id );
        $tenant = $verified ? $meta['tenant'] : null;
        $owner = $user ?? $this->owner( $data, $id );

        if( !$verified && ( !$owner || !$this->access->owns( $owner, $provider, $id ) ) )
        {
            $data = $this->proof( $data, $id );
            $meta = $this->meta( $data );
            $user = $meta && $meta['provider'] === $provider ? $this->user( $meta ) : null;
            $verified = $user && $this->verifyRemove( $data, $meta, $user, $id );
            $tenant = $verified ? $meta['tenant'] : null;
            $owner = $user ?? $this->owner( $data, $id ) ?? $owner;
        }

        if( $owner ) {
            $this->access->remove( $owner, $tenant, $provider, $id, $occurred ?? new \DateTimeImmutable() );
        }
    }


    /**
     * Starts provider-specific checkout with signed metadata.
     *
     * @param ProductData $product
     * @param array<string, string> $metadata
     */
    abstract protected function start( Authenticatable $user, array $product, array $metadata ) : mixed;


    /**
     * Resolves and tenant-validates the user stored in signed metadata.
     *
     * @param TokenData $meta
     */
    protected function user( array $meta ) : ?Authenticatable
    {
        $class = config( 'auth.providers.users.model', 'App\\Models\\User' );

        if( !is_string( $class ) || !is_subclass_of( $class, Model::class ) ) {
            return null;
        }

        /** @var Model|null $user */
        $user = $class::query()->find( $meta['user'] );

        if( !$user instanceof Authenticatable ) {
            return null;
        }

        return Tenancy::allows( $user, $meta['tenant'] ) ? $user : null;
    }


    /**
     * Verifies provider-specific purchase details before granting access.
     *
     * @param array<string, mixed>|object $data
     * @param TokenData $meta
     */
    protected function verify( array|object $data, array $meta, Authenticatable $user ) : bool
    {
        return true;
    }


    /**
     * Verifies that signed CMS metadata belongs to the removed provider source.
     *
     * @param array<string, mixed>|object $data
     * @param TokenData $meta
     */
    protected function verifyRemove( array|object $data, array $meta, Authenticatable $user, string $id ) : bool
    {
        return true;
    }
}
