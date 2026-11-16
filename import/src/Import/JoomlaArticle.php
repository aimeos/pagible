<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Returns the values only the Joomla articles have, the common ones of all rows are returned by JoomlaRow.
 */
class JoomlaArticle
{
    /** Image field of the articles shown in the lists of the articles */
    public const INTRO_IMAGE = 'image_intro';

    /** Image field of the articles shown on the article pages */
    public const ARTICLE_IMAGE = 'image_fulltext';


    /**
     * Returns the category ID of the article or 0 if it has none.
     */
    public static function catId( \stdClass $article ): int
    {
        return (int) ( $article->catid ?? 0 );
    }


    /**
     * Returns the decoded images column of the Joomla article.
     *
     * The suffix of the Joomla 4+ media field, e.g. "#joomlaImage://local-images/a.jpg?width=800&height=600", is removed from the image paths.
     *
     * @param \stdClass|null $article Joomla article or NULL if there's none
     * @return array<string, mixed>
     */
    public static function images( ?\stdClass $article ): array
    {
        $images = JoomlaValue::decode( $article->images ?? null );

        foreach( [self::INTRO_IMAGE, self::ARTICLE_IMAGE] as $name )
        {
            $path = $images[$name] ?? null;

            if( is_string( $path ) ) {
                $images[$name] = JoomlaImage::path( $path );
            }
        }

        return $images;
    }
}
