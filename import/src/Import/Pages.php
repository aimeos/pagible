<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Aimeos\Cms\DB;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Models\Version;
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
     * @param string|null $created Creation date of the page or NULL for the current date
     * @return PageView Created page
     */
    public static function create( array $data, array $content, ?PageView $parent = null, ?string $created = null ) : PageView
    {
        $node = new Page( ['source' => (string) ( $data['lang'] ?? '' )] );
        if( $parent )
        {
            // the node of the parent is loaded once per parent, its bounds are refreshed before each insert
            $parentNode = $parent->page;

            if( !$parentNode instanceof Page || $parentNode->trashed() ) {
                $parentNode = Page::findOrFail( $parent->id );
            }

            $node->appendToNode( $parentNode )->save();
        }
        else
        {
            $node->save();
        }

        // the creation date is stored with the new page instead of updating it afterwards
        $values = $data + ['content' => $content] + ( $created ? ['created_at' => $created] : [] );

        return Scout::mute( [PageView::class], fn() => $node->variants()->forceCreate( $values ) )->view;
    }


    /**
     * Returns the pages matching the conditions for each of the values of the column at once.
     *
     * Trashed pages are returned too but not restored, use find() to restore them.
     *
     * @param array<string, mixed> $where Column/value pairs
     * @param string $column Column the values are compared to
     * @param array<int, string> $values Values of the column
     * @param array<int, string> $columns Columns of the pages which are fetched, must contain the compared column
     * @return array<string, PageView|null> Pages by column value, NULL for values without a page
     */
    public static function findAll( array $where, string $column, array $values, array $columns = ['*'] ) : array
    {
        $pages = array_fill_keys( $values, null );

        // numeric keys are converted to integers by PHP
        foreach( array_chunk( array_map( strval( ... ), array_keys( $pages ) ), DB::IN_SIZE ) as $chunk )
        {
            foreach( PageView::withTrashed()->where( $where )->whereIn( $column, $chunk )->get( $columns ) as $page )
            {
                if( $page instanceof PageView ) {
                    $pages[(string) $page->getAttribute( $column )] ??= $page;
                }
            }
        }

        return $pages;
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
     * Returns the root page, the one of the domain if a domain is given.
     *
     * @param string|null $domain Domain of the root page or NULL for the first root page of any domain
     */
    public static function root( ?string $domain = null ) : ?PageView
    {
        return PageView::where( 'tag', 'root' )->when( $domain !== null, fn( $query ) => $query->where( 'domain', $domain ) )->first();
    }


    /**
     * Tests if the published page already contains the page data, content and meta data.
     *
     * The IDs of the content elements are ignored because the importers create new ones each run.
     *
     * @param PageView $page Published page or page variant
     * @param array<string, mixed> $data Page data of the new version
     * @param array<string, mixed> $aux Auxiliary data of the new version like content, config and meta
     * @return bool TRUE if publishing a new version wouldn't change the page
     */
    public static function unchanged( PageView $page, array $data, array $aux ) : bool
    {
        foreach( $data as $key => $value )
        {
            if( self::normalize( $page->getAttribute( $key ) ) !== self::normalize( $value ) ) {
                return false;
            }
        }

        foreach( ['content', 'config', 'meta'] as $key )
        {
            if( self::elements( $page->getAttribute( $key ) ) !== self::elements( $aux[$key] ?? [] ) ) {
                return false;
            }
        }

        return true;
    }


    /**
     * Tests if the version contains the page data, content, meta data and publication date.
     *
     * The IDs of the content elements are ignored because the importers create new ones each run.
     *
     * @param Version $version Existing version
     * @param array<string, mixed> $data Page data of the new version
     * @param array<string, mixed> $aux Auxiliary data of the new version like content, config and meta
     * @param string|null $publishAt Publication date of the new version or NULL for none
     * @return bool TRUE if the version is the same
     */
    public static function same( Version $version, array $data, array $aux, ?string $publishAt ) : bool
    {
        $at = $version->getRawOriginal( 'publish_at' );

        if( ( $at !== null ? strtotime( (string) $at ) : null ) !== ( $publishAt !== null ? strtotime( $publishAt ) : null ) ) {
            return false;
        }

        if( self::normalize( array_diff_key( (array) $version->data, ['scheduled' => null] ) ) !== self::normalize( $data ) ) {
            return false;
        }

        foreach( ['content', 'config', 'meta'] as $key )
        {
            if( self::elements( $version->aux->{$key} ?? [] ) !== self::elements( $aux[$key] ?? [] ) ) {
                return false;
            }
        }

        return true;
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
        $version = self::newVersion( $page, $data, $aux, $fileIds, $elementIds, $lang, $editor );
        // publishing saves the latest version ID together with the published values
        $page->latest_id = $version->id;
        $page->publish( $version );
    }


    /**
     * Creates a new version of the page as its latest version without publishing it.
     *
     * Versions with a publication date are published by the "cms:publish" command at that time.
     *
     * @param PageView $page Page of the version
     * @param array<string, mixed> $data Page data of the version
     * @param array<string, mixed> $aux Auxiliary data of the version like content, config and meta
     * @param array<string> $fileIds IDs of the files referenced by the version
     * @param array<string> $elementIds IDs of the shared elements referenced by the version
     * @param string $lang Language of the version
     * @param string $editor Editor name for the version
     * @param string|null $publishAt Date and time when the version is published or NULL for a draft
     * @return Version Created version
     */
    public static function version( PageView $page, array $data, array $aux, array $fileIds, array $elementIds,
        string $lang, string $editor, ?string $publishAt = null ) : Version
    {
        $version = self::newVersion( $page, $data, $aux, $fileIds, $elementIds, $lang, $editor, $publishAt );

        PageVariant::whereKey( $page->variant_id )->toBase()->update( ['latest_id' => $version->id] );
        $page->latest_id = $version->id;

        return $version;
    }


    /**
     * Creates a new version of the page with its file and element references.
     *
     * @param PageView $page Page of the version
     * @param array<string, mixed> $data Page data of the version
     * @param array<string, mixed> $aux Auxiliary data of the version like content, config and meta
     * @param array<string> $fileIds IDs of the files referenced by the version
     * @param array<string> $elementIds IDs of the shared elements referenced by the version
     * @param string $lang Language of the version
     * @param string $editor Editor name for the version
     * @param string|null $publishAt Date and time when the version is published or NULL for a draft
     * @return Version Created version
     */
    protected static function newVersion( PageView $page, array $data, array $aux, array $fileIds, array $elementIds,
        string $lang, string $editor, ?string $publishAt = null ) : Version
    {
        $version = $page->versions()->forceCreate( [
            'lang' => $lang,
            'data' => $data,
            'aux' => $aux,
            'editor' => $editor,
            'publish_at' => $publishAt,
        ] );

        if( !empty( $fileIds ) ) {
            $version->files()->attach( $fileIds );
        }

        if( !empty( $elementIds ) ) {
            $version->elements()->attach( $elementIds );
        }

        return $version;
    }


    /**
     * Returns the content elements or meta data without the IDs of the elements for comparison.
     *
     * @param mixed $value List or map of elements
     * @return array<int|string, mixed> Normalized elements
     */
    protected static function elements( mixed $value ) : array
    {
        $items = self::normalize( $value );

        return is_array( $items ) ? array_map( fn( $item ) => is_array( $item ) ? array_diff_key( $item, ['id' => null] ) : $item, $items ) : [];
    }


    /**
     * Converts the value to scalars and arrays with sorted keys, e.g. for values read from JSON columns.
     */
    public static function normalize( mixed $value ) : mixed
    {
        if( is_object( $value ) ) {
            $value = json_decode( (string) json_encode( $value ), true );
        }

        if( !is_array( $value ) ) {
            return is_scalar( $value ) ? (string) $value : $value;
        }

        $value = array_map( fn( $item ) => self::normalize( $item ), $value );

        if( !array_is_list( $value ) ) {
            ksort( $value );
        }

        return $value;
    }
}
