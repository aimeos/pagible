<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Events\Authed;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Watch;
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
		$watchAuth = fn( string $action ) => Watch::dispatch( fn() => new Authed(
			$action,
			$email,
			(string) request()->ip(),
			(string) request()->userAgent(),
			Tenancy::value()
		) );

		if( RateLimiter::tooManyAttempts( $key, 3 ) ) {
			$watchAuth( 'login-fail' );
			throw new Error( "Too many login attempts" );
		}

		$guard = Auth::guard();

		if( !$guard->attempt( $args ) )
		{
			RateLimiter::hit( $key, 60 );
			$watchAuth( 'login-fail' );
			throw new Error( 'Invalid credentials' );
		}

		RateLimiter::clear( $key );

		// Rotate the session ID on privilege change to prevent session fixation
		if( request()->hasSession() ) {
			request()->session()->regenerate();
		}

		$user = $guard->user() ?? throw new Error( 'Login failed' );

		$watchAuth( 'login' );

		return $user;
	}
}
