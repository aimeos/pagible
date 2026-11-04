<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Aimeos\Cms\Utils;


/**
 * Creates the article element of WordPress posts imported as blog articles.
 */
class WpArticle
{
    /**
     * Returns the article element with the introduction and the cover image of the post.
     *
     * @param string $intro Introduction text of the post
     * @param string|null $coverFileId ID of the featured image shown in the article element
     * @param string|null $previewFileId ID of the image used as preview if there's no featured image
     * @return array<string, mixed> Article element
     */
    public static function element( string $intro, ?string $coverFileId, ?string $previewFileId = null ): array
    {
        $data = ['text' => $intro];
        $files = $previewFileId ? [$previewFileId] : [];

        if( $coverFileId ) {
            $data['file'] = ['id' => $coverFileId, 'type' => 'file'];
            $files = [$coverFileId];
        }

        return ['id' => Utils::uid(), 'type' => 'article', 'group' => 'main', 'files' => $files, 'data' => $data];
    }


    /**
     * Returns the plain text of the post content before the "more" tag as introduction, shortened to 300 characters.
     */
    public static function intro( string $html ): string
    {
        if( str_contains( $html, '<!--more-->' ) ) {
            $html = explode( '<!--more-->', $html )[0];
        }

        $text = strip_tags( $html );
        $text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
        $text = (string) preg_replace( '/\s+/', ' ', $text );
        $text = trim( $text );

        if( mb_strlen( $text ) > 300 ) {
            $text = mb_substr( $text, 0, 297 ) . '...';
        }

        return $text;
    }
}
