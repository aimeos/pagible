<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Aimeos\Cms\ContentAction;
use Aimeos\Cms\DB;
use Aimeos\Cms\Exception;
use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageAccess;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Resource\Pages;
use Aimeos\Cms\Resource\Paths;
use Aimeos\Cms\Resource\Refs;
use Aimeos\Cms\Scout;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Utils;
use Aimeos\Nestedset\NestedSet;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;


/**
 * Copies of pages including their sub-pages and language variants.
 *
 * @phpstan-type CopyRecords array{variant: array<string, mixed>, version: array<string, mixed>, refs: array<string, array<int, string>>}
 */
trait Copies
{
    /**
     * Copies a page and its sub-pages with all language variants.
     *
     * The latest version of each variant becomes the first version of its copy, element IDs
     * and hashes stay unchanged. Colliding paths get the language code appended. The access
     * restrictions are copied too, so restricted pages don't become public by copying them.
     *
     * @param string $id Page UUID
     * @param string|null $ref Sibling page ID to insert before
     * @param string|null $parent Parent page ID to append to
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return PageView Copy of the page in its source language
     */
    public static function copyPage( string $id, ?string $ref = null, ?string $parent = null, ?Authenticatable $user = null ) : PageView
    {
        $editor = Utils::editor( $user );

        // the copies are mostly inserted in bulk, so all of them are indexed once after the transaction including their access rules
        // the lock is released after copying, its lifetime only protects large subtrees until then
        [$copy, $ids] = Scout::mute( [PageView::class], fn() => DB::lockedTransaction(
            fn() => self::copyTree( $id, $ref, $parent, $editor, $user ), 300
        ) );

        Scout::index( PageView::class, $ids );

        return $copy;
    }


    /**
     * Copies the page and its sub-pages with all language variants.
     *
     * Only the copy of the root page is placed by the nested set, the sub-pages are inserted in
     * bulk into the gap opened once for the whole subtree.
     *
     * @param string $id Page UUID
     * @param string|null $ref Sibling page ID to insert before
     * @param string|null $parent Parent page ID to append to
     * @param string $editor Name of the editor
     * @param Authenticatable|null $user Authenticated user for the references of variants without versions
     * @return array{0: PageView, 1: array<int, string>} Copy of the root page and IDs of the copied pages
     */
    protected static function copyTree( string $id, ?string $ref, ?string $parent, string $editor, ?Authenticatable $user ) : array
    {
        [$rootId, $pages, $variants, $sizes] = self::copySubtree( $id );
        [$known, $loaded] = Paths::known( $variants->collapse() );
        $copy = null;
        $nodes = $next = $inserts = $rows = [];

        // the latest versions with the content are loaded and the copies inserted per chunk to limit the memory
        foreach( $pages->chunk( Base::CHUNK ) as $chunk )
        {
            $versions = self::copyVersions( $variants->only( $chunk->pluck( 'id' )->all() )->collapse() );

            foreach( $chunk as $orig )
            {
                $oid = (string) $orig->id;
                $list = $variants->get( $oid );

                // pages whose parent isn't copied would be placed at the target position otherwise
                if( !$list || !isset( $sizes[$oid] ) ) {
                    continue;
                }

                $page = null;

                // the source variant first because the page gets its language as source, otherwise the first one
                foreach( $list->sortBy( fn( $v ) => $v->lang !== $orig->source ) as $variant )
                {
                    [$data, $aux, $refs] = self::copyData( $variant, $versions->get( (string) $variant->latest_id ), $known, $loaded, $user );

                    if( !$page && $oid === $rootId )
                    {
                        [$page, $nodes[$oid]] = self::copyRoot( $variant, $data, $aux, $refs, $editor, $sizes[$oid], $ref, $parent );
                        $next[$oid] = (int) $nodes[$oid][NestedSet::LFT] + 1;
                        $copy = $page;
                        continue;
                    }

                    if( !$page ) {
                        $inserts[] = $nodes[$oid] = self::copyNode( $variant->lang, $oid, (string) $orig->parent_id, $sizes[$oid], $next, $nodes );
                    }

                    $rows[] = $row = self::copyRows( (string) $nodes[$oid]['id'], $variant, $data, $aux, $refs, $editor );

                    if( !$page )
                    {
                        $page = ( new PageView() )->newFromBuilder( ['id' => $nodes[$oid]['id'], 'latest_id' => $row['version']['id']] );
                        $page->setRelation( 'latest', ( new Version() )->newFromBuilder( $row['version'] ) );
                    }
                }

                $page?->announce( ContentAction::Added, $editor );
            }

            self::flushCopies( $inserts, $rows );
        }

        if( !$copy ) {
            throw new Exception( sprintf( 'Page "%1$s" has not been copied', $id ) );
        }

        $ids = array_map( fn( $node ) => (string) $node['id'], $nodes );

        self::copyAccess( $ids, $editor );

        return [$copy, array_values( $ids )];
    }


