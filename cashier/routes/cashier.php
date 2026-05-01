<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

use Aimeos\Cms\Controllers;
use Illuminate\Support\Facades\Route;

Route::group( config( 'cms.multidomain' ) ? ['domain' => '{domain}'] : [], function() {
    Route::post( 'cmsapi/cashier', [Controllers\CashierController::class, 'checkout'] )
        ->middleware( ['web', 'auth', 'throttle:cms-cashier'] )
        ->name( 'cms.cashier' );
} );
