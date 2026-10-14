<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Resource;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Query\PageBuilder;
use Aimeos\Cms\Tenancy;
use Illuminate\Support\Collection;


/**
 * Page writes of the resource methods.
 *
 * The resource methods work with the read-only PageView models, these helpers
 * write their values to the Page and PageVariant models and keep the page
 * views in sync with the stored values.
 *
 * @internal
 */
final class Pages
{
    /**
     * Inserts the new page and its variant into the page tree.
     *
     * @param PageView $page New page with the values of the variant
     * @param string|null $ref Sibling page ID to insert before
     * @param string|null $parent Parent page ID to append to, as root page if both are NULL
     * @return Page Inserted page
     */
    public static function create( PageView $page, ?string $ref, ?string $parent ) : Page
    {
        $page->setUniqueIds();

        $node = new Page( ['source' => $page->getAttribute( 'source' ) ?: (string) $page->getAttribute( 'lang' )] );
        $node->setAttribute( 'id', $page->getKey() );

        self::position( $node, $ref, $parent )->save();

        // saved as model to update the search index
        $variant = ( new PageVariant() )->setRawAttributes( self::variantRow( (string) $node->id, PageBuilder::variantValues( $page->getAttributes() ), raw: true ) );
        $node->variants()->save( $variant );

        $page->syncChanges();
        $page->synced( $node, $variant )->syncOriginal();
        $page->exists = true;
        $page->wasRecentlyCreated = true;

        return $node;
    }


    /**
     * Changes the page of the page view and takes over the stored tree columns.
     *
     * The page is created from the page view without querying the database.
     *
     * @param PageView $page Existing page view
     * @param \Closure(Page): mixed $change Writes the page, e.g. by moving, trashing or restoring it
     * @return Page Saved page
     */
    public static function saveNode( PageView $page, \Closure $change ) : Page
    {
        $node = $page->toPage();

        $change( $node );
        $page->synced( $node );

        return $node;
    }


    /**
     * Sets the position of the page in the page tree.
     *
     * @param Page $node New or existing page
     * @param string|null $ref Sibling page ID to insert before
     * @param string|null $parent Parent page ID to append to, as root page if both are NULL
     * @return Page Same page with the pending position
     */
    public static function position( Page $node, ?string $ref, ?string $parent ) : Page
    {
        if( $ref !== null ) {
            $node->beforeNode( Page::withTrashed()->findOrFail( $ref ) );
        } elseif( $parent !== null ) {
            $node->appendToNode( Page::withTrashed()->findOrFail( $parent ) );
        } elseif( $node->exists ) {
            $node->makeRoot();
        }

        return $node;
    }


    /**
     * Saves the changed variant values of the page view to its variant.
     *
     * @param PageView $page Existing page view
     * @param array<string, mixed> $values Additional column/value pairs of the page view to save
     * @return PageView Same page view with the saved values
     */
    public static function saveVariant( PageView $page, array $values = [] ) : PageView
    {
        $page->forceFill( $values );

        $dirty = $page->getDirty();

        if( empty( PageBuilder::variantValues( $dirty ) ) ) {
            return $page;
        }

        $original = $page->getRawOriginal();

        $variant = $page->toVariant();
        $variant->save();

        // the source language follows the language of the source variant
        if( isset( $dirty['lang'] ) && ( $original['lang'] ?? null ) === ( $original['source'] ?? null ) ) {
            $page->setAttribute( 'source', $dirty['lang'] );
        }

        $page->setAttribute( 'updated_at', $variant->getAttributes()['updated_at'] ?? null );
        $page->syncChanges();
        $page->syncOriginal();

        return $page;
    }


    /**
     * Returns the complete record of a new page variant for inserting it.
     *
     * Missing values are set to the defaults of the variants, a new unique ID,
     * the current tenant and the current time. NULL values are skipped.
     *
     * @param string $pageId ID of the page the variant belongs to
     * @param array<string, mixed> $values Attribute values which are converted like in the PageVariant model
     * @param bool $raw TRUE if the values are column values as stored in the database
     * @return array<string, mixed> Record of the page variant
     */
    public static function variantRow( string $pageId, array $values, bool $raw = false ) : array
    {
        $values = array_filter( $values, fn( $v ) => $v !== null );

        $variant = new PageVariant();
        $variant->setRawAttributes( array_replace( $variant->getAttributes(), ['tenant_id' => Tenancy::value()], $raw ? $values : [] ) );
        $variant->forceFill( ( $raw ? [] : $values ) + ['page_id' => $pageId] );

        if( !$variant->getKey() ) {
            $variant->setAttribute( 'id', $variant->newUniqueId() );
        }

        if( !$variant->getAttribute( 'created_at' ) ) {
            $variant->setCreatedAt( $variant->freshTimestamp() );
        }

        if( !$variant->getAttribute( 'updated_at' ) ) {
            $variant->setUpdatedAt( $variant->freshTimestamp() );
        }

        return $variant->getAttributes();
    }


    /**
     * Returns the language and the variant attributes contained in the page data.
     *
     * @param array<string, mixed> $data Page data
     * @return array<string, mixed> Language and variant attribute values
     */
    public static function variantData( array $data ) : array
    {
        return array_intersect_key( $data, array_flip( ['lang', ...PageVariant::DATA_FIELDS] ) );
    }


    /**
     * Adds a new version to the page variant and saves the page.
     *
     * @param PageView $page Page with the variant the version belongs to
     * @param array<string, mixed> $data Version data
     * @param array<string, mixed> $aux Content, meta and config
     * @param string $editor Name of the editing user
     * @param array<string, array<string>>|null $refs File and element references or NULL to copy the ones of the previous version
     * @param array<string, mixed>|null $diffs Merge differences for the change info
     * @param string|null $lang Language of the version, the one of the data or the page by default
     * @return PageView Page with the new version as "latest"
     */
    public static function addVersion( PageView $page, array $data, array $aux, string $editor,
        ?array $refs, ?array $diffs = null, ?string $lang = null ) : PageView
    {
        $page->newDraft( [
            'data' => $data,
            'lang' => $lang ?? $data['lang'] ?? $page->lang,
            'editor' => $editor,
            'aux' => $aux,
        ], $refs, $diffs );

        return self::saveVariant( $page );
    }


    /**
     * Returns the pages of the list.
     *
     * @param iterable<mixed> $items List of models
     * @return Collection<int, PageView> List of pages
     */
    public static function views( iterable $items ) : Collection
    {
        $list = [];

        foreach( $items as $item ) {
            if( $item instanceof PageView ) {
                $list[] = $item;
            }
        }

        return collect( $list );
    }
}
