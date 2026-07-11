<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Pulse;

use Aimeos\Cms\Tenancy;


final class Metric
{
    /**
     * @var list<'count'|'avg'|'max'>
     */
    public const LATENCY = ['count', 'avg', 'max'];


    public static function type( string $type, ?string $tenant = null ) : string
    {
        $tenant ??= Tenancy::value();

        return $tenant !== '' ? $type . ':' . $tenant : $type;
    }


    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    public static function key( array $fields ) : array
    {
        unset( $fields['tenant'] );

        return $fields;
    }
}
