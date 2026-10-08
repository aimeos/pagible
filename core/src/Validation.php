<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;


class Validation
{
    /** @var array<class-string, array<string, int>> Maximum string lengths or integer values of scalar fields */
    private const LIMITS = [
        Element::class => ['lang' => 10, 'name' => 255, 'type' => 50],
        File::class => ['lang' => 10, 'mime' => 100, 'name' => 255, 'path' => 255],
        Page::class => [
            'cache' => 32767, 'domain' => 255, 'lang' => 10, 'name' => 255, 'path' => 255,
            'status' => 32767, 'tag' => 30, 'theme' => 30, 'title' => 255, 'to' => 255, 'type' => 30,
        ],
    ];

    /** @var array<string, bool> Scalar fields containing non-negative integers */
    private const NUMBERS = ['cache' => true, 'status' => true];


    /**
     * Validates the scalar fields of the input against the storage limits of the model.
     *
     * @param class-string<Element|File|Page> $model Model class the input is stored in
     * @param array<string, mixed> $input Input data
     * @throws Exception If a value isn't scalar or exceeds its limit
     */
    public static function limits( string $model, array $input ) : void
    {
        foreach( self::LIMITS[$model] as $key => $max )
        {
            if( ( $value = $input[$key] ?? null ) === null ) {
                continue;
            }

            if( isset( self::NUMBERS[$key] ) )
            {
                if( filter_var( $value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => $max]] ) === false ) {
                    throw new Exception( sprintf( 'Invalid value for "%s", expected an integer between 0 and %d', $key, $max ) );
                }
            }
            elseif( !is_scalar( $value ) || mb_strlen( (string) $value ) > $max ) {
                throw new Exception( sprintf( 'Invalid value for "%s", expected up to %d characters', $key, $max ) );
            }
        }
    }


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
        self::limits( Page::class, $input );

        if( !Utils::isValidUrl( $input['to'] ?? null, false ) ) {
            throw new Exception( sprintf( 'Invalid URL "%s" in "to" field', $input['to'] ?? '' ) );
        }

        if( !Permission::can( 'page:config', $user ) ) {
            unset( $input['config'] );
        }

        // the source language is changed by Resource::setSource() only
        unset( $input['source'] );

        // host names are case insensitive and requests use lower case hosts
        if( is_string( $input['domain'] ?? null ) ) {
            $input['domain'] = mb_strtolower( $input['domain'] );
        }

        if( isset( $input['content'] ) )
        {
            foreach( $input['content'] as &$item )
            {
                $item = (object) $item;

                if( is_string( $item->type ?? null )
                    && ( is_object( $item->data ?? null ) || is_array( $item->data ?? null ) )
                ) {
                    self::html( $item->type, $item->data );
                    $item->data = self::defaults( $item->type, $item->data );
                }

                // Keep the per-element "files" list in sync with the file references in the
                // element data, so readers resolving files from it (JSON:API, blog list) work
                // regardless of how the content was saved.
                if( ( $item->type ?? null ) !== 'reference' )
                {
                    if( $files = self::files( $item->data ?? null ) ) {
                        $item->files = $files;
                    } else {
                        unset( $item->files );
                    }
                }
            }

            unset( $item );

            self::validateContent( $input['content'] );
        }

        if( array_key_exists( 'meta', $input ) ) {
            $input['meta'] = self::structured( $input['meta'], 'meta' );
        }

        if( array_key_exists( 'config', $input ) ) {
            $input['config'] = self::structured( $input['config'], 'config' );
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
            } elseif( $files = self::files( $entry['data'] ) ) {
                $entry['files'] = $files;
            }

            return (object) $entry;
        }, $items ) );
    }


    /**
     * Applies schema defaults and generated collection identities to element data.
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
     * @return object Data with schema defaults applied
     */
    public static function defaults( string $type, object|array $data, string $section = 'content',
        ?array $schemas = null
    ) : object {
        $data = (object) $data;
        $schemas ??= Schema::schemas( section: $section );

        return self::fields( $data, $schemas[$type]['fields'] ?? [] );
    }


    /**
     * Builds one canonical meta/config entry.
     *
     * @param string $type Entry type
     * @param object|array<string, mixed> $data Entry field data
     * @param string $section Schema section ('meta' or 'config')
     * @return object Canonical {type, data, files} entry
     */
    public static function entry( string $type, object|array $data, string $section ) : object
    {
        $data = self::defaults( $type, $data, $section );

        return (object) [
            'type' => $type,
            'data' => $data,
            'files' => self::files( $data ),
        ];
    }


    /**
     * Validates canonical structured meta/config objects.
     *
     * Only accepts entries keyed by type in the canonical shape
     * {type, data, files}. The type key is the stable identity; structured
     * meta/config entries deliberately don't carry a separate ID. Legacy shapes
     * are handled exclusively by the data migration and are rejected at runtime.
     *
     * @param array<mixed>|object|null $items Canonical meta/config entries
     * @param string $section Schema section ('meta' or 'config')
     * @return object Structured meta/config object
     */
    public static function structured( array|object|null $items, string $section ) : object
    {
        $schemas = Schema::schemas( section: $section );

        if( is_null( $items ) ) {
            throw new Exception( sprintf( 'Invalid %s structure: expected an object keyed by type', $section ) );
        }

        $result = new \stdClass();
        $items = (array) $items;

        if( $items && array_is_list( $items ) ) {
            throw new Exception( sprintf( 'Invalid %s structure: entries must be keyed by type', $section ) );
        }

        foreach( $items as $key => $value )
        {
            if( !is_array( $value ) && !is_object( $value ) ) {
                throw new Exception( sprintf( 'Invalid %s entry "%s": entry must be an object', $section, $key ) );
            }

            $value = (array) $value;
            $keys = array_keys( $value );
            sort( $keys );

            if( $keys !== ['data', 'files', 'type'] ) {
                throw new Exception( sprintf( 'Invalid %s entry "%s": expected type, data and files', $section, $key ) );
            }

            $entryType = $value['type'];

            if( !is_string( $key ) || $key === '' || !is_string( $entryType ) || $entryType !== $key ) {
                throw new Exception( sprintf( 'Invalid %s entry "%s": key and type must match', $section, $key ) );
            }

            if( !is_array( $value['data'] ) && !is_object( $value['data'] ) ) {
                throw new Exception( sprintf( 'Invalid %s entry "%s": data must be an object', $section, $key ) );
            }

            if( !is_array( $value['files'] ) || !array_is_list( $value['files'] )
                || array_filter( $value['files'], fn( $id ) => !is_string( $id ) )
            ) {
                throw new Exception( sprintf( 'Invalid %s entry "%s": files must be a list of strings', $section, $key ) );
            }

            $data = self::defaults( $entryType, $value['data'], $section, $schemas );
            $files = self::files( $data );

            if( $files !== $value['files'] ) {
                throw new Exception( sprintf( 'Invalid %s entry "%s": files must match data references', $section, $key ) );
            }

            $result->{$entryType} = (object) [
                'type' => $entryType,
                'data' => $data,
                'files' => $files,
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
     * Recursively collects the IDs of {id, type: "file"} references in a data tree.
     *
     * Lets non-browser writers (MCP/LLM, GraphQL) persist the same per-element "files"
     * list the admin editor stores, so readers resolving files from it (JSON:API, blog
     * list) work regardless of how the content was created.
     *
     * @param mixed $data Element data or a nested value
     * @return array<int, string> Deduped file IDs referenced in the data
     */
    public static function files( mixed $data ) : array
    {
        if( !is_array( $data ) && !is_object( $data ) ) {
            return [];
        }

        $data = (array) $data;

        if( ( $data['type'] ?? null ) === 'file' && is_string( $data['id'] ?? null ) && $data['id'] !== '' ) {
            return [$data['id']];
        }

        $ids = [];

        foreach( $data as $value ) {
            $ids = array_merge( $ids, self::files( $value ) );
        }

        return array_values( array_unique( $ids ) );
    }


    /**
     * Applies field defaults and generated collection identities recursively.
     *
     * @param object $data Field data
     * @param array<string, mixed> $fields Field schemas
     * @return object Normalized field data
     */
    private static function fields( object $data, array $fields ) : object
    {
        foreach( $fields as $name => $field )
        {
            if( !is_array( $field ) ) {
                continue;
            }

            if( ( $field['type'] ?? null ) === 'hidden' && isset( $field['value'] ) && !isset( $data->{$name} ) ) {
                $data->{$name} = $field['value'];
            }

            if( ( $field['type'] ?? null ) !== 'items' || !is_array( $data->{$name} ?? null ) ) {
                continue;
            }

            $identity = $field['identity'] ?? null;
            $data->{$name} = array_values( array_map( function( mixed $item ) use ( $field, $identity ) {
                if( !is_array( $item ) && !is_object( $item ) ) {
                    return $item;
                }

                $item = (object) $item;

                if( is_string( $identity ) && $identity !== ''
                    && ( !is_string( $item->{$identity} ?? null ) || $item->{$identity} === '' )
                ) {
                    $item->{$identity} = Utils::uid();
                }

                return self::fields( $item, (array) ( $field['item'] ?? [] ) );
            }, $data->{$name} ) );
        }

        return $data;
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
                if( !is_string( $item->refid ?? null ) || $item->refid === '' ) {
                    throw new Exception( 'Invalid content reference ID' );
                }

                continue;
            }

            if( !$type || !isset( $schemas[$type] ) ) {
                throw new Exception( sprintf( 'Unknown content type "%s"', $type ?? '' ) );
            }
        }
    }


    /**
     * Validates an element type and sanitizes the element data if passed
     *
     * @param string $type Element type to validate
     * @param object|array<string, mixed>|null $data Element data to sanitize
     * @param bool $strict TRUE to reject element types missing in the schemas
     * @return array<string, mixed>|null Sanitized data with defaults or NULL if no data was passed
     * @throws Exception If element type is unknown
     */
    public static function element( string $type, object|array|null $data = null, bool $strict = true ) : ?array
    {
        $schemas = Schema::schemas( section: 'content' );

        if( $strict && !isset( $schemas[$type] ) ) {
            throw new Exception( sprintf( 'Unknown element type "%s"', $type ) );
        }

        if( $data === null ) {
            return null;
        }

        self::html( $type, $data );
        return (array) self::defaults( $type, $data, 'content', $schemas );
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
