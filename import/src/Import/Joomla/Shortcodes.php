<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * Replaces the shortcodes of the "Content - Fields" and "Content - Load Modules" plugins like Joomla.
 *
 * The shortcodes are replaced in the article texts, category descriptions, custom field values and module contents.
 *
 * Args: arguments of a shortcode by name, see fields() and modules()
 *
 * @phpstan-type Args array{id?: string, position?: string, type?: string, title?: string, style?: string}
 */
class Shortcodes
{
    /** Shortcode of a custom field */
    protected const FIELD = 'field';
    /** Shortcode of a custom field group */
    public const FIELD_GROUP = 'fieldgroup';
    /** Shortcode of the modules of a position */
    public const LOAD_POSITION = 'loadposition';
    /** Shortcode of a module by its type and title */
    public const LOAD_MODULE = 'loadmodule';
    /** Shortcode of a module by its ID */
    public const LOAD_MODULE_ID = 'loadmoduleid';
    /** Module style without title and wrapper */
    public const NO_STYLE = 'none';
    /** Regular expression matching the arguments of most shortcodes, all characters up to the closing brace in the same line */
    protected const ARGS_PATTERN = '[^}\n]*';
    /** Regular expression matching the module ID argument of the "{loadmoduleid ...}" shortcode without leading zeros */
    protected const ID_PATTERN = '[1-9][0-9]*';
    /** Regular expression matching the spaces between the name and the arguments of the field shortcodes */
    protected const FIELD_SPACE_PATTERN = '\s+';
    /** Regular expression matching the single white space character between the name and the arguments of the module shortcodes, further ones are part of the arguments */
    protected const MODULE_SPACE_PATTERN = '\s';
    /** Regular expression matching an HTML text which is a single paragraph, its content as first group */
    protected const SINGLE_PARAGRAPH_PATTERN = '#^<p(?:\s[^>]*)?>((?:(?!<p[\s>]).)*)</p>$#is';


    /**
     * Replaces the shortcodes of the "Content - Fields" plugin by their output like Joomla.
     *
     * The plugin replaces the "{field ID}" and "{fieldgroup ID}" shortcodes before the "Content - Load Modules" plugin
     * replaces the module shortcodes, see modules(). The "{emailcloak=off}" shortcodes are handled by ModulePreparer::cloak().
     * Shortcodes which are the only content of a paragraph replace the whole paragraph, so the HTML isn't nested in it.
     *
     * The closure gets the shortcode with its name and arguments:
     * - "field": "id" of the field
     * - "fieldgroup": "id" of the field group, "0" for the fields without group or "*" for all fields
     *
     * @param string $html HTML with shortcodes like "{field 3}" or "{fieldgroup 1}"
     * @param \Closure(Shortcode): ?string $replace Returns the HTML for the shortcode or NULL to keep it
     * @return string HTML with replaced shortcodes
     */
    public static function fields( string $html, \Closure $replace ): string
    {
        // like Joomla, the quick check is case-sensitive while the shortcodes are matched case-insensitively
        if( !str_contains( $html, self::FIELD ) ) {
            return $html;
        }

        $names = self::FIELD . '|' . self::FIELD_GROUP;

        return self::replace( $html, $names, self::FIELD_SPACE_PATTERN, self::ARGS_PATTERN, function( string $name, string $arg ) use ( $replace ) {
            $id = (int) explode( ',', $arg )[0];

            if( $name === self::FIELD && $id ) {
                return $replace( new Shortcode( self::FIELD, ['id' => (string) $id], $arg ) );
            }

            // like Joomla, "{FIELD 3}" and "{field abc}" are field groups, the upper case ones are only replaced if a lower case "field" is in the HTML too
            return $replace( new Shortcode( self::FIELD_GROUP, ['id' => $arg === Fields::ALL_GROUPS ? Fields::ALL_GROUPS : (string) $id], $arg ) );
        } );
    }


