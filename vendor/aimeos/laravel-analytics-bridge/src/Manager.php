<?php

namespace Aimeos\AnalyticsBridge;

use Aimeos\AnalyticsBridge\Contracts\Driver;


class Manager implements Driver
{
    private Driver $driver;
    private Google $google;


    public function google(array $config = []): Google
    {
        if (!isset($this->google))
        {
            $config = array_replace(config('analytics-bridge.google', []), $config);
            $this->google = new Google($config);
        }

        return $this->google;
    }


    public function driver(?string $name = null, array $config = []): Driver
    {
        if (!isset($this->driver))
        {
            $name ??= config('analytics-bridge.default');
            $class = '\\Aimeos\\AnalyticsBridge\\Drivers\\' . ucfirst($name);

            if (!class_exists($class)) {
                throw new \InvalidArgumentException("Driver [$name] not found");
            }

            $config = array_replace(config('analytics-bridge.drivers.' . $name, []), $config);
            $this->driver = new $class($config);
        }

        return $this->driver;
    }


    public function stats(string $url, int $days = 30): ?array
    {
        return $this->driver()->stats($url, $days);
    }


    public function types(array $types): Driver
    {
        return $this->driver()->types($types);
    }


    public function pagespeed(string $url): ?array
    {
        return $this->google()->pagespeed($url);
    }


    public function indexed(string $url, string $lang = 'en'): ?array
    {
        return $this->google()->indexed($url, $lang);
    }


    public function search(string $url, int $days = 30): ?array
    {
        return $this->google()->search($url, $days);
    }


    public function queries(string $url, int $days = 30): ?array
    {
        return $this->google()->queries($url, $days);
    }
}
