<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:cms-jsonapi'])->group(function () {
    \LaravelJsonApi\Laravel\Facades\JsonApiRoute::server('cms')->prefix('cms')->resources(function ($server) {
        $server->resource('pages', \Aimeos\Cms\JsonApi\V1\Controllers\JsonapiController::class)->readOnly();
    });
});
