<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms;

use Carbon\Carbon;


class Validation
{
    /**
     * Validates page/element content arrays against configured schemas
     *
     * @param iterable<array<string, mixed>> $items Content items to validate
     * @throws \InvalidArgumentException If content type is unknown
     */
    public static function content( iterable $items ): void
    {
        $schemas = config( 'cms.schemas.content', [] );

        foreach( $items as $item )
        {
            $item = (object) $item;
            $type = $item->type ?? null;

            if( $type === 'reference' ) {
                continue;
            }

            if( !$type || !isset( $schemas[$type] ) ) {
                throw new \InvalidArgumentException( sprintf( 'Unknown content type "%s"', $type ?? '' ) );
            }
        }
    }


    /**
     * Validates structured objects (meta/config) against configured schemas
     *
     * Skips entries with unknown types or without the expected data structure
     * to support legacy/simplified formats and theme switching.
     *
     * @param object $items Object with named entries to validate
     * @param string $schemaKey Schema config key (e.g. 'meta', 'config')
     */
    public static function structured( object $items, string $schemaKey ): void
    {
        $schemas = config( 'cms.schemas.' . $schemaKey, [] );

        foreach( get_object_vars( $items ) as $key => $item )
        {
            if( !isset( $schemas[$key] ) || !is_object( $item ) ) {
                continue;
            }
        }
    }


    /**
     * Validates that publish_at is a valid future datetime
     *
     * @param string|null $at Datetime string
     * @throws \InvalidArgumentException If datetime is invalid or in the past
     */
    public static function publishAt( ?string $at ): void
    {
        if( !$at ) {
            return;
        }

        try {
            $date = Carbon::parse( $at );
        } catch( \Exception $e ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid publish date "%s"', $at ) );
        }

        if( $date->isPast() ) {
            throw new \InvalidArgumentException( 'Publish date must be in the future' );
        }
    }
}
