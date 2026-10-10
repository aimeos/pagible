<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageNode;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Query\PageBuilder;
use Aimeos\Cms\Tenancy;


/**
 * Writes of the pages.
 *
 * The Page model is a read-only facade joining the page tree and the variants,
 * so all page writes go through these methods. They write the tree columns
 * through the PageNode model and the language specific columns through the
 * PageVariant model. Search indexes are updated like for saved models unless
 * syncing to the search index is disabled.
 */
trait Pages
{
    /**
     * Inserts a new page and its variant into the page tree.
     *
     * @param Page $page New page with the values of the variant
     * @param string|null $ref Sibling page ID to insert before
     * @param string|null $parent Parent page ID to append to, as root page if both are NULL
     * @return Page Inserted page including its position in the page tree
     */
    public static function insertPage( Page $page, ?string $ref = null, ?string $parent = null ) : Page
    {
        self::createPage( $page, fn( PageNode $node ) => self::position( $node, $ref, $parent ) );
        return $page;
    }


    /**
     * Inserts the pages in bulk without updating the page tree or the search index.
     *
     * @param array<int, array<string, mixed>> $rows Column/value pairs of the page facade per page including the tree columns
     */
    public static function insertPages( array $rows ) : void
    {
        $nodes = $variants = [];

        foreach( $rows as $row )
        {
            [$node, $variant] = self::splitPage( $row );

            $node['source'] ??= $variant['lang'] ?? '';

            $nodes[] = $node;
            $variants[] = self::variantRow( (string) $node['id'], $variant + ['tenant_id' => $node['tenant_id'] ?? ''] );
        }

        self::insertRows( PageNode::withoutGlobalScopes()->toBase(), $nodes );
        self::insertRows( PageVariant::withoutGlobalScopes()->toBase(), $variants );
    }


    /**
     * Moves the page and its descendants to a new position in the page tree.
     *
     * @param Page $page Existing page
     * @param string|null $ref Sibling page ID to insert before
     * @param string|null $parent Parent page ID to append to, as root page if both are NULL
     * @return Page Same page with its new position
     */
    public static function placePage( Page $page, ?string $ref = null, ?string $parent = null ) : Page
    {
        $node = self::node( $page );
        self::position( $node, $ref, $parent );
        $node->save();

        self::syncNode( $page, $node );
        return $page;
    }


    /**
     * Permanently deletes the page, its descendants and all their variants and versions.
     *
     * @param Page $page Existing page
     */
    public static function removePage( Page $page ) : void
    {
        // positions of loaded pages are outdated if other pages were removed before
        // and pages removed together with an ancestor don't exist anymore
        if( !( $node = PageNode::withTrashed()->find( $page->id ) ) )
        {
            $page->exists = false;
            return;
        }

        self::removeVariants( $node->newNestedSetQuery()->whereDescendantOrSelf( $node )->pluck( 'id' )->all() );
        $node->forceDelete();

        $page->exists = false;
        self::fire( $page, 'forceDeleted' );
    }


    /**
     * Permanently deletes the pages including all their variants and versions.
     *
     * The pages are removed without updating the page tree or the search index,
     * so the IDs must include all descendants or the whole page tree.
     *
     * @param array<int, mixed> $ids Page IDs
     */
    public static function removePages( array $ids ) : void
    {
        self::removeVariants( $ids );

        foreach( array_chunk( array_values( array_unique( $ids ) ), 500 ) as $chunk ) {
            PageNode::withoutGlobalScopes()->whereIn( 'id', $chunk )->toBase()->delete();
        }
    }


    /**
     * Splits the page values into the values of the page tree and the variant.
     *
     * @param array<string, mixed> $values Column/value pairs of the page facade
     * @return array{0: array<string, mixed>, 1: array<string, mixed>} Values of the page tree and of the variant
     * @throws \InvalidArgumentException If a column is unknown
     */
    public static function splitPage( array $values ) : array
    {
        $node = $variant = [];

        foreach( $values as $key => $value )
        {
            if( in_array( $key, PageBuilder::PAGE_COLUMNS, true ) ) {
                $node[$key] = $value;
            } elseif( in_array( $key, PageBuilder::SHARED_COLUMNS, true ) ) {
                $node[$key] = $variant[$key] = $value;
            } elseif( isset( PageBuilder::VARIANT_COLUMNS[$key] ) ) {
                $variant[PageBuilder::VARIANT_COLUMNS[$key]] = $value;
            } else {
                throw new \InvalidArgumentException( sprintf( 'Unknown page column "%1$s"', $key ) );
            }
        }

        return [$node, $variant];
    }


