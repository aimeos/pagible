<?php

namespace Aimeos\AnalyticsBridge\Facades;

use Illuminate\Support\Facades\Facade;


class Analytics extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Aimeos\AnalyticsBridge\Manager::class;
    }
}
