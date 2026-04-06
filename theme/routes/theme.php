<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

use Aimeos\Cms\Controllers;
use Illuminate\Support\Facades\Route;

Route::post('cmsapi/contact', [Controllers\ContactController::class, 'send'])
    ->middleware(['web', 'throttle:cms-contact'])
    ->name('cms.api.contact');

Route::group(config('cms.multidomain') ? ['domain' => '{domain}'] : [], function() {
    Route::get('cmsapi/search', [Controllers\SearchController::class, 'index'])->middleware(['throttle:cms-search'])->name('cms.search');
    Route::get('cms-sitemap.xml', [Controllers\SitemapController::class, 'index'])->middleware(['throttle:cms-sitemap'])->name('cms.sitemap');
});

if(config('cms.theme.pageroute', true))
{
    Route::group(config('cms.multidomain') ? ['domain' => '{domain}'] : [], function() {
        Route::get('{path?}', [Controllers\PageController::class, 'index'])
            ->middleware(['web'])
            ->name('cms.page');
    });
}
