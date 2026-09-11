<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

use Illuminate\Support\Facades\Route;

Route::get( 'cms/schema', \Aimeos\Cms\Controllers\SchemaController::class )
    ->middleware( 'throttle:cms-jsonapi' )
    ->name( 'cms.schema' );

Route::middleware([
    'throttle:cms-jsonapi',
    \Aimeos\Cms\Http\Middleware\ScopeJsonapi::class,
    \Aimeos\Cms\Http\Middleware\WatchJsonapi::class,
])->group(function () {
    \LaravelJsonApi\Laravel\Facades\JsonApiRoute::server('cms')->prefix('cms')->resources(function ($server) {
        $server->resource('pages', \Aimeos\Cms\JsonApi\V1\Controllers\JsonapiController::class)->readOnly();
    });
});
