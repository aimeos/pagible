<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Events\Authed;
use Illuminate\Support\Facades\Auth;
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
		$email = (string) $args['email'];
		$key = 'cms-login:' . request()->ip() . '|' . strtolower( $email );

		if( RateLimiter::tooManyAttempts( $key, 3 ) ) {
			Authed::fire( 'login-fail', $email );
			throw new Error( "Too many login attempts" );
		}

		$guard = Auth::guard();

		if( !$guard->attempt( $args ) )
		{
			RateLimiter::hit( $key, 60 );
			Authed::fire( 'login-fail', $email );
			throw new Error( 'Invalid credentials' );
		}

		RateLimiter::clear( $key );

		// Rotate the session ID on privilege change to prevent session fixation
		if( request()->hasSession() ) {
			request()->session()->regenerate();
		}

		$user = $guard->user() ?? throw new Error( 'Login failed' );

		Authed::fire( 'login', $email );

		return $user;
	}
}
