<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

use Aimeos\Cms\Controllers\ChatController;
use Illuminate\Support\Facades\Route;


// chat answers are streamed, the middleware is configurable by "cms.ai.middleware", e.g. for tenancy
Route::post( 'cmsapi/chat', [ChatController::class, 'stream'] )
    ->middleware( config( 'cms.ai.middleware' ) )
    ->name( 'cms.chat' );
