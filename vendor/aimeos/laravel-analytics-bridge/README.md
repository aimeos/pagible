# Laravel Analytics Bridge

A unified analytics bridge for Laravel providing a consistent API across multiple analytics providers such as Google Analytics, Matomo, and others.

## Features

* Unified API for multiple analytics services
* Drivers for Google Analytics, Matomo, and others
* Returns time series (per day) for page views, visits, visit duration
* Aggregates top countries and referrers
* Integrates PageSpeed Insights (CrUX real-user data) for Web Vitals
* Easily extendable with new drivers

## Installation

Install the driver package you need:

```bash
# for Matomo
composer require aimeos/laravel-analytics-matomo
# for Google Analytics
composer require aimeos/laravel-analytics-google
# for Cloudflare Web Analytics
composer require aimeos/laravel-analytics-cloudflare
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=config
```

This creates the `./config/analytics-bridge.php` file:

```php
return [
    'default' => env('ANALYTICS_DRIVER'),

    'drivers' => [
        'cloudflare' => [
            'siteTag' => env('CLOUDFLARE_SITETAG'),
            'token' => env('CLOUDFLARE_TOKEN'),
        ],
        'ga4' => [
            'propertyid' => env('GOOGLE_PROPERTYID'),
            'credentials' => json_decode(base64_decode(env('GOOGLE_AUTH', '')), true),
        ],
        'matomo' => [
            'url' => env('MATOMO_URL'),
            'token' => env('MATOMO_TOKEN'),
            'siteid' => env('MATOMO_SITEID'),
        ],
    ],

    'google' => [
        'auth' => json_decode(base64_decode(env('GOOGLE_AUTH', '')), true),
        'crux' => [
            'apikey' => env('CRUX_API_KEY'),
        ]
    ]
];
```

Set your .env variables accordingly and don't forget to configure the
`ANALYTICS_DRIVER` value using the name of the driver ("cloudflare",
"google", or "matomo").

The value of `GOOGLE_AUTH` must be the string from the **Service Account JSON
key file** encoded as base64. To do the encoding on the command line, use:

```bash
php -r 'echo base64_encode(file_get_contents("name-xxxxxxxxxxxx.json"));'
```

## API Usage

Import the facade:

```php
use Aimeos\AnalyticsBridge\Facades\Analytics;
```

### Page Statistics

Available are:

* views
* visits
* conversions
* durations
* countries
* referrers

```php
$result = Analytics::stats('https://aimeos.org/features', 30);

// to limit the result set
$result = Analytics::types(['visits', 'referrers'])->stats('https://aimeos.org/features', 30);
```

It returns arrays with one entry per day, country or URL:

```php
[
    'views'     => [
        ['key' => '2025-08-01', 'value' => 123],
        ['key' => '2025-08-02', 'value' => 97],
        ...
    ],
    'visits'    => [
        ['key' => '2025-08-01', 'value' => 53],
        ['key' => '2025-08-02', 'value' => 40],
        ...
    ],
    'conversions' => [
        ['key' => '2025-08-01', 'value' => 15],
        ['key' => '2025-08-02', 'value' => 10],
        ...
    ],
    'durations' => [ // in seconds
        ['key' => '2025-08-01', 'value' => 75],
        ['key' => '2025-08-02', 'value' => 80],
        ...
    ],
    'countries' => [
        ['key' => 'Germany', 'value' => 321],
        ['key' => 'USA', 'value' => 244],
        ...
    ],
    'referrers' => [
        ['key' => 'Direct entry', 'value' => 243, 'rows' => []],
        ['key' => 'Website', 'value' => 199, 'rows' => [
            ['key' => 'https://aimeos.org/', 'value' => 321],
            ['key' => 'https://aimeos.org/Laravel', 'value' => 244],
            ...
        ]],
        ...
    ],
]
```

### PageSpeed Metrics


```php
$data = Analytics::pagespeed('https://aimeos.org/features');
```

Returns:

```php
[
    ['key' => 'round_trip_time', 'value' => 150],
    ['key' => 'time_to_first_byte', 'value' => 700],
    ['key' => 'first_contentful_paint', 'value' => 1200],
    ['key' => 'largest_contentful_paint', 'value' => 1700],
    ['key' => 'interaction_to_next_paint', 'value' => 180],
    ['key' => 'cumulative_layout_shift', 'value' => 0.05],
    /*...*/
]
```

