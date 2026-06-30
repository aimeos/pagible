<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Concerns\WatchAuth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Contracts\Auth\Authenticatable;
use GraphQL\Error\Error;


final class CmsLogin
{
	use WatchAuth;


	/**
	 * @param  null  $rootValue
	 * @param  array<string, mixed>  $args
	 */
	public function __invoke( $rootValue, array $args ): Authenticatable
	{
		$key = 'cms-login:' . request()->ip() . '|' . strtolower( $args['email'] );

		if( RateLimiter::tooManyAttempts( $key, 3 ) ) {
			$this->authWatch( 'login-fail', $args['email'] );
			throw new Error( "Too many login attempts" );
		}

		$guard = Auth::guard();

		if( !$guard->attempt( $args ) )
		{
			RateLimiter::hit( $key, 60 );
			$this->authWatch( 'login-fail', $args['email'] );
			throw new Error( 'Invalid credentials' );
		}

		RateLimiter::clear( $key );

		// Rotate the session ID on privilege change to prevent session fixation
		if( request()->hasSession() ) {
			request()->session()->regenerate();
		}

		$user = $guard->user() ?? throw new Error( 'Login failed' );

		$this->authWatch( 'login', $args['email'] );

		return $user;
	}
}
