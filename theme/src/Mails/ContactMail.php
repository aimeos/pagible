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


    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }


    public function build(): self
    {
        if( $email = $this->data['email'] ?? null )
        {
            $name = $this->data['name'] ?? null;

            // keep the configured sender address to pass SPF/DKIM/DMARC checks
            $this->from( config( 'mail.from.address' ), $name ?: $email )->replyTo( $email, $name );
        }

        return $this
            ->subject( __( 'Contact mail from :name', ['name' => config( 'app.name' )] ) )
            ->markdown( 'cms::mails.contact' );
    }
}
