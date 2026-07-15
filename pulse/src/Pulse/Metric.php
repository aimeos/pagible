<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Pulse;

use Aimeos\Cms\Tenancy;


final class Metric
{
    public static function type( string $type, ?string $tenant = null ) : string
    {
        $tenant ??= Tenancy::value();

        return $tenant !== '' ? $type . ':' . $tenant : $type;
    }
}
