<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

use Aimeos\Cms\Controllers;
use Illuminate\Support\Facades\Route;

Route::group( config( 'cms.multidomain' ) ? ['domain' => '{domain}'] : [], function() {
    Route::match( ['get', 'post'], 'cmsapi/cashier', [Controllers\CashierController::class, 'checkout'] )
        ->middleware( ['web', 'throttle:cms-cashier'] )
        ->name( 'cms.cashier' );
} );