### Google Search Statistics


```php
$data = Analytics::search('https://aimeos.org/features');
```

Returns:

```php
[
    'impressions' => [
        ['key' => '2025-08-01', 'value' => 123],
        ['key' => '2025-08-02', 'value' => 97],
        ...
    ],
    'clicks' => [
        ['key' => '2025-08-01', 'value' => 23],
        ['key' => '2025-08-02', 'value' => 14],
        ...
    ],
    'ctrs' => [ // click through rate (between 0 and 1)
        ['key' => '2025-08-01', 'value' => 0.194],
        ['key' => '2025-08-02', 'value' => 0.69],
        ...
    ],
```

### Google Search Queries


```php
$data = Analytics::queries('https://aimeos.org/features');
```

Returns:

```php
[
    ['key' => 'aimeos', 'impressions' => 1234, 'clicks' => 512, 'ctr' => 0.41, 'position' => 1.1],
    ['key' => 'laravel ecommerce', 'impressions' => 2486, 'clicks' => 299, 'ctr' => 0.11, 'position' => 1.9],
    ...
```

### Google Index Status

```php
$data = Analytics::indexed('https://aimeos.org/features', 'en-US');
```

Returns something like:

* "Indexed"
* "Not found"
* "Crawled"
* "Discovered"

## Implemnt new Driver

For a new analyics service (e.g. Foobar), create a new composer package, e.g.
`yourorg/laravel-analytics-foobar`. Replace every occurrence of "yourorg" and
"foobar" (in any case) with own vendor name and resp. the service name.

Use this `composer.json` as template:

```javascript
{
  "name": "yourorg/laravel-analytics-foobar",
  "description": "Foobar driver for Laravel Analytics Bridge",
  "type": "library",
  "license": "LGPL-2.1+",
  "autoload": {
    "psr-4": {
      "Aimeos\\AnalyticsBridge\\Drivers\\": "src/"
    }
  },
  "require": {
    "php": "^8.1",
    "aimeos/laravel-analytics-bridge": "~1.0"
  }
}
```

Create a `./src/Foobar.php` file that implements fetching the data from the
analytics service. The skeleton class is:

```php
<?php

namespace Aimeos\AnalyticsBridge\Drivers;

use Aimeos\AnalyticsBridge\Contracts\Driver;

class Foobar implements Driver
{
    private array $types = ['views', 'visits', 'durations', 'conversions', 'countries', 'referrers'];

    public function __construct(array $config = [])
    {
        // $config from ./config/analytics-bridge.php
    }

    public function stats(string $path, int $days = 30): ?array
    {
        // limit by types if requested
        return [
            'views'     => [['key' => '2025-08-01', 'value' => 123], /*...*/],
            'visits'    => [['key' => '2025-08-01', 'value' => 123], /*...*/],
            'durations' => [['key' => '2025-08-01', 'value' => 123], /*...*/],
            'countries' => [['key' => 'Germany', 'value' => 321], /*...*/],
            'referrers' => [['key' => 'https://aimeos.org/', 'value' => 321], /*...*/],
        ];
    }

    public function types(array $types): Driver
    {
        $this->types = $types;
        return $this;
    }
}
```

Install your package using composer:

```bash
composer require yourorg/laravel-analytics-foobar
```

Register your driver in `config/analytics-bridge.php`, e.g.:

```php
'drivers' => [
    'foobar' => [
        'url' => env('FOOBAR_URL'),
        // more required settings
    ]
],
```

## Optimize

The `google/apiclient` package contains classes for all Google APIs (currently
over 300) but only two (SearchConsole and WebIndex) are required. To install
only the required ones, insert this into your `composer.json` at the correct
places:

```json
    "scripts": {
        "pre-autoload-dump": [
            "Google\\Task\\Composer::cleanup"
        ],
    },
    "extra": {
        "google/apiclient-services": [
            "SearchConsole"
        ]
    },
```

When you run `composer update` afterwards, this will remove the unused services.
Take a look into the [Google client README](https://github.com/googleapis/google-api-php-client#installation)
for details.

## License

This package is released under the LGPL-2.1+ License.
