<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;


class Validation
{
    /**
     * Sanitizes page input: validates URL, strips config without permission,
     * sanitizes HTML content, validates content/meta/config schemas.
     *
     * @param array<string, mixed> $input Page input data
     * @param Authenticatable|null $user Authenticated user
     * @return array<string, mixed> Sanitized input
     * @throws \InvalidArgumentException On validation failure
     */
    public static function page( array $input, ?Authenticatable $user = null ) : array
    {
        if( !Utils::isValidUrl( $input['to'] ?? null, false ) ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid URL "%s" in "to" field', $input['to'] ?? '' ) );
        }

        if( !Permission::can( 'page:config', $user ) ) {
            unset( $input['config'] );
        }

        if( isset( $input['content'] ) )
        {
            foreach( $input['content'] as &$item )
            {
                $item = (object) $item;

                if( ( $item->type ?? null ) === 'html' )
                {
                    if( is_object( $item->data ?? null ) && isset( $item->data->text ) ) {
                        $item->data->text = Utils::html( (string) $item->data->text );
                    } elseif( is_array( $item->data ?? null ) && isset( $item->data['text'] ) ) {
                        $item->data['text'] = Utils::html( (string) $item->data['text'] );
                    }
                }
            }

            self::validateContent( $input['content'] );
        }

        if( isset( $input['meta'] ) ) {
            self::validateStructured( is_object( $input['meta'] ) ? $input['meta'] : (object) $input['meta'], 'meta' );
        }

        if( isset( $input['config'] ) ) {
            self::validateStructured( is_object( $input['config'] ) ? $input['config'] : (object) $input['config'], 'config' );
        }

        return $input;
    }


    /**
     * Validates and builds content elements with auto IDs and group defaults.
     *
     * @param array<int, array<string, mixed>|object> $items Content element items
     * @return array<int, object> Structured content elements
     * @throws \InvalidArgumentException If content type is unknown
     */
    public static function content( array $items ) : array
    {
        $schemas = Schema::schemas( section: 'content' );

        self::validateContent( $items, $schemas );

        return array_values( array_map( function( array|object $item ) use ( $schemas ) {
            $item = (array) $item;
            $type = $item['type'];
            $group = $item['group'] ?? $schemas[$type]['group'] ?? 'main';

            $entry = [
                'id' => $item['id'] ?? Utils::uid(),
                'type' => $type,
                'group' => $group,
                'data' => (object) ( $item['data'] ?? [] ),
            ];

            if( !empty( $item['refid'] ) ) {
                $entry['refid'] = $item['refid'];
            }

            if( !empty( $item['files'] ) ) {
                $entry['files'] = array_values( array_unique( $item['files'] ) );
            }

            return (object) $entry;
        }, $items ) );
    }


    /**
     * Validates and builds structured meta/config objects.
     *
     * @param array<string, array<string, mixed>> $items Keyed by type name, values are data fields
     * @param string $section Schema section ('meta' or 'config')
     * @param array<string, mixed>|object $existing Existing meta/config data to merge with
     * @return object Structured meta/config object
     */
    public static function structured( array $items, string $section, array|object|null $existing = null ) : object
    {
        $schemas = Schema::schemas( section: $section );

        self::validateStructured( (object) $items, $section, $schemas );
        $result = (object) ( (array) ( $existing ?? new \stdClass() ) );

        foreach( $items as $type => $data )
        {
            $group = $schemas[$type]['group'] ?? 'basic';
            $existingId = $result->{$type}->id ?? null;

            $result->{$type} = (object) [
                'id' => $existingId ?? Utils::uid(),
                'type' => $type,
                'group' => $group,
                'data' => (object) $data,
            ];
        }

        return $result;
    }


    /**
     * Sanitizes HTML content in element data if type is 'html'.
     *
     * @param string $type Element type
     * @param object|array<string, mixed> &$data Element data (modified in place)
     */
    public static function html( string $type, object|array &$data ) : void
    {
        if( $type !== 'html' ) {
            return;
        }

        if( is_object( $data ) && isset( $data->text ) ) {
            $data->text = Utils::html( (string) $data->text );
        } elseif( is_array( $data ) && isset( $data['text'] ) ) {
            $data['text'] = Utils::html( (string) $data['text'] );
        }
    }


    /**
     * Validates page/element content arrays against configured schemas
     *
     * @param iterable<array<string, mixed>|object> $items Content items to validate
     * @param array<string, mixed>|null $schemas Pre-loaded schemas or null to load
     * @throws \InvalidArgumentException If content type is unknown
     */
    private static function validateContent( iterable $items, ?array $schemas = null ): void
    {
        $schemas ??= Schema::schemas( section: 'content' );

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
     * Validates a single element type against configured content schemas
     *
     * @param string $type Element type to validate
     * @throws \InvalidArgumentException If element type is unknown
     */
    public static function element( string $type ): void
    {
        $schemas = Schema::schemas( section: 'content' );

        if( !isset( $schemas[$type] ) ) {
            throw new \InvalidArgumentException( sprintf( 'Unknown element type "%s"', $type ) );
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
     * @param array<string, mixed>|null $schemas Pre-loaded schemas or null to load
     */
    private static function validateStructured( object $items, string $schemaKey, ?array $schemas = null ): void
    {
        $schemas ??= Schema::schemas( section: $schemaKey );

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