    /**
     * Moves the page and its descendants into the trash, the variants keep their own trash state.
     *
     * @param Page $page Existing page
     * @return Page Same page in the trash
     */
    public static function trashPage( Page $page ) : Page
    {
        $node = self::node( $page );
        $node->delete();

        self::syncNode( $page, $node );
        self::fire( $page, 'deleted' );

        return $page;
    }


    /**
     * Restores the page and the descendants moved into the trash together with the page.
     *
     * Other changed values of the page, e.g. the editor, are saved too.
     *
     * @param Page $page Existing page
     * @return Page Same page restored from the trash
     */
    public static function untrashPage( Page $page ) : Page
    {
        $node = self::node( $page );
        $node->restore();

        self::syncNode( $page, $node );
        self::updatePage( $page );

        self::fire( $page, 'restored' );

        return $page;
    }


    /**
     * Saves the changed values of an existing page.
     *
     * The tree columns are only written if the structure changes, e.g. the source language,
     * and the variant columns if the values of the variant change.
     *
     * @param Page $page Existing page
     * @param array<string, mixed> $values Additional column/value pairs of the page facade to save
     * @return Page Same page with the saved values
     */
    public static function updatePage( Page $page, array $values = [] ) : Page
    {
        if( !$page->exists ) {
            throw new \LogicException( 'Insert new pages using Resource::insertPage()' );
        }

        $page->forceFill( $values );

        if( !$page->isDirty() ) {
            return $page;
        }

        if( $page->usesTimestamps() ) {
            $page->updateTimestamps();
        }

        $original = $page->getRawOriginal();
        [$node, $variant] = self::splitPage( $page->getDirty() );

        // the source language follows the language of the source variant
        if( isset( $variant['lang'] ) && ( $original['lang'] ?? null ) === ( $original['source'] ?? $page->getAttributes()['source'] ?? null ) ) {
            $node['source'] = $variant['lang'];
            $page->forceFill( ['source' => $variant['lang']] );
        }

        $shared = array_flip( PageBuilder::SHARED_COLUMNS );
        $structure = !empty( array_diff_key( $node, $shared ) );

        // timestamps of the tree only change if the structure changes and the ones of the variant if its content changes
        if( $structure ) {
            PageNode::withoutGlobalScopes()->whereKey( $page->getKey() )->toBase()->update( $node );
        }

        if( !empty( array_diff_key( $variant, $shared ) ) || !$structure ) {
            self::variantQuery( $page )->update( $variant );
        }

        self::fire( $page, 'saved' );

        $page->syncChanges();
        $page->syncOriginal();

        return $page;
    }


    /**
     * Inserts a new page whose position in the page tree is set by the given function.
     *
     * @param Page $page New page with the values of the variant
     * @param \Closure(PageNode): mixed $place Sets the position of the node in the page tree
     * @return PageNode Inserted node of the page
     */
    protected static function createPage( Page $page, \Closure $place ) : PageNode
    {
        if( $page->exists ) {
            throw new \LogicException( 'Save existing pages using Resource::updatePage()' );
        }

        $page->setUniqueIds();

        if( $page->usesTimestamps() ) {
            $page->updateTimestamps();
        }

        $page->forceFill( [
            'tenant_id' => Tenancy::value(),
            'source' => $page->getAttribute( 'source' ) ?: (string) $page->getAttribute( 'lang' ),
        ] );

        $node = self::node( $page );
        $place( $node );
        $node->save();

        $page->setRawAttributes( array_replace( $page->getAttributes(), $node->getAttributes() ) );

        [, $variant] = self::splitPage( $page->getAttributes() );

        PageVariant::withoutGlobalScopes()->toBase()->insert( self::variantRow( (string) $page->getKey(), $variant ) );

        $page->exists = true;
        $page->wasRecentlyCreated = true;

        self::fire( $page, 'saved' );

        $page->syncChanges();
        $page->syncOriginal();

        return $node;
    }


