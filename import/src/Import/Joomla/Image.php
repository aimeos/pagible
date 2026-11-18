<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * Converts the image values of the Joomla articles, categories and media fields.
 */
class Image
{
    /** Start of the suffix of the Joomla 4+ media field values */
    protected const SUFFIX = '#joomlaImage://';


    /**
     * Returns the image path without the suffix of the Joomla 4+ media field.
     *
     * @param string $value Image value like "images/a.jpg#joomlaImage://local-images/a.jpg?width=800&height=600"
     * @return string Image path like "images/a.jpg"
     */
    public static function path( string $value ): string
    {
        $pos = strpos( $value, self::SUFFIX );
        return $pos !== false ? substr( $value, 0, $pos ) : $value;
    }


    /**
     * Returns the normalized source of an image value or HTML image source.
     *
     * The suffix of the Joomla 4+ media field is removed and the source is trimmed. Inline images
     * aren't files and are skipped like by the other importers because they can be very large.
     *
     * @param string $value Decoded image value like " images/a.jpg#joomlaImage://local-images/a.jpg"
     * @return string Source like "images/a.jpg" or an empty string if there's no image or it's an inline image
     */
    public static function source( string $value ): string
    {
        $src = trim( self::path( $value ) );
        return $src !== '' && !Html::inline( $src ) ? $src : '';
    }
}
