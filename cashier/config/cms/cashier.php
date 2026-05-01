<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Provider
    |--------------------------------------------------------------------------
    |
    | The active payment provider: 'stripe', 'paddle', or 'mollie'.
    | Each provider requires its own Cashier package to be installed.
    |
    */

    'provider' => env( 'CMS_CASHIER_PROVIDER' ),

    /*
    |--------------------------------------------------------------------------
    | Redirect URLs
    |--------------------------------------------------------------------------
    |
    | Where to redirect users after a successful or canceled checkout.
    |
    */

    'success_url' => env( 'CMS_CASHIER_SUCCESS_URL', '/' ),
    'cancel_url' => env( 'CMS_CASHIER_CANCEL_URL', '/' ),

];
