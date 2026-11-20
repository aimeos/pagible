<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

use Aimeos\Cms\Controllers\AssetController;
use Aimeos\Cms\Utils;
use Illuminate\Support\Facades\Route;


Route::group( Utils::routeGroup(), function() {
    Route::match( ['GET', 'HEAD'], 'cmsasset/{page}/{file}/{variant?}', [AssetController::class, 'show'] )
        ->where( 'variant', '[0-9]+' )
        ->middleware( ['web', 'throttle:cms-asset'] )
        ->name( 'cms.asset' );
} );
