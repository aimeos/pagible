<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
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
}
