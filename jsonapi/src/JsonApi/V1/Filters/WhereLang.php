<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\JsonApi\V1\Filters;

use LaravelJsonApi\Eloquent\Filters\Where;


/**
 * Returns the variants of the pages in the given language.
 *
 * The language is also passed to the included navigation relations, which show
 * pages without variant in that language depending on "cms.translate.fallback".
 */
class WhereLang extends Where
{
    /**
     * Applies the filter to the page query.
     *
     * @param \Aimeos\Cms\Query\PageQuery<\Aimeos\Cms\Models\Page> $query
     * @param mixed $value
     * @return \Aimeos\Cms\Query\PageQuery<\Aimeos\Cms\Models\Page>
     */
    public function apply( $query, $value )
    {
        $lang = (string) $this->deserialize( $value );

        // invalid codes match no variant and can't be quoted if they contain NUL bytes
        if( !\Aimeos\Cms\Utils::isValidLang( $lang ) ) {
            return $query->whereRaw( '1 = 0' );
        }

        $query->getModel()->setAttribute( 'lang', $lang );
        $query->language( $lang );

        return $query;
    }
}
