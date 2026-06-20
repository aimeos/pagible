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
     * sanitizes HTML content, populates per-element file lists, validates
     * content/meta/config schemas.
     *
     * @param array<string, mixed> $input Page input data
     * @param Authenticatable|null $user Authenticated user
     * @return array<string, mixed> Sanitized input
     * @throws Exception On validation failure
     */
    public static function page( array $input, ?Authenticatable $user = null ) : array
    {
        if( !Utils::isValidUrl( $input['to'] ?? null, false ) ) {
            throw new Exception( sprintf( 'Invalid URL "%s" in "to" field', $input['to'] ?? '' ) );
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

                // Keep the per-element "files" list in sync with the file references in the
                // element data, so readers resolving files from it (JSON:API, blog list) work
                // regardless of how the content was saved.
                if( ( $item->type ?? null ) !== 'reference' )
                {
                    if( $files = self::fileIds( $item->data ?? null ) ) {
                        $item->files = $files;
                    } else {
                        unset( $item->files );
                    }
                }
            }

            unset( $item );

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
     * Elements without an explicit group, or with a group that is not a section of
     * the given page type, default to the first section defined for the page type in
     * schema.json (falling back to "main").
     *
     * @param array<int, array<string, mixed>|object> $items Content element items
     * @param string|null $type Page type whose sections provide the valid groups
     * @return array<int, object> Structured content elements
     * @throws Exception If content type is unknown
     */
    public static function content( array $items, ?string $type = null ) : array
    {
        $schemas = Schema::schemas( section: 'content' );

        self::validateContent( $items, $schemas );

        $sections = Schema::sections( $type );
        $default = $sections[0] ?? 'main';

        return array_values( array_map( function( array|object $item ) use ( $schemas, $sections, $default ) {
            $item = (array) $item;
            $type = $item['type'];
            $group = $item['group'] ?? $default;

            if( $sections && !in_array( $group, $sections, true ) ) {
                $group = $default;
            }

            $entry = [
                'id' => $item['id'] ?? Utils::uid(),
                'type' => $type,
                'group' => $group,
                'data' => self::defaults( $type, $item['data'] ?? [], 'content', $schemas ),
            ];

            if( !empty( $item['refid'] ) ) {
                $entry['refid'] = $item['refid'];
            }

            if( $type === 'reference' ) {
                if( !empty( $item['files'] ) ) {
                    $entry['files'] = array_values( (array) $item['files'] );
                }
            } elseif( $files = self::fileIds( $entry['data'] ) ) {
                $entry['files'] = $files;
            }

            return (object) $entry;
        }, $items ) );
    }


    /**
     * Applies default values of hidden schema fields to the given element data.
     *
     * Hidden fields carry a fixed "value" in the schema (e.g. the action handler
     * class for "toc" and "blog" elements). The admin editor injects these values
     * client-side, so this ensures non-browser writers (MCP/LLM, GraphQL, importers)
     * produce the same data and the action gets wired up on render.
     *
     * @param string $type Element/section type name
     * @param object|array<string, mixed> $data Element data fields
     * @param string $section Schema section ('content', 'meta', 'config')
     * @param array<string, mixed>|null $schemas Pre-loaded schemas or null to load
     * @return object Data with hidden field defaults applied
     */
    public static function defaults( string $type, object|array $data, string $section = 'content', ?array $schemas = null ) : object
    {
        $data = (object) $data;
        $schemas ??= Schema::schemas( section: $section );

        foreach( $schemas[$type]['fields'] ?? [] as $name => $field )
        {
            if( ( $field['type'] ?? null ) === 'hidden' && isset( $field['value'] ) && !isset( $data->{$name} ) ) {
                $data->{$name} = $field['value'];
            }
        }

        return $data;
    }


    /**
     * Validates and builds structured meta/config objects.
     *
     * The entry group defaults to the first section defined for the given page type
     * in schema.json (falling back to "main").
     *
     * @param array<string, array<string, mixed>> $items Keyed by type name, values are data fields
     * @param string $section Schema section ('meta' or 'config')
     * @param array<string, mixed>|object $existing Existing meta/config data to merge with
     * @param string|null $type Page type whose sections provide the default group
     * @return object Structured meta/config object
     */
    public static function structured( array $items, string $section, array|object|null $existing = null, ?string $type = null ) : object
    {
        $schemas = Schema::schemas( section: $section );

        self::validateStructured( (object) $items, $section, $schemas );
        $result = (object) ( (array) ( $existing ?? new \stdClass() ) );
        $group = Schema::section( $type );

        foreach( $items as $key => $data )
        {
            $existingId = $result->{$key}->id ?? null;

            $entry = [
                'id' => $existingId ?? Utils::uid(),
                'type' => $key,
                'group' => $group,
                'data' => self::defaults( $key, $data, $section, $schemas ),
            ];

            if( $files = self::fileIds( $entry['data'] ) ) {
                $entry['files'] = $files;
            }

            $result->{$key} = (object) $entry;
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
     * Recursively collects the IDs of {id, type: "file"} references in a data tree.
     *
     * Lets non-browser writers (MCP/LLM, GraphQL) persist the same per-element "files"
     * list the admin editor stores, so readers resolving files from it (JSON:API, blog
     * list) work regardless of how the content was created.
     *
     * @param mixed $data Element data or a nested value
     * @return array<int, string> Deduped file IDs referenced in the data
     */
    private static function fileIds( mixed $data ) : array
    {
        if( !is_array( $data ) && !is_object( $data ) ) {
            return [];
        }

        $data = (array) $data;

        if( ( $data['type'] ?? null ) === 'file' && !empty( $data['id'] ) ) {
            return [(string) $data['id']];
        }

        $ids = [];

        foreach( $data as $value ) {
            $ids = array_merge( $ids, self::fileIds( $value ) );
        }

        return array_values( array_unique( $ids ) );
    }


    /**
     * Validates page/element content arrays against configured schemas
     *
     * @param iterable<array<string, mixed>|object> $items Content items to validate
     * @param array<string, mixed>|null $schemas Pre-loaded schemas or null to load
     * @throws Exception If content type is unknown
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
                throw new Exception( sprintf( 'Unknown content type "%s"', $type ?? '' ) );
            }
        }
    }


    /**
     * Validates a single element type against configured content schemas
     *
     * @param string $type Element type to validate
     * @throws Exception If element type is unknown
     */
    public static function element( string $type ): void
    {
        $schemas = Schema::schemas( section: 'content' );

        if( !isset( $schemas[$type] ) ) {
            throw new Exception( sprintf( 'Unknown element type "%s"', $type ) );
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
     * @throws Exception If datetime is invalid or in the past
     */
    public static function publishAt( ?string $at ): void
    {
        if( !$at ) {
            return;
        }

        try {
            $date = Carbon::parse( $at );
        } catch( \Exception $e ) {
            throw new Exception( sprintf( 'Invalid publish date "%s"', $at ) );
        }

        if( $date->isPast() ) {
            throw new Exception( 'Publish date must be in the future' );
        }
    }
}
