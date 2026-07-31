<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;


/**
 * Signs Cashier payloads used by checkout metadata and dynamic plans.
 *
 * @phpstan-import-type ProductData from CashierProduct
 * @phpstan-type TokenData array{
 *   user: string,
 *   tenant: string,
 *   access: string,
 *   provider: string,
 *   kind: string,
 *   reference: string,
 * }
 */
class CashierToken
{
    /**
     * Creates signed metadata binding a trusted product to its user and tenant.
     *
     * @param ProductData $product
     */
    public function create( Authenticatable $user, string $provider, array $product ) : string
    {
        return $this->make( [
            'u' => (string) ( $user instanceof Model ? $user->getKey() : $user->getAuthIdentifier() ),
            't' => Tenancy::value(),
            'a' => $product['access'],
            'p' => $provider,
            'k' => $product['kind'],
            'r' => $product['reference'],
        ] );
    }


    /**
     * Signs an arbitrary Cashier payload.
     *
     * @param array<string, mixed> $data
     */
    public function make( array $data ) : string
    {
        $payload = $this->encode( json_encode( $data,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ) );

        return $payload . '.' . $this->signature( $payload, $this->keys()[0] );
    }


    /**
     * Reads an arbitrary signed Cashier payload.
     *
     * @return array<string, mixed>|null
     */
    public function parse( string $token ) : ?array
    {
        $parts = explode( '.', $token, 2 );

        if( count( $parts ) !== 2 ) {
            return null;
        }

        $valid = false;

        foreach( $this->keys() as $key ) {
            $valid = hash_equals( $this->signature( $parts[0], $key ), $parts[1] ) || $valid;
        }

        if( !$valid ) {
            return null;
        }

        $payload = json_decode( $this->decode( $parts[0] ), true );

        return is_array( $payload ) ? $payload : null;
    }


    /**
     * Reads and validates canonical checkout metadata.
     *
     * @return TokenData|null
     */
    public function read( string $token ) : ?array
    {
        $payload = $this->parse( $token );

        if( !is_array( $payload ) ) {
            return null;
        }

        foreach( ['u', 't', 'a', 'p', 'k', 'r'] as $key )
        {
            if( !is_string( $payload[$key] ?? null ) ) {
                return null;
            }
        }

        if( $payload['u'] === '' || mb_strlen( $payload['t'] ) > 250
            || $payload['a'] === '' || mb_strlen( $payload['a'] ) > 100
            || $payload['p'] === '' || mb_strlen( $payload['p'] ) > 32
            || $payload['r'] === '' || mb_strlen( $payload['r'] ) > 255
            || !in_array( $payload['k'], ['once', 'subscription'], true )
        ) {
            return null;
        }

        return [
            'user' => $payload['u'],
            'tenant' => $payload['t'],
            'access' => $payload['a'],
            'provider' => $payload['p'],
            'kind' => $payload['k'],
            'reference' => $payload['r'],
        ];
    }


    /**
     * Decodes an unpadded URL-safe Base64 value.
     */
    private function decode( string $value ) : string
    {
        $padding = ( 4 - strlen( $value ) % 4 ) % 4;
        $decoded = base64_decode( strtr( $value, '-_', '+/' ) . str_repeat( '=', $padding ), true );

        return $decoded === false ? '' : $decoded;
    }


    /**
     * Encodes bytes as unpadded URL-safe Base64.
     */
    private function encode( string $value ) : string
    {
        return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
    }


    /**
     * Returns the current and previous Laravel application keys.
     *
     * @return list<string>
     */
    private function keys() : array
    {
        $result = [];

        foreach( [config( 'app.key' ), ...(array) config( 'app.previous_keys', [])] as $key )
        {
            if( !is_string( $key ) || $key === '' ) {
                continue;
            }

            if( str_starts_with( $key, 'base64:' ) ) {
                $key = base64_decode( substr( $key, 7 ), true ) ?: '';
            }

            if( $key !== '' ) {
                $result[] = $key;
            }
        }

        if( !$result ) {
            throw new \RuntimeException( 'APP_KEY is required for Cashier signatures.' );
        }

        return array_values( array_unique( $result ) );
    }


    /**
     * Returns the URL-safe HMAC signature for a payload.
     */
    private function signature( string $payload, string $key ) : string
    {
        return $this->encode( hash_hmac( 'sha256', $payload, $key, true ) );
    }
}
