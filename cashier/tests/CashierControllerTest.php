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


    public function testCheckoutInvalidPaytype()
    {
        $response = $this->actingAs( $this->user )->postJson( route( 'cms.cashier' ), [
            'priceid' => 'price_test123',
            'paytype' => 'invalid',
        ] );

        $response->assertStatus( 422 );
        $response->assertJsonValidationErrors( 'paytype' );
    }


    public function testCheckoutMissingPaytype()
    {
        $response = $this->actingAs( $this->user )->postJson( route( 'cms.cashier' ), [
            'priceid' => 'price_test123',
        ] );

        $response->assertStatus( 422 );
        $response->assertJsonValidationErrors( 'paytype' );
    }


    public function testCheckoutMissingPriceid()
    {
        $response = $this->actingAs( $this->user )->postJson( route( 'cms.cashier' ), [
            'paytype' => 'recurring',
        ] );

        $response->assertStatus( 422 );
        $response->assertJsonValidationErrors( 'priceid' );
    }


    public function testCheckoutNoProvider()
    {
        config()->set( 'cms.cashier.provider', null );

        $response = $this->actingAs( $this->user )->postJson( route( 'cms.cashier' ), [
            'priceid' => 'price_test123',
            'paytype' => 'recurring',
        ] );

        $response->assertStatus( 500 );
    }


    public function testCheckoutPriceidTooLong()
    {
        $response = $this->actingAs( $this->user )->postJson( route( 'cms.cashier' ), [
            'priceid' => str_repeat( 'a', 256 ),
            'paytype' => 'recurring',
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
                'paytype' => 'recurring',
            ] );
        }

        $response = $this->actingAs( $this->user )->postJson( route( 'cms.cashier' ), [
            'priceid' => 'price_test123',
            'paytype' => 'recurring',
        ] );

        $response->assertStatus( 429 );
    }


    public function testCheckoutUnauthenticated()
    {
        $response = $this->postJson( route( 'cms.cashier' ), [
            'priceid' => 'price_test123',
            'paytype' => 'recurring',
        ] );

        $response->assertStatus( 401 );
    }


    public function testCheckoutUnknownProvider()
    {
        config()->set( 'cms.cashier.provider', 'unknown' );

        $response = $this->actingAs( $this->user )->postJson( route( 'cms.cashier' ), [
            'priceid' => 'price_test123',
            'paytype' => 'recurring',
        ] );

        $response->assertStatus( 500 );
    }
}
