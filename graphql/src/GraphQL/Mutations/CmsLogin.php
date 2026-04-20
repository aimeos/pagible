<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Contracts\Auth\Authenticatable;
use GraphQL\Error\Error;


final class CmsLogin
{
	/**
	 * @param  null  $rootValue
	 * @param  array<string, mixed>  $args
	 */
	public function __invoke( $rootValue, array $args ): Authenticatable
	{
		$key = 'cms-login:' . request()->ip() . '|' . strtolower( $args['email'] );

		if( RateLimiter::tooManyAttempts( $key, 3 ) ) {
			throw new Error( "Too many login attempts" );
		}

		$guard = Auth::guard();

		if( !$guard->attempt( $args ) )
		{
			RateLimiter::hit( $key, 60 );
			throw new Error( 'Invalid credentials' );
		}

		RateLimiter::clear( $key );

		$user = $guard->user() ?? throw new Error( 'Login failed' );

		$expires = now()->addDay();
		Cookie::queue( 'cms_proxy', encrypt( $user->getAuthIdentifier() . '|' . $expires->timestamp ), $expires->diffInMinutes() );

		return $user;
	}
}
