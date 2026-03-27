<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
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
        return $this
            ->subject( 'Contact mail from ' . config( 'app.name' ) )
            /** @phpstan-ignore argument.type */
            ->markdown( 'cms::mails.contact' );
    }
}
