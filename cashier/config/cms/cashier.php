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
    | Products
    |--------------------------------------------------------------------------
    |
    | Map payment provider price IDs to server-side metadata. This prevents
    | clients from manipulating metadata to gain unauthorized access.
    | The metadata is forwarded to the payment provider and available
    | in webhook events.
    |
    | 'products' => [
    |     'price_xxx' => ['once' => true, 'action' => 'course_access', 'course_id' => '123'],
    |     'price_yyy' => ['action' => 'premium'],
    | ],
    |
    */

    'products' => [],

];
