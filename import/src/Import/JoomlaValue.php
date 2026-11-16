<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Converts the values of the Joomla database columns and options.
 */
class JoomlaValue
{
    /**
     * Returns the decoded JSON object or list of a column like "images", "attribs", "params", "metadata" or "rules".
     *
     * @return array<array-key, mixed> Decoded values or empty array for empty and invalid values
     */
    public static function decode( mixed $json ): array
    {
        $values = is_string( $json ) && $json !== '' ? json_decode( $json, true ) : null;
        return is_array( $values ) ? $values : [];
    }


    /**
     * Tests if the Joomla option is enabled.
     *
     * Like in Joomla, "0", 0 and FALSE disable the option while NULL and empty strings use the default.
     *
     * @param array<string, mixed> $options Options by name
     * @param bool $default Value used if the option isn't set
     */
    public static function enabled( array $options, string $name, bool $default = true ): bool
    {
        if( ( $options[$name] ?? null ) === false ) {
            return false;
        }

        $value = self::string( $options[$name] ?? null );
        return $value === '' ? $default : $value !== '0';
    }


    /**
     * Returns the options which are set.
     *
     * @template TKey of array-key
     * @template TValue
     * @param array<TKey, TValue> $options Options by name
     * @return array<TKey, TValue> Options without NULL and empty string values by name
     */
    public static function filled( array $options ): array
    {
        return array_filter( $options, fn( $value ) => $value !== null && $value !== '' );
    }


    /**
     * Returns the trimmed text of a scalar value.
     *
     * @return string Text or an empty string for other values
     */
    public static function string( mixed $value ): string
    {
        return is_scalar( $value ) ? trim( (string) $value ) : '';
    }


    /**
     * Returns the text or HTML content as string without trimming it.
     *
     * Leading and trailing white space of content is kept because it can be significant, e.g. in <pre> blocks.
     *
     * @param mixed $value Text or HTML content
     * @return string Untrimmed content or an empty string if the value isn't scalar
     */
    public static function text( mixed $value ): string
    {
        return is_scalar( $value ) ? (string) $value : '';
    }


    /**
     * Returns the first value which isn't empty.
     *
     * @param string ...$values Values in the order they are used
     * @return string First non-empty value or an empty string if all are empty
     */
    public static function firstFilled( string ...$values ): string
    {
        foreach( $values as $value )
        {
            if( $value !== '' ) {
                return $value;
            }
        }

        return '';
    }
}
