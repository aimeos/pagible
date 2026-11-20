<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Translation\Limit;
use Aimeos\Cms\Translation\Translator;
use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Responses\TextResponse;
use Aimeos\Cms\Tenancy;
use Illuminate\Support\Facades\RateLimiter;


class LimitTest extends TranslateTestAbstract
{
    public function testReserve()
    {
        config( ['cms.ai.ratelimit' => 2] );

        $key = 'cms-translate:' . sha1( (string) Tenancy::value() );

        RateLimiter::hit( $key, 60 );

        // the reservation of all calls exceeds the limit and is given back
        try {
            Limit::reserve( 2 );
            $this->fail( 'ThrottledException not thrown' );
        } catch( \Aimeos\Cms\Exceptions\ThrottledException $e ) {
            $this->assertSame( 1, RateLimiter::attempts( $key ) );
        }

        Limit::reserve( 1 );
        $this->assertSame( 2, RateLimiter::attempts( $key ) );

        // more calls than the limit are reserved if no other calls were made
        RateLimiter::clear( $key );
        Limit::reserve( 5 );
        $this->assertSame( 5, RateLimiter::attempts( $key ) );

        // and the exceeding calls are taken from the following minutes
        $this->travel( 61 )->seconds();
        $this->assertFalse( RateLimiter::tooManyAttempts( $key, 2 ) );

        try {
            Limit::reserve( 1 );
            $this->fail( 'ThrottledException not thrown' );
        } catch( \Aimeos\Cms\Exceptions\ThrottledException $e ) {
            $this->assertGreaterThan( 60, $e->delay );
        }

        $this->travel( 120 )->seconds();
        Limit::reserve( 1 );
        $this->assertSame( 1, RateLimiter::attempts( $key ) );
    }


    public function testTranslateLimited()
    {
        config( ['cms.ai.ratelimit' => 1] );
        Prisma::fake( [TextResponse::fromText( 'Hallo' ), TextResponse::fromText( 'Hallo' ), TextResponse::fromText( 'Hallo' )] );

        $key = 'cms-translate:' . sha1( (string) Tenancy::value() );

        // interactive translations are limited per user by the API, not by the tenant limit
        $this->assertSame( ['Hallo'], Translator::translate( ['Hello'], 'de', 'en' ) );
        $this->assertSame( 0, RateLimiter::attempts( $key ) );

        // translations of queued jobs reserve their calls
        $translate = \Aimeos\Cms\Permission::system( fn() => Translator::for( null ) );
        $this->assertNotNull( $translate );
        $this->assertSame( ['Hallo'], $translate( ['Hello'], 'de', 'en' ) );
        $this->assertSame( 1, RateLimiter::attempts( $key ) );

        $this->assertThrows( fn() => $translate( ['Hello'], 'de', 'en' ), \Aimeos\Cms\Exceptions\ThrottledException::class );
    }
}
