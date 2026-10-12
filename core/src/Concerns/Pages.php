<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Query\PageBuilder;
use Aimeos\Cms\Tenancy;


/**
 * Page writes of the resource methods.
 *
 * The resource methods work with the read-only PageView models, these helpers
 * write their values to the Page and PageVariant models and keep the page
 * views in sync with the stored values.
 */
trait Pages
{
    /**
     * Inserts the new page and its variant into the page tree.
     *
     * @param PageView $page New page with the values of the variant
     * @param string|null $ref Sibling page ID to insert before
     * @param string|null $parent Parent page ID to append to, as root page if both are NULL
     * @return Page Inserted page
     */
    protected static function createPage( PageView $page, ?string $ref = null, ?string $parent = null ) : Page
    {
        $page->setUniqueIds();

        $node = new Page( ['source' => $page->getAttribute( 'source' ) ?: (string) $page->getAttribute( 'lang' )] );
        $node->setAttribute( 'id', $page->getKey() );

        self::position( $node, $ref, $parent )->save();

        // saved as model to update the search index
        $variant = ( new PageVariant() )->setRawAttributes( self::variantRow( (string) $node->id, PageBuilder::variantValues( $page->getAttributes() ) ) );
        $node->variants()->save( $variant );

        $page->setRawAttributes( array_replace( $page->getAttributes(), $node->getAttributes(), [
            'variant_id' => $variant->id,
            'created_at' => $variant->getAttributes()['created_at'] ?? null,
            'updated_at' => $variant->getAttributes()['updated_at'] ?? null,
        ] ) );

        $page->exists = true;
        $page->wasRecentlyCreated = true;
        $page->syncChanges();
        $page->syncOriginal();

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
    protected static function saveNode( PageView $page, \Closure $change ) : Page
    {
        $node = ( new Page() )->newFromBuilder( array_intersect_key( $page->getRawOriginal(), array_flip( Page::COLUMNS ) ) );
        $node->setRawAttributes( array_replace( $node->getAttributes(), array_intersect_key( $page->getDirty(), array_flip( PageBuilder::PAGE_COLUMNS ) ) ) );

        $change( $node );
        self::syncNode( $page, $node );

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
    protected static function position( Page $node, ?string $ref = null, ?string $parent = null ) : Page
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
    protected static function saveVariant( PageView $page, array $values = [] ) : PageView
    {
        $page->forceFill( $values );

        $dirty = $page->getDirty();

        if( empty( PageBuilder::variantValues( $dirty ) ) ) {
            return $page;
        }

        $dirty = PageBuilder::variantValues( $dirty, true );

        $original = $page->getRawOriginal();

        // without the variant ID, the update wouldn't change any row
        if( empty( $original['variant_id'] ) ) {
            throw new \LogicException( sprintf( 'Page "%s" must be loaded with its variant ID to be saved', $page->getKey() ) );
        }

        $variant = ( new PageVariant() )->newFromBuilder( PageBuilder::variantValues( $original ) + ['page_id' => $page->getKey()] );
        $variant->setRawAttributes( array_replace( $variant->getAttributes(), $dirty ) );
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
     * Takes over the tree columns stored by the page.
     *
     * @param PageView $page Page view to update
     * @param Page $node Saved page
     */
    protected static function syncNode( PageView $page, Page $node ) : void
    {
        $values = array_intersect_key( $node->getAttributes(), array_flip( PageBuilder::PAGE_COLUMNS ) );

        $page->setRawAttributes( array_replace( $page->getAttributes(), $values ) );
        $page->syncOriginalAttributes( array_keys( $values ) );
    }



    /**
     * Inserts the records in chunks which stay below the parameter limits of the databases.
     *
     * @param \Illuminate\Database\Query\Builder $query Query builder for the table
     * @param array<int, array<string, mixed>> $records Records to insert
     */
    protected static function insertRows( \Illuminate\Database\Query\Builder $query, array $records ) : void
    {
        $groups = [];

        // bulk inserts require the same columns in each record
        foreach( $records as $record )
        {
            ksort( $record );
            $groups[implode( ',', array_keys( $record ) )][] = $record;
        }

        foreach( $groups as $list )
        {
            // SQL Server allows up to 2100 parameters per statement
            foreach( array_chunk( $list, max( 1, intdiv( 2000, count( $list[0] ) ) ) ) as $chunk ) {
                ( clone $query )->insert( $chunk );
            }
        }
    }


    /**
     * Returns the complete record of a new page variant for inserting it.
     *
     * Missing values are set to the defaults of the variants, a new unique ID,
     * the current tenant and the current time. NULL values are skipped.
     *
     * @param string $pageId ID of the page the variant belongs to
     * @param array<string, mixed> $raw Column values as stored in the database
     * @param array<string, mixed> $values Attribute values which are converted like in the PageVariant model
     * @return array<string, mixed> Record of the page variant
     */
    protected static function variantRow( string $pageId, array $raw, array $values = [] ) : array
    {
        $variant = new PageVariant();
        $variant->setRawAttributes( array_replace( $variant->getAttributes(), ['tenant_id' => Tenancy::value()],
            array_filter( $raw, fn( $v ) => $v !== null ) ) );
        $variant->forceFill( array_filter( $values, fn( $v ) => $v !== null ) + ['page_id' => $pageId] );

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
}
