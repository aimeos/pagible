<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Mails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;


class ContactMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var array<string, mixed> */
    public array $data;

    /** @var array<int, array{path: string, name: string, mime: string}> */
    public array $uploads;


    /**
     * @param array<string, mixed> $data
     * @param array<int, array{path: string, name: string, mime: string}> $uploads Files attached to the mail
     */
    public function __construct(array $data, array $uploads = [])
    {
        $this->data = $data;
        $this->uploads = $uploads;
    }


    public function build(): self
    {
        if( $email = $this->data['email'] ?? null )
        {
            $name = $this->data['name'] ?? null;

            // keep the configured sender address to pass SPF/DKIM/DMARC checks
            $this->from( config( 'mail.from.address' ), $name ?: $email )->replyTo( $email, $name );
        }

        foreach( $this->uploads as $upload ) {
            $this->attach( $upload['path'], ['as' => $upload['name'], 'mime' => $upload['mime']] );
        }

        return $this
            ->subject( __( 'Contact mail from :name', ['name' => config( 'app.name' )] ) )
            ->markdown( 'cms::mails.contact' );
    }
}
