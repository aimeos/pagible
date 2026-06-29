<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Controllers;

use Aimeos\Cms\Mails\ContactMail;
use Aimeos\Cms\Requests\ContactRequest;
use Illuminate\Support\Facades\Mail;
use Illuminate\Routing\Controller;


class ContactController extends Controller
{
    public function send( ContactRequest $request ): \Illuminate\Http\JsonResponse
    {
        $start = hrtime( true );

        Mail::to(config('mail.from.address'))->send(
            new ContactMail( $request->validated() )
        );

        if( config( 'cms.theme.watch', false ) ) {
            event( new \Aimeos\Cms\Events\Contacted(
                email: (string) ( $request->validated()['email'] ?? '' ),
                ip: (string) $request->ip(),
                durationMs: ( hrtime( true ) - $start ) / 1e6,
                tenant: \Aimeos\Cms\Tenancy::value(),
            ) );
        }

        return response()->json( ['message' => 'Message sent successfully', 'status' => true] );
    }
}
