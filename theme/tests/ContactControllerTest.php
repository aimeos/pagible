<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\Cms\Mails\ContactMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;


class ContactControllerTest extends ThemeTestAbstract
{
    protected function defineEnvironment( $app )
    {
        parent::defineEnvironment( $app );

        $app['config']->set( 'mail.from.address', 'test@example.com' );
    }


    public function testSendSuccess()
    {
        Mail::fake();

        $response = $this->post( route( 'cms.api.contact' ), [
            'name' => 'Test User',
            'email' => 'sender@google.com',
            'message' => 'Hello, this is a test message.',
        ] );

        $response->assertStatus( 200 );
        $response->assertJson( ['message' => 'Message sent successfully', 'status' => true] );

        Mail::assertSent( ContactMail::class, function( $mail ) {
            return $mail->hasTo( 'test@example.com' );
        } );
    }


    public function testSendMissingName()
    {
        Mail::fake();

        $response = $this->postJson( route( 'cms.api.contact' ), [
            'email' => 'sender@google.com',
            'message' => 'Hello.',
        ] );

        $response->assertStatus( 422 );
        $response->assertJsonValidationErrors( 'name' );
        Mail::assertNothingSent();
    }


    public function testSendInvalidEmail()
    {
        Mail::fake();

        $response = $this->postJson( route( 'cms.api.contact' ), [
            'name' => 'Test User',
            'email' => 'not-an-email',
            'message' => 'Hello.',
        ] );

        $response->assertStatus( 422 );
        $response->assertJsonValidationErrors( 'email' );
        Mail::assertNothingSent();
    }


    public function testSendMissingMessage()
    {
        Mail::fake();

        $response = $this->postJson( route( 'cms.api.contact' ), [
            'name' => 'Test User',
            'email' => 'sender@google.com',
        ] );

        $response->assertStatus( 422 );
        $response->assertJsonValidationErrors( 'message' );
        Mail::assertNothingSent();
    }


    public function testSendMessageTooLong()
    {
        Mail::fake();

        $response = $this->postJson( route( 'cms.api.contact' ), [
            'name' => 'Test User',
            'email' => 'sender@google.com',
            'message' => str_repeat( 'a', 5001 ),
        ] );

        $response->assertStatus( 422 );
        $response->assertJsonValidationErrors( 'message' );
        Mail::assertNothingSent();
    }


    public function testSendMissingAllFields()
    {
        Mail::fake();

        $response = $this->postJson( route( 'cms.api.contact' ), [] );

        $response->assertStatus( 422 );
        $response->assertJsonValidationErrors( ['name', 'email', 'message'] );
        Mail::assertNothingSent();
    }


    public function testSendThrottle()
    {
        Mail::fake();
        RateLimiter::clear( 'cms-contact' );

        $data = [
            'name' => 'Test User',
            'email' => 'sender@google.com',
            'message' => 'Hello, this is a test message.',
        ];

        for( $i = 0; $i < 2; $i++ ) {
            $this->post( route( 'cms.api.contact' ), $data )->assertStatus( 200 );
        }

        $this->post( route( 'cms.api.contact' ), $data )->assertStatus( 429 );
    }
}
