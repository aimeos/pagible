<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Resolvers;

use Illuminate\Foundation\Auth\User;


class UserResolver
{
    /**
     * @param array<string, mixed> $args
     * @param mixed $context
     * @return array<string, mixed>
     */
    public function permission( User $user, array $args, mixed $context ): array
    {
        return \Aimeos\Cms\Permission::get( $user );
    }


    /**
     * @param array<string, mixed> $args
     * @param mixed $context
     * @return array<int, string>
     */
    public function roles( User $user, array $args, mixed $context ): array
    {
        return array_values( array_filter( $user->cmsperms ?? [], fn( $entry ) => !str_contains( $entry, ':' ) ) );
    }


    /**
     * @param array<string, mixed> $args
     * @param mixed $context
     * @return array<string, mixed>|null
     */
    public function settings( User $user, array $args, mixed $context ): array|null
    {
        return json_decode( $user->cmsdata ?? '', true ) ?: null;
    }


    /**
     * @param array<string, mixed> $args
     * @param mixed $context
     */
    public function token( User $user, array $args, mixed $context ): string
    {
        // Short-lived, user-bound capability token for the media proxy. Kept brief
        // because it travels in the proxy URL query string; the admin client
        // refreshes it before expiry (see stores.js applyProxyToken).
        $ttl = (int) config( 'cms.admin.proxy.ttl', 3600 );
        $expires = now()->addSeconds( $ttl )->timestamp;
        $payload = $expires . '|' . $user->getAuthIdentifier();

        return base64_encode( $payload . '|' . hash_hmac( 'sha256', $payload, config( 'app.key' ) ) );
    }
}