    /**
     * Replaces the shortcodes of the "Content - Load Modules" plugin by their output like Joomla.
     *
     * The plugin replaces the "{loadposition ...}", "{loadmodule ...}" and "{loadmoduleid ...}" shortcodes one after
     * another, so the later ones are also replaced in the output of the former ones. Shortcodes which are the only
     * content of a paragraph replace the whole paragraph, so the HTML isn't nested in it.
     *
     * The closure gets the shortcode with its name and arguments:
     * - "loadposition": module "position" and module "style"
     * - "loadmodule": module "type", module "title" or an empty string and module "style"
     * - "loadmoduleid": module "id" and module "style"
     *
     * @param string $html HTML with shortcodes like "{loadposition banner}" or "{loadmoduleid 5}"
     * @param \Closure(Shortcode): ?string $replace Returns the HTML for the shortcode or NULL to keep it
     * @param string $style Default module style of the "Content - Load Modules" plugin
     * @return string HTML with replaced shortcodes
     */
    public static function modules( string $html, \Closure $replace, string $style = self::NO_STYLE ): string
    {
        // argument patterns and arguments of the module shortcodes in the order Joomla replaces them
        $shortcodes = [
            self::LOAD_POSITION => [self::ARGS_PATTERN, function( string $arg ) use ( $style ) {
                $args = explode( ',', $arg );
                return ['position' => trim( $args[0] ), 'style' => trim( $args[1] ?? $style )];
            }],
            self::LOAD_MODULE => [self::ARGS_PATTERN, function( string $arg ) use ( $style ) {
                $args = explode( ',', $arg );
                return ['type' => trim( $args[0] ), 'title' => htmlspecialchars_decode( trim( $args[1] ?? '' ) ), 'style' => trim( $args[2] ?? $style )];
            }],
            self::LOAD_MODULE_ID => [self::ID_PATTERN, fn( string $arg ) => ['id' => $arg, 'style' => self::NO_STYLE]],
        ];

        foreach( $shortcodes as $name => [$pattern, $args] )
        {
            // like Joomla, only shortcodes in lower case are found by the check but all are replaced then
            if( str_contains( $html, '{' . $name ) )
            {
                // the matched name can be in upper case, the closure always gets the lower case name
                $html = self::replace( $html, $name, self::MODULE_SPACE_PATTERN, $pattern, fn( string $matched, string $arg ) => $replace( new Shortcode( $name, $args( $arg ), $arg ) ) );
            }
        }

        return $html;
    }


    /**
     * Replaces the shortcodes matching the name and argument patterns.
     *
     * The regular expression has three alternatives, which are tested in this order:
     * - a paragraph containing only the shortcode, which is replaced as a whole including the paragraph
     * - a shortcode inside of a text
     * - an opening or closing paragraph tag, which only tracks if the following shortcodes are inside of a paragraph
     *
     * Shortcodes inside of a paragraph get the content of a single paragraph without the paragraph, so the
     * paragraphs aren't nested. The names in the matched shortcodes keep their case.
     *
     * @param string $names Regular expression matching the shortcode names
     * @param string $space Regular expression matching the spaces between the name and the arguments
     * @param string $arg Regular expression matching the arguments of the shortcode
     * @param \Closure(string, string): ?string $replace Returns the HTML for the name and the arguments of the shortcode or NULL to keep it
     * @return string HTML with replaced shortcodes
     */
    protected static function replace( string $html, string $names, string $space, string $arg, \Closure $replace ): string
    {
        // the name and argument groups are prefixed by "p" for whole paragraphs and by "i" for shortcodes inside of texts
        $shortcode = fn( string $prefix ) => '\{(?<' . $prefix . 'name>' . $names . ')' . $space . '(?<' . $prefix . 'arg>' . $arg . ')\}';
        // the paragraph tag is followed by its attributes or ">", so tags like "<pre>" or "<picture>" don't match
        $paragraph = '<p(?:\s[^>]*)?>\s*' . $shortcode( 'p' ) . '\s*<\/p>';
        $tag = '<(?<close>\/?)p(?=[\s>])';

        // TRUE after an opening and FALSE after a closing paragraph tag, updated by the callback while it walks through the HTML
        $inside = false;

        return (string) preg_replace_callback( '/' . $paragraph . '|' . $shortcode( 'i' ) . '|' . $tag . '/i', function( $match ) use ( &$inside, $replace ) {
            // paragraph containing only the shortcode
            if( isset( $match['pname'] ) ) {
                return $replace( $match['pname'], $match['parg'] ?? '' ) ?? $match[0];
            }

            // paragraph tag
            if( !isset( $match['iname'] ) ) {
                $inside = ( $match['close'] ?? '' ) === '';
                return $match[0];
            }

            // shortcode inside of a text

            $result = $replace( $match['iname'], $match['iarg'] ?? '' ) ?? $match[0];
            return $inside && preg_match( self::SINGLE_PARAGRAPH_PATTERN, $result, $inline ) ? $inline[1] : $result;
        }, $html, flags: PREG_UNMATCHED_AS_NULL );
    }
}
