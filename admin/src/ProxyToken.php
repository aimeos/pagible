<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Illuminate\Contracts\Auth\Authenticatable;


final class ProxyToken
{
    public function make( Authenticatable $user ) : string
    {
        $expires = now()->addSeconds( (int) config( 'cms.admin.proxy.ttl', 3600 ) )->timestamp;
        $payload = $expires . '|' . $user->getAuthIdentifier();
        $hmac = hash_hmac( 'sha256', $payload, (string) config( 'app.key' ) );

        return base64_encode( $payload . '|' . $hmac );
    }


    public function valid( string $token, Authenticatable $user ) : bool
    {
        $decoded = base64_decode( $token, true );
        $parts = $decoded === false ? [] : explode( '|', $decoded );

        if( count( $parts ) !== 3 ) {
            return false;
        }

        [$expires, $uid, $hmac] = $parts;
        $payload = $expires . '|' . $uid;

        return ctype_digit( $expires )
            && (int) $expires >= now()->timestamp
            && $uid === (string) $user->getAuthIdentifier()
            && hash_equals( hash_hmac( 'sha256', $payload, (string) config( 'app.key' ) ), $hmac );
    }
}
