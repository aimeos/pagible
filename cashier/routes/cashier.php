<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

use Aimeos\Cms\Controllers;
use Aimeos\Cms\Utils;
use Illuminate\Support\Facades\Route;

Route::group( Utils::routeGroup(), function() {
    Route::match( ['get', 'post'], 'cmsapi/cashier', [Controllers\CashierController::class, 'checkout'] )
        ->middleware( ['web', 'throttle:cms-cashier'] )
        ->name( 'cms.cashier' );

    Route::delete( 'cmsapi/cashier/{subscription}', [Controllers\CashierController::class, 'cancel'] )
        ->where( 'subscription', '[A-Za-z0-9_\\-]+' )
        ->middleware( ['web', 'auth', 'throttle:cms-cashier'] )
        ->name( 'cms.cashier.cancel' );
} );
