<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Aimeos\Cms\Validation;
use Illuminate\Database\Eloquent\Casts\Attribute;


/**
 * Normalizes the page attribute values when they are set.
 *
 * Used by the PageVariant model storing the values and the PageView model
 * reading them, so both store the same values.
 */
trait PageAttributes
{
    /**
     * Interact with the "cache" property.
     *
     * @return Attribute<mixed, mixed> Eloquent attribute for the "cache" property
     */
    protected function cache(): Attribute
    {
        return Attribute::make(
            set: fn( $value ) => $value === null ? 5 : (int) $value,
        );
    }


    /**
     * Interact with the "config" property.
     *
     * @return Attribute<mixed, mixed> Eloquent attribute for the "config" property
     */
    protected function config(): Attribute
    {
        return Attribute::make(
            set: fn( $value ) => json_encode( Validation::structured( $value, 'config' ) ),
        );
    }


    /**
     * Interact with the "content" property.
     *
     * @return Attribute<mixed, mixed> Eloquent attribute for the "content" property
     */
    protected function content(): Attribute
    {
        return Attribute::make(
            set: fn( $value ) => json_encode( $value ?? [] ),
        );
    }


    /**
     * Interact with the "domain" property.
     *
     * @return Attribute<mixed, mixed> Eloquent attribute for the "domain" property
     */
    protected function domain(): Attribute
    {
        // host names are case insensitive and requests use lower case hosts
        return Attribute::make(
            set: fn( $value ) => mb_strtolower( (string) $value ),
        );
    }


    /**
     * Interact with the "name" property.
     *
     * @return Attribute<mixed, mixed> Eloquent attribute for the "name" property
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn( $value ) => (string) $value,
        );
    }


    /**
     * Interact with the "meta" property.
     *
     * @return Attribute<mixed, mixed> Eloquent attribute for the "meta" property
     */
    protected function meta(): Attribute
    {
        return Attribute::make(
            set: fn( $value ) => json_encode( Validation::structured( $value, 'meta' ) ),
        );
    }


    /**
     * Interact with the "path" property.
     *
     * @return Attribute<mixed, mixed> Eloquent attribute for the "path" property
     */
    protected function path(): Attribute
    {
        return Attribute::make(
            set: fn( $value ) => (string) $value,
        );
    }


    /**
     * Interact with the "status" property.
     *
     * @return Attribute<mixed, mixed> Eloquent attribute for the "status" property
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            set: fn( $value ) => (int) $value,
        );
    }


    /**
     * Interact with the "tag" property.
     *
     * @return Attribute<mixed, mixed> Eloquent attribute for the "tag" property
     */
    protected function tag(): Attribute
    {
        return Attribute::make(
            set: fn( $value ) => (string) $value,
        );
    }


    /**
     * Interact with the "theme" property.
     *
     * @return Attribute<mixed, mixed> Eloquent attribute for the "theme" property
     */
    protected function theme(): Attribute
    {
        return Attribute::make(
            set: fn( $value ) => (string) $value,
        );
    }


    /**
     * Interact with the "to" property.
     *
     * @return Attribute<mixed, mixed> Eloquent attribute for the "to" property
     */
    protected function to(): Attribute
    {
        return Attribute::make(
            set: fn( $value ) => (string) $value,
        );
    }


    /**
     * Interact with the "type" property.
     *
     * @return Attribute<mixed, mixed> Eloquent attribute for the "type" property
     */
    protected function type(): Attribute
    {
        return Attribute::make(
            set: fn( $value ) => (string) $value,
        );
    }
}
