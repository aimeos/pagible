<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Controllers;

use Aimeos\Cms\Events\CmsContact;
use Aimeos\Cms\Mails\ContactMail;
use Aimeos\Cms\Requests\ContactRequest;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Watch;
use Illuminate\Support\Facades\Mail;
use Illuminate\Routing\Controller;


class ContactController extends Controller
{
    public function send( ContactRequest $request ): \Illuminate\Http\JsonResponse
    {
        $start = Watch::start( 'cms.theme.watch', CmsContact::class );

        Mail::to(config('mail.from.address'))->send(
            new ContactMail( $request->validated() )
        );

        Watch::dispatchWhen( 'cms.theme.watch', CmsContact::class, fn() => new CmsContact(
            email: (string) ( $request->validated()['email'] ?? '' ),
            ip: (string) $request->ip(),
            durationMs: Watch::duration( $start ),
            tenant: Tenancy::value(),
        ) );

        return response()->json( ['message' => 'Message sent successfully', 'status' => true] );
    }
}
