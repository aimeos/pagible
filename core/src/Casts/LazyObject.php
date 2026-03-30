<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;


/**
 * Casts a JSON column to stdClass/array with lazy decoding.
 *
 * Unlike the built-in 'object' cast which calls json_decode() on every
 * attribute access, this cast benefits from Laravel's $classCastCache
 * so decoding happens only once per model instance.
 */
/** @implements CastsAttributes<\stdClass|array<int|string, mixed>|null, \stdClass|array<int|string, mixed>|string|null> */
class LazyObject implements CastsAttributes
{
    /**
     * Cast the given value from the database.
     *
     * @param Model $model Eloquent model instance
     * @param string $key Attribute name
     * @param mixed $value Raw database value
     * @param array<string, mixed> $attributes All raw attributes
     * @return \stdClass|array<int|string, mixed>|null Decoded value or null
     */
    public function get( Model $model, string $key, mixed $value, array $attributes ) : \stdClass|array|null
    {
        if( is_null( $value ) ) {
            return null;
        }

        return json_decode( $value );
    }


    /**
     * Prepare the given value for storage.
     *
     * @param Model $model Eloquent model instance
     * @param string $key Attribute name
     * @param \stdClass|array<int|string, mixed>|string|null $value Value to store
     * @param array<string, mixed> $attributes All raw attributes
     * @return string|null JSON string or null
     */
    public function set( Model $model, string $key, mixed $value, array $attributes ) : ?string
    {
        if( is_null( $value ) ) {
            return null;
        }

        return is_string( $value ) ? $value : (string) json_encode( $value );
    }
}
