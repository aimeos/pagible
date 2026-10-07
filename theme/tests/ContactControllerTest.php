<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Mails\ContactMail;
use Aimeos\Cms\Requests\ContactRequest;
use Illuminate\Http\UploadedFile;
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
            $html = $mail->render();

            return $mail->hasTo( 'test@example.com' )
                && $mail->hasFrom( 'test@example.com', 'Test User' )
                && $mail->hasReplyTo( 'sender@google.com', 'Test User' )
                && $mail->data['source'] === $source
                && preg_match( '#Test User</p>\s*<p[^>]*><strong[^>]*>E-Mail:</strong> sender@google.com</p>#', $html );
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


    public function testSendErrorsUsePageLanguage()
    {
        Mail::fake();

        try {
            $response = $this->postJson( route( 'cms.api.contact' ), [
                'email' => 'invalid',
                'message' => 'Hallo.',
                'locale' => 'de-AT',
            ] );
        } finally {
            app( 'translator' )->setLocale( 'en' );
        }

        $response->assertStatus( 422 );
        $response->assertJsonPath( 'errors.name.0', 'Name: Dieses Feld ist erforderlich.' );
        $response->assertJsonPath( 'errors.email.0', 'E-Mail: Ungültige E-Mail-Adresse.' );
        $this->assertEquals( 'en', config( 'app.locale' ) );
        Mail::assertNothingSent();
    }


    public function testSendMailUsesSiteLanguage()
    {
        Mail::fake();

        try {
            $response = $this->post( route( 'cms.api.contact' ), [
                'name' => 'Test User',
                'email' => 'sender@google.com',
                'message' => 'Bonjour.',
                'locale' => 'fr',
            ] );
        } finally {
            app( 'translator' )->setLocale( 'en' );
        }

        $response->assertStatus( 200 );

        Mail::assertSent( ContactMail::class, function( $mail ) {
            $html = $mail->render();

            return $mail->locale === 'en'
                && $mail->subject === 'Contact mail from ' . config( 'app.name' )
                && str_contains( $html, 'Contact message' );
        } );
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


    public function testSendAttachments()
    {
        Mail::fake();
        $schema = ContactRequest::schema( ['name', 'email'], [], [], 2 );

        $response = $this->post( route( 'cms.api.contact' ), [
            'schema' => $schema,
            'signature' => ContactRequest::signature( $schema ),
            'name' => 'Test User',
            'email' => 'sender@google.com',
            'message' => 'Please see the photos.',
            'files' => [
                UploadedFile::fake()->image( '../Roof: damage.jpg', 20, 20 ),
                UploadedFile::fake()->create( 'plan.pdf', 100, 'application/pdf' ),
            ],
        ] );

        $response->assertOk();

        Mail::assertSent( ContactMail::class, function( $mail ) {
            $mail->build();

            return array_column( $mail->uploads, 'name' ) === ['Roof_ damage.jpg', 'plan.pdf']
                && array_column( $mail->uploads, 'mime' ) === ['image/jpeg', 'application/pdf']
                && count( $mail->attachments ) === 2
                && $mail->attachments[0]['options'] === ['as' => 'Roof_ damage.jpg', 'mime' => 'image/jpeg'];
        } );
    }


    public function testSendAttachmentsNotAllowed()
    {
        Mail::fake();

        $response = $this->postJson( route( 'cms.api.contact' ), [
            'name' => 'Test User',
            'email' => 'sender@google.com',
            'message' => 'Hello.',
            'files' => [UploadedFile::fake()->create( 'plan.pdf', 100, 'application/pdf' )],
        ] );

        $response->assertStatus( 422 );
        $response->assertJsonValidationErrors( 'files' );
        Mail::assertNothingSent();
    }


    public function testSendTooManyAttachments()
    {
        Mail::fake();
        $schema = ContactRequest::schema( [], [], [], 1 );

        $response = $this->postJson( route( 'cms.api.contact' ), [
            'schema' => $schema,
            'signature' => ContactRequest::signature( $schema ),
            'message' => 'Hello.',
            'files' => [
                UploadedFile::fake()->create( 'a.pdf', 100, 'application/pdf' ),
                UploadedFile::fake()->create( 'b.pdf', 100, 'application/pdf' ),
            ],
        ] );

        $response->assertStatus( 422 );
        $response->assertJsonPath( 'errors.files.0', 'Attachments: Too many files.' );
        Mail::assertNothingSent();
    }


    public function testSendAttachmentsTooLarge()
    {
        Mail::fake();
        $schema = ContactRequest::schema( [], [], [], 3 );

        $response = $this->postJson( route( 'cms.api.contact' ), [
            'schema' => $schema,
            'signature' => ContactRequest::signature( $schema ),
            'message' => 'Hello.',
            'files' => [
                UploadedFile::fake()->create( 'a.pdf', 9 * 1024, 'application/pdf' ),
                UploadedFile::fake()->create( 'b.pdf', 9 * 1024, 'application/pdf' ),
                UploadedFile::fake()->create( 'c.pdf', 9 * 1024, 'application/pdf' ),
            ],
        ] );

        $response->assertStatus( 422 );
        $response->assertJsonPath( 'errors.files.0', 'Attachments: The files are too large.' );
        Mail::assertNothingSent();
    }


    public function testSendAttachmentInvalid()
    {
        Mail::fake();
        config( ['cms.upload.maxpixels' => 100] );
        $schema = ContactRequest::schema( [], [], [], 3 );

        $response = $this->postJson( route( 'cms.api.contact' ), [
            'schema' => $schema,
            'signature' => ContactRequest::signature( $schema ),
            'message' => 'Hello.',
            'files' => [
                UploadedFile::fake()->create( 'script.php', 1, 'text/x-php' ),
                UploadedFile::fake()->create( 'big.pdf', 11 * 1024, 'application/pdf' ),
                UploadedFile::fake()->image( 'photo.png', 20, 20 ),
            ],
        ] );

        $response->assertStatus( 422 );
        $response->assertJsonPath( 'errors', [
            'files.0' => ['Attachments: The file type is not allowed.'],
            'files.1' => ['Attachments: The file is too large.'],
            'files.2' => ['Attachments: The file is too large.'],
        ] );
        Mail::assertNothingSent();
    }


    public function testSendSelectAndTextareaFields()
    {
        Mail::fake();
        $schema = ContactRequest::schema( ['Service'], ['Details'], ['Service' => ['Repair', 'Installation'], 'Details' => 'textarea'] );
        $data = [
            'schema' => $schema,
            'signature' => ContactRequest::signature( $schema ),
            ContactRequest::key( 'Service' ) => 'Demolition',
            ContactRequest::key( 'Details' ) => str_repeat( 'a', 1000 ),
            'message' => 'Hello.',
        ];

        $this->postJson( route( 'cms.api.contact' ), $data )
            ->assertStatus( 422 )
            ->assertJsonValidationErrors( ContactRequest::key( 'Service' ) );

        $data[ContactRequest::key( 'Service' )] = 'Repair';

        $this->postJson( route( 'cms.api.contact' ), $data )->assertOk();

        Mail::assertSent( ContactMail::class, fn( $mail ) => $mail->data['fields'] === [
            ['name' => 'Service', 'value' => 'Repair', 'required' => true],
            ['name' => 'Details', 'value' => str_repeat( 'a', 1000 ), 'required' => false],
        ] );
    }


    public function testSchemaTypesAndFiles()
    {
        $schema = ContactRequest::schema( ['email', 'Service'], ['0'], [
            'email' => 'textarea',
            'Service' => ['Repair', '', "In\nvalid", 'Repair'],
            '0' => 'textarea',
            'Unknown' => 'textarea',
        ], 9 );

        $this->assertSame(
            '{"mandatory":["email","Service"],"optional":["0"],"types":{"Service":["Repair"],"0":"textarea"},"files":5}',
            $schema
        );
        $this->assertSame( '{"mandatory":["name","email"],"optional":[]}', ContactRequest::schema() );
    }


    public function testSendTamperedFileCount()
    {
        Mail::fake();
        $schema = ContactRequest::schema( [], [], [], 1 );

        $response = $this->postJson( route( 'cms.api.contact' ), [
            'schema' => str_replace( '"files":1', '"files":5', $schema ),
            'signature' => ContactRequest::signature( $schema ),
            'message' => 'Hello.',
        ] );

        $response->assertStatus( 422 );
        $response->assertJsonValidationErrors( 'schema' );
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
