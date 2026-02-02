<?php

namespace Aimeos\AnalyticsBridge\Contracts;

interface Driver
{
    public function stats(string $url, int $days = 30): ?array;
    public function types(array $types): Driver;
}
