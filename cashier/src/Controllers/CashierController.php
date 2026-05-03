<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Controllers;

use Aimeos\Cms\CashierServiceProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;


class CashierController extends Controller
{
    public function checkout( Request $request ): mixed
    {
        $products = (array) config( 'cms.cashier.products', [] );

        if( $request->isMethod( 'post' ) )
        {
            $request->validate( [
                'priceid' => ['required', 'string', 'max:255', Rule::in( array_keys( $products ) )],
                'success' => ['sometimes', 'string', 'max:255', 'regex:/^\/[^\/]/'],
            ] );

            $request->session()->put( 'cms.cashier', [
                'priceid' => $request->input( 'priceid' ),
                'success' => $request->input( 'success' ),
            ] );
        }

        /** @var Authenticatable|null $user */
        $user = $request->user();

        if( !$user ) {
            return redirect()->guest( route( 'login' ) );
        }

        $checkout = (array) $request->session()->pull( 'cms.cashier', [] );
        $priceid = (string) ( $checkout['priceid'] ?? '' );
        $product = $products[$priceid] ?? null;

        if( $product === null ) {
            abort( 404, __( 'Unknown product' ) );
        }

        $successUrl = url( (string) ( $checkout['success'] ?? '/' ) );
        $provider = (string) config( 'cms.cashier.provider' );

        if( !isset( CashierServiceProvider::PROVIDERS[$provider] ) ) {
            abort( 500, __( 'Unknown payment provider' ) );
        }

        return $this->$provider( $user, $product, $priceid, $successUrl );
    }


    /**
     * @param array<string, mixed> $product
     */
    protected function mollie( Authenticatable $user, array $product, string $priceid, string $successUrl ): \Illuminate\Http\RedirectResponse
    {
        if( !empty( $product['once'] ) )
        {
            /** @phpstan-ignore method.notFound */
            $checkout = $user->checkout( $priceid, [
                'redirectUrl' => $successUrl,
                'metadata' => $product,
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


    /**
     * @param array<string, mixed> $product
     */
    protected function paddle( Authenticatable $user, array $product, string $priceid, string $successUrl ): \Illuminate\Http\RedirectResponse
    {
        /** @phpstan-ignore method.notFound */
        $checkout = $user->checkout( $priceid )
            ->customData( $product )
            ->returnTo( $successUrl );

        return new \Illuminate\Http\RedirectResponse( $checkout->url() );
    }


    /**
     * @param array<string, mixed> $product
     */
    protected function stripe( Authenticatable $user, array $product, string $priceid, string $successUrl ): \Symfony\Component\HttpFoundation\Response
    {
        $urls = [
            'success_url' => $successUrl,
            'cancel_url' => url()->previous( '/' ),
        ];

        if( !empty( $product['once'] ) )
        {
            /** @phpstan-ignore method.notFound */
            return $user->checkout( [$priceid => 1], $urls + ['metadata' => $product] );
        }

        /** @phpstan-ignore method.notFound */
        return $user->newSubscription( 'default', $priceid )
            ->checkout( $urls + ['metadata' => $product] );
    }
}
