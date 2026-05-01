<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Controllers;

use Aimeos\Cms\CashierServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;


class CashierController extends Controller
{
    public function checkout( Request $request ): mixed
    {
        $request->validate( [
            'paytype' => 'required|string|in:onetime,recurring',
            'priceid' => 'required|string|max:255',
        ] );

        $provider = (string) config( 'cms.cashier.provider' );

        if( !isset( CashierServiceProvider::PROVIDERS[$provider] ) ) {
            abort( 500, __( 'Unknown payment provider' ) );
        }

        if( !class_exists( CashierServiceProvider::PROVIDERS[$provider][1] . '\CashierServiceProvider' ) ) {
            abort( 500, __( ucfirst( $provider ) . ' Cashier package is not installed' ) );
        }

        return match( $provider ) {
            'stripe' => $this->stripe( $request ),
            'paddle' => $this->paddle( $request ),
            'mollie' => $this->mollie( $request ),
        };
    }


    protected function mollie( Request $request ): \Illuminate\Http\RedirectResponse
    {
        /** @var \Illuminate\Foundation\Auth\User $user */
        $user = $request->user();
        $priceid = (string) $request->input( 'priceid' );

        if( $request->input( 'paytype' ) === 'onetime' )
        {
            /** @phpstan-ignore method.notFound */
            $checkout = $user->checkout( $priceid, [
                'redirectUrl' => url( (string) config( 'cms.cashier.success_url', '/' ) ),
            ] );
        }
        else
        {
            /** @phpstan-ignore method.notFound */
            $checkout = $user->newSubscriptionViaMollieCheckout( 'default', $priceid )
                ->create();
        }

        return $checkout->redirect();
    }


    protected function paddle( Request $request ): \Illuminate\Http\RedirectResponse
    {
        /** @var \Illuminate\Foundation\Auth\User $user */
        $user = $request->user();

        /** @phpstan-ignore method.notFound */
        $checkout = $user->checkout( (string) $request->input( 'priceid' ) )
            ->returnTo( url( (string) config( 'cms.cashier.success_url', '/' ) ) );

        return new \Illuminate\Http\RedirectResponse( $checkout->url() );
    }


    protected function stripe( Request $request ): \Symfony\Component\HttpFoundation\Response
    {
        /** @var \Illuminate\Foundation\Auth\User $user */
        $user = $request->user();
        $priceid = (string) $request->input( 'priceid' );

        $urls = [
            'success_url' => url( (string) config( 'cms.cashier.success_url', '/' ) ),
            'cancel_url' => url( (string) config( 'cms.cashier.cancel_url', '/' ) ),
        ];

        if( $request->input( 'paytype' ) === 'onetime' )
        {
            /** @phpstan-ignore method.notFound */
            return $user->checkout( [$priceid => 1], $urls );
        }

        /** @phpstan-ignore method.notFound */
        return $user->newSubscription( 'default', $priceid )
            ->checkout( $urls );
    }
}
