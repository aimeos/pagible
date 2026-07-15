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
        $start = hrtime( true );
        $data = $request->validated();

        Mail::to(config('mail.from.address'))->send(
            new ContactMail( $data )
        );

        $duration = Watch::duration( $start );
        $ip = (string) $request->ip();
        $tenant = Tenancy::value();

        Watch::dispatchWhen( 'cms.theme.watch', CmsContact::class, fn() => new CmsContact(
            email: (string) ( $data['email'] ?? '' ),
            ip: $ip,
            durationMs: $duration,
            tenant: $tenant,
        ) );

        Watch::observe(
            source: 'contact',
            action: 'theme:contact',
            durationMs: $duration,
            tenant: $tenant,
        );

        return response()->json( ['message' => 'Message sent successfully', 'status' => true] );
    }
}
