<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Controllers;

use Aimeos\Cms\Mails\ContactMail;
use Aimeos\Cms\Requests\ContactRequest;
use Illuminate\Support\Facades\Mail;
use Illuminate\Routing\Controller;


class ContactController extends Controller
{
    public function send( ContactRequest $request )
    {
        Mail::to(config('mail.from.address'))->send(
            new ContactMail( $request->validated() )
        );

        return response()->json( ['message' => 'Message sent successfully', 'status' => true] );
    }
}
