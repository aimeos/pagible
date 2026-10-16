<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Scout;


/**
 * Page helpers shared by the importers.
 */
class Pages
{
    /**
     * Creates a page and appends it to the parent page if given.
     *
     * The page isn't indexed because the importers publish it afterwards, which indexes it.
     *
     * @param array<string, mixed> $data Page data
     * @param array<int|string, mixed> $content Content elements
     * @param PageView|null $parent Parent page or NULL for a root page
     * @return PageView Created page
     */
    public static function create( array $data, array $content, ?PageView $parent = null ) : PageView
    {
        $node = new Page( ['source' => (string) ( $data['lang'] ?? '' )] );
        $parent ? $node->appendToNode( Page::findOrFail( $parent->id ) )->save() : $node->save();

        return Scout::mute( [PageView::class], fn() => $node->variants()->forceCreate( $data + ['content' => $content] ) )->view;
    }


    /**
     * Returns the first page matching the conditions and restores it if it's trashed.
     *
     * @param array<string, mixed> $where Column/value pairs
     * @return PageView|null Found page or NULL if none matches
     */
    public static function find( array $where ) : ?PageView
    {
        $page = PageView::withTrashed()->where( $where )->first();

        if( $page?->trashed() )
        {
            $page->page->restore();
            // loaded again because the view row still contains the deletion date and the old values
            $page = PageView::where( $where )->whereKey( $page->id )->firstOrFail();
        }

        return $page;
    }


    /**
     * Creates a new version of the page and publishes it.
     *
     * @param PageView $page Page to publish
     * @param array<string, mixed> $data Page data of the version
     * @param array<string, mixed> $aux Auxiliary data of the version like content, config and meta
     * @param array<string> $fileIds IDs of the files referenced by the version
     * @param array<string> $elementIds IDs of the shared elements referenced by the version
     * @param string $lang Language of the version
     * @param string $editor Editor name for the version
     */
    public static function publish( PageView $page, array $data, array $aux, array $fileIds, array $elementIds,
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

        PageVariant::whereKey( $page->variant_id )->toBase()->update( ['latest_id' => $version->id] );
        $page->latest_id = $version->id;
        $page->publish( $version );
    }
}
