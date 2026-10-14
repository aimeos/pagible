<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;


/**
 * Casts the attribute to a string when it's read and set, NULL is stored as empty string.
 *
 * @implements CastsAttributes<string|null, mixed>
 */
class AsString implements CastsAttributes
{
    /**
     * Casts the stored value to a string.
     *
     * @param array<string, mixed> $attributes
     */
    public function get( Model $model, string $key, mixed $value, array $attributes ) : ?string
    {
        return $value === null ? null : (string) $value;
    }


    /**
     * Casts the passed value to a string.
     *
     * @param array<string, mixed> $attributes
     */
    public function set( Model $model, string $key, mixed $value, array $attributes ) : string
    {
        return (string) $value;
    }
}
