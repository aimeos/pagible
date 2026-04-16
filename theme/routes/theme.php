<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

use Aimeos\Cms\Controllers;
use Illuminate\Support\Facades\Route;

Route::group(config('cms.multidomain') ? ['domain' => '{domain}'] : [], function() {
    Route::post('cmsapi/contact', [Controllers\ContactController::class, 'send'])->middleware(['web', 'throttle:cms-contact'])->name('cms.api.contact');
    Route::get('cmsapi/search', [Controllers\SearchController::class, 'index'])->middleware(['web', 'throttle:cms-search'])->name('cms.search');

    $sitemap = config('cms.theme.sitemap', 'sitemap');
    Route::get("{$sitemap}.xml", [Controllers\SitemapController::class, 'index'])->middleware(['web', 'throttle:cms-sitemap'])->name('cms.sitemap');
    Route::get("{$sitemap}-{page}.xml", [Controllers\SitemapController::class, 'chunk'])->where('page', '[0-9]+')->middleware(['web', 'throttle:cms-sitemap'])->name('cms.sitemap.chunk');

    if(config('cms.theme.pageroute', true))
    {
        Route::get('{path?}', [Controllers\PageController::class, 'index'])
            ->middleware(['web'])
            ->name('cms.page')
            ->fallback();
    }
});
