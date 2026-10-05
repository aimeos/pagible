<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Aimeos\Cms\Models\Page;


/**
 * Page helpers shared by the importers.
 */
class Pages
{
    /**
     * Creates a page and appends it to the parent page if given.
     *
     * @param array<string, mixed> $data Page data
     * @param array<int|string, mixed> $content Content elements
     * @param Page|null $parent Parent page or NULL for a root page
     * @return Page Created page
     */
    public static function create( array $data, array $content, ?Page $parent = null ) : Page
    {
        $page = Page::forceCreate( $data + ['content' => $content] );

        if( $parent ) {
            $page->appendToNode( $parent )->save();
        }

        return $page;
    }


    /**
     * Returns the first page matching the conditions and restores it if it's trashed.
     *
     * @param array<string, mixed> $where Column/value pairs
     * @return Page|null Found page or NULL if none matches
     */
    public static function find( array $where ) : ?Page
    {
        $page = Page::withTrashed()->where( $where )->first();

        if( $page?->trashed() ) {
            $page->restore();
        }

        return $page;
    }


    /**
     * Creates a new version of the page and publishes it.
     *
     * @param Page $page Page to publish
     * @param array<string, mixed> $data Page data of the version
     * @param array<string, mixed> $aux Auxiliary data of the version like content, config and meta
     * @param array<string> $fileIds IDs of the files referenced by the version
     * @param array<string> $elementIds IDs of the shared elements referenced by the version
     * @param string $lang Language of the version
     * @param string $editor Editor name for the version
     */
    public static function publish( Page $page, array $data, array $aux, array $fileIds, array $elementIds,
        string $lang, string $editor ) : void
    {
        $version = $page->versions()->forceCreate( [
            'lang' => $lang,
            'data' => $data,
            'aux' => $aux,
            'editor' => $editor,
        ] );

        if( !empty( $fileIds ) ) {
            $version->files()->attach( $fileIds );
        }

        if( !empty( $elementIds ) ) {
            $version->elements()->attach( $elementIds );
        }

        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->publish( $version );
    }
}
