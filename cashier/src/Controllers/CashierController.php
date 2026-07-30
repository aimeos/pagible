<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms\Controllers;

use Aimeos\Cms\CashierProduct;
use Aimeos\Cms\CashierProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;


class CashierController extends Controller
{
    /**
     * Schedules cancellation at the paid-through period end.
     */
    public function cancel( Request $request, CashierProvider $provider, string $subscription ): \Illuminate\Http\Response
    {
        /** @var Authenticatable $user */
        $user = $request->user();

        $provider->cancel( $user, $subscription );

        return response()->noContent();
    }


    /**
     * Starts checkout for a published pricing-content price.
     */
    public function checkout( Request $request, CashierProduct $products, CashierProvider $provider ): mixed
    {
        $checkout = [];

        if( $request->isMethod( 'post' ) )
        {
            $checkout = $request->validate( [
                'page' => ['required', 'string', 'max:36'],
                'element' => ['required', 'string', 'max:255'],
                'package' => ['required', 'string', 'max:100'],
                'price' => ['required', 'string', 'max:100'],
            ] );
        }

        /** @var Authenticatable|null $user */
        $user = $request->user();

        if( !$user )
        {
            if( $checkout !== [] ) {
                $request->session()->put( 'cms.cashier', $checkout );
            }

            return redirect()->guest( route( 'login' ) );
        }

        if( $checkout === [] ) {
            $checkout = (array) $request->session()->pull( 'cms.cashier', [] );
        }

        if( $checkout === [] ) {
            abort( 404, __( 'Unknown product' ) );
        }

        $product = $products->find(
            $user,
            (string) ( $checkout['page'] ?? '' ),
            (string) ( $checkout['element'] ?? '' ),
            (string) ( $checkout['package'] ?? '' ),
            (string) ( $checkout['price'] ?? '' ),
        );

        return $provider->checkout( $user, $product );
    }
}
