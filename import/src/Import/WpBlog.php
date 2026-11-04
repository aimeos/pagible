<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Aimeos\Cms\Models\PageView;


/**
 * Reads the content the WordPress posts inherit from their parent blog page.
 */
class WpBlog
{
    /**
     * Returns the shared footer references of the blog page.
     *
     * @param PageView $page Parent blog page of the posts
     * @return array{elements: array<int, array<string, mixed>>, elementIds: array<string>}
     */
    public static function footer( PageView $page ): array
    {
        $elements = collect( (array) $page->content )
            ->filter( fn( $item ) => ( $item->type ?? null ) === 'reference'
                && ( $item->group ?? null ) === 'footer'
                && is_string( $item->refid ?? null )
                && $item->refid !== '' )
            ->map( fn( $item ) => (array) $item )
            ->values()
            ->all();

        return [
            'elements' => $elements,
            'elementIds' => array_values( array_unique( array_column( $elements, 'refid' ) ) ),
        ];
    }
}
