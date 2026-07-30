<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

use Aimeos\Cms\Controllers\AssetController;
use Illuminate\Support\Facades\Route;


$options = config( 'cms.multidomain' ) ? ['domain' => '{domain}'] : [];

Route::group( $options, function() {
    Route::match( ['GET', 'HEAD'], 'cmsasset/{page}/{file}/{variant?}', [AssetController::class, 'show'] )
        ->where( 'variant', '[0-9]+' )
        ->middleware( ['web', 'throttle:cms-asset'] )
        ->name( 'cms.asset' );
} );
