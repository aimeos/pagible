<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms;


/**
 * Plural selector accepting BCP 47 language tags (e.g. "pt-BR") used as page languages.
 */
class MessageSelector extends \Illuminate\Translation\MessageSelector
{
    /**
     * Returns the plural index using the primary language subtag only.
     *
     * Laravel's plural rules never differ between regional variants of a language,
     * so "pt-BR", "pt_BR" and "sr-Latn" are reduced to "pt" and "sr".
     *
     * @param string $locale Language tag like "pt-BR" or "pt_BR"
     * @param int|float $number Number to select the plural form for
     * @return int Index of the plural segment
     */
    public function getPluralIndex( $locale, $number )
    {
        return parent::getPluralIndex( strtolower( strtok( (string) $locale, '-_' ) ?: '' ), $number );
    }
}
