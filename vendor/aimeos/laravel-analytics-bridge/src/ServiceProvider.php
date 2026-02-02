<?php

namespace Aimeos\AnalyticsBridge;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;


class ServiceProvider extends BaseServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/analytics-bridge.php' => config_path('analytics-bridge.php'),
        ], 'config');
    }
}
