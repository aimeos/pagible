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
}
