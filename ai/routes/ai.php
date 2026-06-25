<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

use Aimeos\Cms\Controllers\ChatController;
use Illuminate\Support\Facades\Route;


// Streams return chat answers as a chunked text response. Uses the cookie/session "web" guard
// like the GraphQL endpoint; the controller enforces the page:chat permission itself. The
// middleware is config-driven (cms.ai.middleware) so multi-tenant setups (e.g. stancl/tenancy) can
// add their tenancy-init middleware, ensuring Tenancy::value() resolves to the right tenant for the
// AI tool calls instead of '' (which fails closed on reads but would mis-tenant a created page).
Route::post( 'cmsapi/chat', [ChatController::class, 'stream'] )
    ->middleware( config( 'cms.ai.middleware', ['web', 'throttle:cms-ai'] ) )
    ->name( 'cms.chat' );