    /**
     * Fires the model event of the page, e.g. to update the search index.
     *
     * @param Page $page Written page
     * @param string $event Event name like "saved" or "deleted"
     */
    protected static function fire( Page $page, string $event ) : void
    {
        $page->getEventDispatcher()?->dispatch( 'eloquent.' . $event . ': ' . $page::class, $page );
    }


    /**
     * Returns the node of the page in the page tree.
     *
     * @param Page $page New or existing page
     * @return PageNode Node of the page including unsaved changes of the tree columns
     */
    protected static function node( Page $page ) : PageNode
    {
        $cols = array_flip( PageNode::COLUMNS );
        $node = new PageNode();

        if( !$page->exists ) {
            return $node->setRawAttributes( array_intersect_key( $page->getAttributes(), $cols ) );
        }

        $node = $node->newFromBuilder( array_intersect_key( $page->getRawOriginal(), $cols ) );
        return $node->setRawAttributes( array_replace( $node->getAttributes(), array_intersect_key( $page->getDirty(), $cols ) ) );
    }


    /**
     * Sets the position of the node in the page tree.
     *
     * @param PageNode $node New or existing node
     * @param string|null $ref Sibling page ID to insert before
     * @param string|null $parent Parent page ID to append to, as root page if both are NULL
     */
    protected static function position( PageNode $node, ?string $ref = null, ?string $parent = null ) : void
    {
        if( $ref !== null ) {
            $node->beforeNode( PageNode::withTrashed()->findOrFail( $ref ) );
        } elseif( $parent !== null ) {
            $node->appendToNode( PageNode::withTrashed()->findOrFail( $parent ) );
        } elseif( $node->exists ) {
            $node->makeRoot();
        }
    }


    /**
     * Permanently deletes all variants of the pages and their versions.
     *
     * Uses ID lists instead of subqueries because older MySQL/MariaDB versions
     * evaluate IN subqueries of single table deletes for each row.
     *
     * @param array<int, mixed> $ids Page IDs
     */
    protected static function removeVariants( array $ids ) : void
    {
        foreach( array_chunk( array_values( array_unique( $ids ) ), 500 ) as $chunk )
        {
            $variantIds = PageVariant::withoutGlobalScopes()->whereIn( 'page_id', $chunk )->pluck( 'id' )->all();

            foreach( array_chunk( $variantIds, 500 ) as $vchunk )
            {
                Version::withoutGlobalScopes()->where( 'versionable_type', PageVariant::class )->whereIn( 'versionable_id', $vchunk )->toBase()->delete();
                PageVariant::withoutGlobalScopes()->whereIn( 'id', $vchunk )->toBase()->delete();
            }
        }
    }


    /**
     * Takes over the tree columns stored by the node of the page.
     *
     * @param Page $page Page to update
     * @param PageNode $node Saved node of the page
     */
    protected static function syncNode( Page $page, PageNode $node ) : void
    {
        $values = array_intersect_key( $node->getAttributes(), array_flip( PageNode::COLUMNS ) );

        $page->setRawAttributes( array_replace( $page->getAttributes(), $values ) );
        $page->syncOriginalAttributes( array_keys( $values ) );
    }


    /**
     * Returns the query for the variant of the page.
     *
     * @param Page $page Existing page
     * @return \Illuminate\Database\Query\Builder Query for the variant row
     */
    protected static function variantQuery( Page $page ) : \Illuminate\Database\Query\Builder
    {
        $query = PageVariant::withoutGlobalScopes()->toBase();
        $original = $page->getRawOriginal();

        if( $id = $original['variant_id'] ?? $page->getAttributes()['variant_id'] ?? null ) {
            return $query->where( 'id', (string) $id );
        }

        $query->where( 'page_id', $page->getKey() );

        if( $lang = $original['lang'] ?? null ) {
            return $query->where( 'lang', (string) $lang );
        }

        return $query->whereIn( 'lang', PageNode::withoutGlobalScopes()->whereKey( $page->getKey() )->select( 'source' )->toBase() );
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
