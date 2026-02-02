<?php

namespace Aimeos\AnalyticsBridge\Drivers;

use Aimeos\AnalyticsBridge\Contracts\Driver;


class None implements Driver
{
    public function stats(string $url, int $days = 30): ?array
    {
        return null;
    }


    public function types(array $types): Driver
    {
        return $this;
    }
}
