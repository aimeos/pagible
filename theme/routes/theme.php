<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

use Aimeos\Cms\Controllers;
use Illuminate\Support\Facades\Route;

Route::group(config('cms.multidomain') ? ['domain' => '{domain}'] : [], function() {
    Route::post('cmsapi/contact', [Controllers\ContactController::class, 'send'])->middleware(['web', 'throttle:cms-contact'])->name('cms.api.contact');
    Route::get('cmsapi/search', [Controllers\SearchController::class, 'index'])->middleware(['web', 'throttle:cms-search'])->name('cms.search');

    // Issues a CSRF token (and starts the session) on demand, so cacheable pages
    // can omit the per-session token from their HTML and fetch it only when a
    // visitor actually submits a form. See theme/public/csrf.js.
    Route::get('cmsapi/csrf', fn() => response()->json(['token' => csrf_token()]))->middleware(['web', 'throttle:60,1'])->name('cms.api.csrf');

    $sitemap = config('cms.theme.sitemap', 'sitemap');
    Route::get("{$sitemap}.xml", [Controllers\SitemapController::class, 'index'])->middleware(['web', 'throttle:cms-sitemap'])->name('cms.sitemap');
    Route::get("{$sitemap}-{page}.xml", [Controllers\SitemapController::class, 'chunk'])->where('page', '[0-9]+')->middleware(['web', 'throttle:cms-sitemap'])->name('cms.sitemap.chunk');

    if(is_array($page = config('cms.theme.pageroute')))
    {
        Route::group($page, function() {
            Route::get('{path?}', [Controllers\PageController::class, 'index'])
                ->where('path', '.*')
                // ServeCachedPage short-circuits cached anonymous GETs (public, no
                // Set-Cookie) before the session/cookie middleware in the web group runs.
                ->middleware([\Aimeos\Cms\Http\Middleware\ServeCachedPage::class, 'web'])
                ->name('cms.page')
                ->fallback();
        });
    }
});
