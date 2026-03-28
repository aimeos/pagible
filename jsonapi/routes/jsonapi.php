<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:cms-jsonapi'])->group(function () {
    \LaravelJsonApi\Laravel\Facades\JsonApiRoute::server('cms')->prefix('cms')->resources(function ($server) {
        $server->resource('pages', \Aimeos\Cms\JsonApi\V1\Controllers\JsonapiController::class)->readOnly();
    });
});
