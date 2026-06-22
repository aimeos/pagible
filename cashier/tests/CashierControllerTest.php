<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Illuminate\Support\Facades\RateLimiter;


class CashierControllerTest extends CashierTestAbstract
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = new \App\Models\User( [
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'password',
        ] );
    }


    public function testCheckoutCancelExternal()
    {
        $response = $this->actingAs( $this->billable() )->post( route( 'cms.cashier' ), [
            'priceid' => 'price_test123',
        ], ['referer' => 'https://evil.com/phishing?id=123'] );

        $response->assertRedirect( url( '/phishing?id=123' ) );
    }


    public function testCheckoutCancelPrevious()
    {
        $response = $this->actingAs( $this->billable() )->post( route( 'cms.cashier' ), [
            'priceid' => 'price_test123',
        ], ['referer' => url( '/pricing?plan=pro#compare' )] );

        $response->assertRedirect( url( '/pricing?plan=pro#compare' ) );
    }


    public function testCheckoutCancelSlashes()
    {
        $response = $this->actingAs( $this->billable() )->post( route( 'cms.cashier' ), [
            'priceid' => 'price_test123',
        ], ['referer' => url( '/' ) . '//evil.com'] );

        $response->assertRedirect( url( '/evil.com' ) );
    }


    public function testCheckoutMissingPriceid()
    {
        $response = $this->actingAs( $this->user )->postJson( route( 'cms.cashier' ) );

        $response->assertStatus( 422 );
        $response->assertJsonValidationErrors( 'priceid' );
    }


    public function testCheckoutMissingSession()
    {
        $response = $this->actingAs( $this->user )->get( route( 'cms.cashier' ) );

        $response->assertStatus( 404 );
    }


    public function testCheckoutNoProvider()
    {
        config()->set( 'cms.cashier.provider', null );

        $response = $this->actingAs( $this->user )->postJson( route( 'cms.cashier' ), [
            'priceid' => 'price_test123',
        ] );

        $response->assertStatus( 500 );
    }


    public function testCheckoutPriceidUnknown()
    {
        $response = $this->actingAs( $this->user )->postJson( route( 'cms.cashier' ), [
            'priceid' => 'price_unknown',
        ] );

        $response->assertStatus( 422 );
        $response->assertJsonValidationErrors( 'priceid' );
    }


    public function testCheckoutPriceidTooLong()
    {
        $response = $this->actingAs( $this->user )->postJson( route( 'cms.cashier' ), [
            'priceid' => str_repeat( 'a', 256 ),
        ] );

        $response->assertStatus( 422 );
        $response->assertJsonValidationErrors( 'priceid' );
    }


    public function testCheckoutThrottle()
    {
        RateLimiter::clear( 'cms-cashier' );

        for( $i = 0; $i < 10; $i++ ) {
            $this->actingAs( $this->user )->postJson( route( 'cms.cashier' ), [
                'priceid' => 'price_test123',
            ] );
        }

        $response = $this->actingAs( $this->user )->postJson( route( 'cms.cashier' ), [
            'priceid' => 'price_test123',
        ] );

        $response->assertStatus( 429 );
    }


    public function testCheckoutUnauthenticated()
    {
        $response = $this->post( route( 'cms.cashier' ), [
            'priceid' => 'price_test123',
        ] );

        $response->assertRedirect( route( 'login' ) );
        $this->assertEquals( 'price_test123', session( 'cms.cashier.priceid' ) );
    }


    public function testCheckoutUnknownProvider()
    {
        config()->set( 'cms.cashier.provider', 'unknown' );

        $response = $this->actingAs( $this->user )->postJson( route( 'cms.cashier' ), [
            'priceid' => 'price_test123',
        ] );

        $response->assertStatus( 500 );
    }


    private function billable() : \App\Models\User
    {
        return new class() extends \App\Models\User
        {
            /**
             * @param array<string, int>|string $prices
             * @param array<string, mixed> $options
             */
            public function checkout( $prices, array $options = [] ) : \Illuminate\Http\RedirectResponse
            {
                return redirect( $options['cancel_url'] );
            }
        };
    }
}
