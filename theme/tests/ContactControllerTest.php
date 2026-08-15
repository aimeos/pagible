<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Mails\ContactMail;
use Aimeos\Cms\Requests\ContactRequest;
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
        $source = url( '/properties/test' );

        $response = $this->post( route( 'cms.api.contact' ), [
            'name' => 'Test User',
            'email' => 'sender@google.com',
            'message' => 'Hello, this is a test message.',
            'source' => $source,
        ] );

        $response->assertStatus( 200 );
        $response->assertJson( ['message' => 'Message sent successfully', 'status' => true] );

        Mail::assertSent( ContactMail::class, function( $mail ) use ( $source ) {
            return $mail->hasTo( 'test@example.com' )
                && $mail->data['source'] === $source;
        } );
    }


    public function testSendConfiguredAndCustomFields()
    {
        Mail::fake();
        $schema = ContactRequest::schema(
            ['company', 'email', 'subject'],
            ['telephone', 'Customer number']
        );

        $response = $this->postJson( route( 'cms.api.contact' ), [
            'schema' => $schema,
            'signature' => ContactRequest::signature( $schema ),
            'company' => 'Example Ltd.',
            'telephone' => '+49 123 456789',
            'email' => 'sender@google.com',
            'subject' => 'Product question',
            ContactRequest::key( 'Customer number' ) => 'C-123',
            'ignored' => 'Must not be mailed',
            'message' => 'Hello.',
        ] );

        $response->assertOk();

        Mail::assertSent( ContactMail::class, function( $mail ) {
            return $mail->data['fields'] === [
                ['name' => 'company', 'value' => 'Example Ltd.', 'required' => true],
                ['name' => 'email', 'value' => 'sender@google.com', 'required' => true],
                ['name' => 'subject', 'value' => 'Product question', 'required' => true],
                ['name' => 'telephone', 'value' => '+49 123 456789', 'required' => false],
                ['name' => 'Customer number', 'value' => 'C-123', 'required' => false],
            ]
                && !array_key_exists( 'ignored', $mail->data );
        } );
    }


    public function testSendOptionalFieldsCanBeEmpty()
    {
        Mail::fake();
        $schema = ContactRequest::schema( [], ['company', 'Customer number'] );

        $response = $this->postJson( route( 'cms.api.contact' ), [
            'schema' => $schema,
            'signature' => ContactRequest::signature( $schema ),
            'message' => 'Hello.',
        ] );

        $response->assertOk();

        Mail::assertSent( ContactMail::class, fn( $mail ) => $mail->data['fields'] === [
            ['name' => 'company', 'value' => null, 'required' => false],
            ['name' => 'Customer number', 'value' => null, 'required' => false],
        ] );
    }


    public function testSendOptionalEmailMustBeValidWhenPresent()
    {
        Mail::fake();
        $schema = ContactRequest::schema( [], ['email'] );

        $response = $this->postJson( route( 'cms.api.contact' ), [
            'schema' => $schema,
            'signature' => ContactRequest::signature( $schema ),
            'email' => 'not-an-email',
            'message' => 'Hello.',
        ] );

        $response->assertStatus( 422 );
        $response->assertJsonValidationErrors( 'email' );
        Mail::assertNothingSent();
    }


    public function testMandatoryFieldsTakePrecedenceOverOptionalFields()
    {
        $schema = ContactRequest::schema( ['email'], ['email', 'subject'] );

        $this->assertSame( [
            'mandatory' => ['email'],
            'optional' => ['subject'],
        ], json_decode( $schema, true ) );
    }


    public function testSendMissingConfiguredField()
    {
        Mail::fake();
        $schema = ContactRequest::schema( ['company', 'Customer number'], [] );

        $response = $this->postJson( route( 'cms.api.contact' ), [
            'schema' => $schema,
            'signature' => ContactRequest::signature( $schema ),
            'company' => 'Example Ltd.',
            'message' => 'Hello.',
        ] );

        $response->assertStatus( 422 );
        $response->assertJsonValidationErrors( ContactRequest::key( 'Customer number' ) );
        Mail::assertNothingSent();
    }


    public function testSendNonStringConfiguredField()
    {
        Mail::fake();
        $schema = ContactRequest::schema( ['company'], [] );

        $response = $this->postJson( route( 'cms.api.contact' ), [
            'schema' => $schema,
            'signature' => ContactRequest::signature( $schema ),
            'company' => ['Example Ltd.'],
            'message' => 'Hello.',
        ] );

        $response->assertStatus( 422 );
        $response->assertJsonValidationErrors( 'company' );
        Mail::assertNothingSent();
    }


    public function testSendTamperedFieldSchema()
    {
        Mail::fake();
        $schema = ContactRequest::schema( ['name'], [] );

        $response = $this->postJson( route( 'cms.api.contact' ), [
            'schema' => ContactRequest::schema( ['subject'], [] ),
            'signature' => ContactRequest::signature( $schema ),
            'subject' => 'Product question',
            'message' => 'Hello.',
        ] );

        $response->assertStatus( 422 );
        $response->assertJsonValidationErrors( 'schema' );
        Mail::assertNothingSent();
    }


    public function testSendInvalidCustomFieldSchema()
    {
        Mail::fake();
        $schema = '{"mandatory":["customer\\nnumber"],"optional":[]}';

        $response = $this->postJson( route( 'cms.api.contact' ), [
            'schema' => $schema,
            'signature' => ContactRequest::signature( $schema ),
            'message' => 'Hello.',
        ] );

        $response->assertStatus( 422 );
        $response->assertJsonValidationErrors( 'schema' );
        Mail::assertNothingSent();
    }


    public function testSendExternalSource()
    {
        Mail::fake();

        $response = $this->postJson( route( 'cms.api.contact' ), [
            'name' => 'Test User',
            'email' => 'sender@google.com',
            'message' => 'Hello.',
            'source' => 'https://external.example/properties/test',
        ] );

        $response->assertStatus( 422 );
        $response->assertJsonValidationErrors( 'source' );
        Mail::assertNothingSent();
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
