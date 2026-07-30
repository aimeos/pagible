<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

use Aimeos\Cms\Controllers;
use Illuminate\Support\Facades\Route;

Route::match(['GET', 'HEAD'], 'cmsadminasset/{file}/{variant?}', [Controllers\AdminController::class, 'asset'])
    ->where('variant', '[0-9]+')
    ->middleware(['web', 'auth', 'throttle:cms-admin-asset'])
    ->name('cms.admin.asset');

Route::get('cmsadmin/{path?}', [Controllers\AdminController::class, 'index'])
    ->middleware(['web'])
    ->where(['path' => '.*'])
    ->name('cms.admin');

Route::match(['GET', 'HEAD', 'OPTIONS'], 'cmsproxy', [Controllers\AdminController::class, 'proxy'])
    ->middleware(config('cms.admin.proxy.middleware', ['web', 'auth', 'throttle:cms-proxy']))
    ->name('cms.proxy');
