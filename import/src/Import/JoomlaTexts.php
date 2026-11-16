<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Keeps the texts of the Joomla articles imported next per chunk by JoomlaChunkCache and returns the images used by them.
 *
 * The texts of other articles are fetched on first use and kept until the next chunk is prefetched. JoomlaExtras
 * keeps the tags and custom field values the same way.
 */
class JoomlaTexts
{
    /** Message shown if the text of an article which wasn't prefetched is fetched on its own */
    protected const UNFETCHED = '  Fetched the text of article "%s" on its own because it wasn\'t prefetched';

    /** @var JoomlaChunkCache<\stdClass> Text rows of the articles of the current chunk and of the ones fetched on their own */
    protected JoomlaChunkCache $texts;


    /**
     * Initializes the object.
     *
     * @param JoomlaReader $reader Reader for the Joomla database
     * @param JoomlaOrigin $origin Removes the directory of Joomla sites in subdirectories from the image sources like the import of the images
     * @param \Closure(string): void|null $debug Shows a debug message, e.g. if a text is fetched on its own, or NULL to ignore them
     */
    public function __construct( JoomlaReader $reader, protected JoomlaOrigin $origin, ?\Closure $debug = null )
    {
        $this->texts = new JoomlaChunkCache( fn( array $ids ) => $reader->texts( $ids ), self::UNFETCHED, $debug );
    }


    /**
     * Fetches the texts of the Joomla articles imported next at once.
     *
     * The texts of the former chunk are discarded, the ones of these articles fetched before are kept.
     *
     * @param array<int|string> $ids IDs of the articles
     */
    public function prefetch( array $ids ): void
    {
        $this->texts->prefetch( $ids );
    }


    /**
     * Returns the paths of the images used by the texts of the articles which are kept.
     *
     * All texts of the articles passed to prefetch() are kept until the next chunk is prefetched, so their images are complete.
     *
     * @param array<int|string> $ids IDs of the articles
     * @return array<int, string> Unique paths of the full text images and the images in the texts, the intro images
     *  are only shown on the pages of teasers, which are rare, so they are fetched when used
     */
    public function imagePaths( array $ids ): array
    {
        $paths = [];

        foreach( $ids as $id )
        {
            if( !( $row = $this->texts->kept( $id ) ) ) {
                continue;
            }

            // raw value because imageSource() removes the suffix of the Joomla 4+ media field like JoomlaArticle::images()
            $image = JoomlaValue::decode( $row->images ?? null )[JoomlaArticle::ARTICLE_IMAGE] ?? null;
            $paths[] = is_string( $image ) ? $this->imageSource( $image ) : '';

            $html = JoomlaValue::text( $row->introtext ?? null ) . JoomlaValue::text( $row->fulltext ?? null );
            array_push( $paths, ...array_map( $this->origin->fileSource( ... ), JoomlaHtml::sources( $html ) ) );
        }

        return array_values( array_unique( array_filter( $paths, fn( $path ) => $path !== '' ) ) );
    }


    /**
     * Returns the source of the image of an article or category like the ones of the images in the texts.
     *
     * The suffix of the Joomla 4+ media field is removed and the source is relative to the base URL of the files
     * if it's a file of the Joomla site, see JoomlaOrigin::fileSource(), so the prefetched files are found again.
     *
     * @param string $value Image value like "/joomla/images/a.jpg#joomlaImage://local-images/a.jpg" or "https://example.com/joomla/images/a.jpg"
     * @return string Source like "/images/a.jpg" or an empty string if there's no image or it's an inline image
     */
    public function imageSource( string $value ): string
    {
        $path = trim( JoomlaImage::path( $value ) );
        return $path !== '' && !JoomlaHtml::inline( $path ) ? $this->origin->fileSource( $path ) : '';
    }


    /**
     * Returns the intro and full text, the decoded images, links and options of the Joomla article.
     *
     * The texts of articles which weren't prefetched are fetched on their own and kept until the next chunk is prefetched.
     *
     * @param int|string $id ID of the article
     * @return JoomlaText Text of the article, an empty one if there's no text row, e.g. of deleted articles
     */
    public function text( int|string $id ): JoomlaText
    {
        return ( $row = $this->texts->get( $id ) ) ? JoomlaText::of( $row ) : new JoomlaText();
    }
}