    /**
     * Copies the access restrictions of the pages to their copies.
     *
     * @param array<string, string> $ids IDs of the copied pages as values and the IDs of the original pages as keys
     * @param string $editor Name of the editor
     */
    protected static function copyAccess( array $ids, string $editor ) : void
    {
        $rows = [];
        $now = now()->startOfSecond();
        $tenant = Tenancy::value();

        foreach( array_chunk( array_keys( $ids ), PageAccess::CHUNK_SIZE ) as $chunk )
        {
            foreach( PageAccess::select( 'page_id', 'value' )->whereIn( 'page_id', $chunk )->toBase()->get() as $access )
            {
                $rows[] = [
                    'page_id' => $ids[(string) $access->page_id],
                    'tenant_id' => $tenant,
                    'value' => (string) $access->value,
                    'editor' => $editor,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::insertRows( PageAccess::query()->toBase(), $rows );
    }


    /**
     * Returns the records of the page variant copy and its first version.
     *
     * @param string $pageId ID of the copied page
     * @param PageVariant $variant Original page variant
     * @param array<string, mixed> $data Version data of the copied variant
     * @param array<string, mixed> $aux Content, meta and config of the copied variant
     * @param array<string, array<int, string>> $refs File and element IDs referenced by the version
     * @param string $editor Name of the editor
     * @return CopyRecords Records to insert
     */
    protected static function copyRows( string $pageId, PageVariant $variant, array $data, array $aux, array $refs, string $editor ) : array
    {
        $tenant = Tenancy::value();
        $copy = Pages::variantRow( $pageId, ['status' => 0] + Pages::variantData( $data ) + [
            'hashes' => (array) $variant->hashes,
            'stale' => (bool) $variant->stale,
            'editor' => $editor,
        ] );

        // same as the "saving" event of the versions which isn't fired for bulk inserts
        $version = ( new Version() )->forceFill( [
            'tenant_id' => $tenant,
            'versionable_type' => PageVariant::class,
            'versionable_id' => $copy['id'],
            'lang' => $data['lang'] ?? $variant->lang,
            'data' => array_replace( $data, ['scheduled' => 0] ),
            'aux' => $aux,
            'editor' => $editor,
        ] );
        $version->setUniqueIds();
        $version->setCreatedAt( $version->freshTimestamp() );

        $copy['latest_id'] = $version->id;

        return ['variant' => $copy, 'version' => $version->getAttributes(), 'refs' => array_filter( $refs )];
    }


    /**
     * Returns the record of a copied page inserted into the gap of the copied subtree.
     *
     * @param string $lang Source language of the copied page
     * @param string $id ID of the original page
     * @param string $pid ID of the original parent page
     * @param int $size Number of copied pages in the subtree of the page including itself
     * @param array<string, int> $next Next free left values by original page ID, updated for the page and its parent
     * @param array<string, array<string, mixed>> $nodes Records of the copied pages by original page ID
     * @return array<string, mixed> Record of the page tree
     */
    protected static function copyNode( string $lang, string $id, string $pid, int $size, array &$next, array $nodes ) : array
    {
        $parent = $nodes[$pid];
        $lft = $next[$pid];
        $next[$pid] = $lft + 2 * $size;
        $next[$id] = $lft + 1;

        $node = ( new Page() )->forceFill( [
            'id' => ( new Page() )->newUniqueId(),
            'tenant_id' => Tenancy::value(),
            'source' => $lang,
        ] );
        $node->rawNode( $lft, $lft + 2 * $size - 1, (string) $parent['id'], (int) $parent[NestedSet::DEPTH] + 1 );
        $node->updateTimestamps();

        return $node->getAttributes();
    }


    /**
     * Returns the version data, content and references of the copy of a page variant.
     *
     * @param PageVariant $variant Original page variant
     * @param Version|null $version Latest version of the variant with its file and element references
     * @param array<string, array<string, bool>> $known Used paths by domain, updated with the path of the copy
     * @param array<string, array<string, string>> $loaded Languages by domain and path whose candidates are all in $known
     * @param Authenticatable|null $user Authenticated user for the references of variants without versions
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<string, array<int, string>>} Data, aux and references
     */
    protected static function copyData( PageVariant $variant, ?Version $version, array &$known, array $loaded, ?Authenticatable $user ) : array
    {
        $data = (array) ( $version->data ?? $variant->only( PageVariant::DATA_FIELDS ) );
        $aux = (array) ( $version->aux ?? self::variantAux( (string) $variant->id ) );
        $domain = (string) ( $data['domain'] ?? $variant->domain );

        $data['lang'] = $variant->lang;
        $data['domain'] = $domain;
        $data['path'] = Paths::unique( $domain, (string) ( $data['path'] ?? $variant->path ), $variant->lang, $known, $loaded );

        $refs = $version ? [
            'files' => $version->files->pluck( 'id' )->all(),
            'elements' => $version->elements->pluck( 'id' )->all(),
        ] : Refs::page( $aux, $user );

        return [$data, $aux, $refs];
    }


    /**
     * Inserts the copy of the root page at the target position and opens the gap for its copied sub-pages.
     *
     * @param PageVariant $variant Source variant of the root page
     * @param array<string, mixed> $data Version data of the copied variant
     * @param array<string, mixed> $aux Content, meta and config of the copied variant
     * @param array<string, array<int, string>> $refs File and element IDs referenced by the version
     * @param string $editor Name of the editor
     * @param int $size Number of copied pages in the subtree including the root page
     * @param string|null $ref Sibling page ID to insert before
     * @param string|null $parent Parent page ID to append to
     * @return array{0: PageView, 1: array<string, mixed>} Copied page and the record of its node
     */
    protected static function copyRoot( PageVariant $variant, array $data, array $aux, array $refs, string $editor,
        int $size, ?string $ref, ?string $parent ) : array
    {
        $page = new PageView();
        // copies are disabled until published because the live columns come from the draft
        $page->forceFill( ['status' => 0] + Pages::variantData( $data ) + [
            'source' => $variant->lang,
            'hashes' => $variant->hashes,
            'stale' => $variant->stale,
            'editor' => $editor,
        ] );

        $node = Pages::create( $page, $ref, $parent );

        if( $size > 1 && ( $rgt = $node->getRgt() ) !== null )
        {
            $node->newNestedSetQuery()->makeGap( $rgt, 2 * ( $size - 1 ) );
            $node->refreshNode();
            $page->synced( $node );
        }

        return [Pages::addVersion( $page, $data, $aux, $editor, $refs ), $node->getAttributes()];
    }


    /**
     * Inserts the copied pages, their variants, versions and the references of the versions and resets the lists.
     *
     * @param array<int, array<string, mixed>> $nodes Records of the copied pages
     * @param array<int, CopyRecords> $rows Records of the copied variants
     */
    protected static function flushCopies( array &$nodes, array &$rows ) : void
    {
        if( empty( $rows ) ) {
            return;
        }

        $pivots = [];
        $version = new Version();

        foreach( $rows as $row )
        {
            foreach( $row['refs'] as $relation => $ids )
            {
                $rel = $version->reference( $relation );

                foreach( array_unique( $ids ) as $refId ) {
                    $pivots[$rel->getTable()][] = [$rel->getForeignPivotKeyName() => $row['version']['id'], $rel->getRelatedPivotKeyName() => $refId];
                }
            }
        }

        // the pages first because of the foreign keys of the variants and versions before their references
        DB::insertRows( Page::withoutGlobalScopes()->toBase(), $nodes );
        DB::insertRows( Version::query()->toBase(), array_column( $rows, 'version' ) );
        DB::insertRows( PageVariant::query()->toBase(), array_column( $rows, 'variant' ) );

        foreach( $pivots as $table => $list ) {
            DB::insertRows( $version->getConnection()->table( $table ), $list );
        }

        $nodes = $rows = [];
    }


    /**
     * Loads the pages and variants of the subtree and the number of copied pages per page.
     *
     * @param string $id ID of the root page of the subtree
     * @return array{0: string, 1: Collection<int, PageView>, 2: Collection<array-key, Collection<int, PageVariant>>, 3: array<string, int>} Root ID, pages in tree order, variants by page ID and copy sizes
     * @throws Exception If the root page has no variants
     */
    protected static function copySubtree( string $id ) : array
    {
        $root = PageView::select( 'id', 'tenant_id', NestedSet::LFT, NestedSet::RGT )->findOrFail( $id );
        $rootId = (string) $root->id;

        $pages = Pages::views( PageView::select( 'id', 'parent_id', 'source', NestedSet::LFT, NestedSet::RGT, NestedSet::DEPTH )
            ->where( NestedSet::LFT, '>=', $root->getLft() )
            ->where( NestedSet::RGT, '<=', $root->getRgt() )
            ->orderBy( NestedSet::LFT )
            ->get() );

        PageView::checkBulk( $pages->count() );

        $variants = PageVariant::select( ['id', 'page_id', 'lang', 'latest_id', 'hashes', 'stale', ...PageVariant::DATA_FIELDS] )
            ->whereIn( 'page_id', $pages->pluck( 'id' )->all() )
            ->orderBy( 'lang' )->get();

        $sizes = self::copySizes( $pages, $variants->pluck( 'page_id', 'page_id' )->all(), $rootId );

        if( !isset( $sizes[$rootId] ) ) {
            throw new Exception( sprintf( 'Page "%1$s" has no variants', $id ) );
        }

        return [$rootId, $pages, $variants->toBase()->groupBy( 'page_id' ), $sizes];
    }


    /**
     * Returns the number of copied pages in the subtree of each page copied below the root page.
     *
     * Pages are only copied if they have variants and their parent page is copied too.
     *
     * @param Collection<int, PageView> $pages Pages of the subtree in tree order
     * @param array<string, mixed> $available IDs of the pages with variants as keys
     * @param string $rootId ID of the root page of the subtree
     * @return array<string, int> Number of copied pages including the page itself by page ID
     */
    protected static function copySizes( Collection $pages, array $available, string $rootId ) : array
    {
        $sizes = [];

        foreach( $pages as $page )
        {
            $id = (string) $page->id;

            if( isset( $available[$id] ) && ( $id === $rootId || isset( $sizes[(string) $page->parent_id] ) ) ) {
                $sizes[$id] = 1;
            }
        }

        foreach( $pages->reverse() as $page )
        {
            $id = (string) $page->id;

            if( $id !== $rootId && isset( $sizes[$id], $sizes[(string) $page->parent_id] ) ) {
                $sizes[(string) $page->parent_id] += $sizes[$id];
            }
        }

        return $sizes;
    }


    /**
     * Returns the latest versions of the page variants with their file and element references.
     *
     * @param Collection<int, PageVariant> $variants Page variants
     * @return Collection<string, Version> Versions by ID
     */
    protected static function copyVersions( Collection $variants ) : Collection
    {
        $versions = [];

        foreach( $variants->pluck( 'latest_id' )->filter()->chunk( DB::IN_SIZE ) as $chunk )
        {
            foreach( Version::with( ['files:id', 'elements:id'] )->whereIn( 'id', $chunk->all() )->get() as $version ) {
                $versions[(string) $version->id] = $version;
            }
        }

        return collect( $versions );
    }


    /**
     * Returns the content, meta and config of a page variant without versions.
     *
     * @param string $id Page variant ID
     * @return array<string, mixed> Content, meta and config data
     */
    protected static function variantAux( string $id ) : array
    {
        $variant = PageVariant::select( 'id', 'content', 'meta', 'config' )->findOrFail( $id );
        return ['content' => $variant->content, 'meta' => $variant->meta, 'config' => $variant->config];
    }

}
