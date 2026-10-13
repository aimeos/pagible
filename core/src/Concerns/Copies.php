<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Aimeos\Cms\Exception;
use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageAccess;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Scout;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Utils;
use Aimeos\Nestedset\NestedSet;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;


/**
 * Copies of pages including their sub-pages and language variants.
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

        [$copy, $indexed, $restricted] = Utils::lockedTransaction(
            fn() => self::copyTree( $id, $ref, $parent, $editor, $user ),
            self::copyLifetime( $id )
        );

        // the variants inserted in bulk aren't indexed by saving the models
        if( $indexed && Scout::usesSearchIndex() ) {
            Scout::index( PageView::class, $indexed );
        }

        // external search engines store if pages are restricted, which was unknown when the copied page was saved
        if( Scout::usesExternalSearch() ) {
            Scout::reindex( PageView::class, array_diff( $restricted, $indexed ) );
        }

        return $copy;
    }


    /**
     * Returns the lifetime of the lock which must last until all pages of the subtree are copied.
     *
     * The copied root page takes about 0.05s and each other variant about 0.01s because they are inserted in bulk.
     *
     * @param string $id Page UUID
     * @return int Lifetime in seconds
     */
    protected static function copyLifetime( string $id ) : int
    {
        $node = PageView::select( 'id', NestedSet::LFT, NestedSet::RGT )->findOrFail( $id );
        $variants = PageVariant::whereIn( 'page_id', fn( $q ) => $q->select( 'id' )->from( 'cms_pages' )
            ->where( 'tenant_id', Tenancy::value() )
            ->whereBetween( NestedSet::LFT, [$node->getLft(), $node->getRgt()] )
        )->count();

        return (int) ceil( 1 + $variants / 100 );
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
     * @return array{0: PageView, 1: array<int, string>, 2: array<int, string>} Copy of the root page, IDs of the copies
     *  inserted in bulk which must be indexed and IDs of the copies which are restricted
     */
    protected static function copyTree( string $id, ?string $ref, ?string $parent, string $editor, ?Authenticatable $user ) : array
    {
        /** @var PageView $root */
        $root = PageView::select( 'id', 'tenant_id', NestedSet::LFT, NestedSet::RGT )->findOrFail( $id );
        $rootId = (string) $root->id;

        $pages = PageView::select( 'id', 'parent_id', 'source', NestedSet::LFT, NestedSet::RGT, NestedSet::DEPTH )
            ->where( NestedSet::LFT, '>=', $root->getLft() )
            ->where( NestedSet::RGT, '<=', $root->getRgt() )
            ->orderBy( NestedSet::LFT )
            ->get();

        PageView::checkBulk( $pages->count() );

        $variants = PageVariant::select( ['id', 'page_id', 'lang', 'latest_id', 'hashes', 'stale', ...PageVariant::FIELDS] )
            ->whereIn( 'page_id', $pages->pluck( 'id' )->all() )
            ->orderBy( 'lang' )->get();

        $sizes = self::copySizes( self::pages( $pages ), $variants->pluck( 'page_id', 'page_id' )->all(), $rootId );

        if( !isset( $sizes[$rootId] ) ) {
            throw new Exception( sprintf( 'Page "%1$s" has no variants', $id ) );
        }

        $variants = $variants->toBase()->groupBy( 'page_id' );
        [$known, $loaded] = self::knownPaths( $variants->collapse() );
        $copy = null;
        $nodes = $next = $inserts = $rows = [];
        $bulk = false;

        // the latest versions with the content are loaded and the copies inserted per chunk to limit the memory
        foreach( $pages->toBase()->chunk( 50 ) as $chunk )
        {
            $versions = self::copyVersions( $variants->only( $chunk->pluck( 'id' )->all() )->collapse() );

            /** @var PageView $orig */
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

                    if( !$page )
                    {
                        $pid = (string) $orig->parent_id;
                        $lft = $next[$pid];
                        $next[$pid] = $lft + 2 * $sizes[$oid];
                        $next[$oid] = $lft + 1;

                        $inserts[] = $nodes[$oid] = self::copyNode( $variant->lang, $lft, $next[$pid] - 1, $nodes[$pid] );
                    }

                    $rows[] = $row = self::copyRows( (string) $nodes[$oid]['id'], $variant, $data, $aux, $refs, $editor );

                    if( !$page )
                    {
                        $page = ( new PageView() )->newFromBuilder( ['id' => $nodes[$oid]['id'], 'latest_id' => $row['version']['id']] );
                        $page->setRelation( 'latest', ( new Version() )->newFromBuilder( $row['version'] ) );
                    }
                }

                $page?->announce( 'added', $editor );
            }

            $bulk = self::flushCopies( $inserts, $rows ) || $bulk;
        }

        /** @var PageView $copy */
        $ids = array_map( fn( $node ) => (string) $node['id'], $nodes );

        return [$copy, $bulk ? array_values( $ids ) : [], self::copyAccess( $ids, $editor )];
    }


    /**
     * Copies the access restrictions of the pages to their copies.
     *
     * @param array<string, string> $ids IDs of the copied pages as values and the IDs of the original pages as keys
     * @param string $editor Name of the editor
     * @return array<int, string> IDs of the copied pages which are restricted
     */
    protected static function copyAccess( array $ids, string $editor ) : array
    {
        $rows = $restricted = [];
        $now = now()->startOfSecond();
        $tenant = Tenancy::value();

        foreach( array_chunk( array_keys( $ids ), PageAccess::CHUNK_SIZE ) as $chunk )
        {
            foreach( PageAccess::select( 'page_id', 'value' )->whereIn( 'page_id', $chunk )->toBase()->get() as $access )
            {
                $rows[] = [
                    'page_id' => $restricted[] = $ids[(string) $access->page_id],
                    'tenant_id' => $tenant,
                    'value' => (string) $access->value,
                    'editor' => $editor,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        self::insertRows( PageAccess::query()->toBase(), $rows );

        return array_values( array_unique( $restricted ) );
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
     * @return array{variant: array<string, mixed>, version: array<string, mixed>, refs: array<string, array<int, string>>} Records to insert
     */
    protected static function copyRows( string $pageId, PageVariant $variant, array $data, array $aux, array $refs, string $editor ) : array
    {
        $tenant = Tenancy::value();
        $copy = self::variantRow( $pageId, [], ['status' => 0] + array_intersect_key( $data, array_flip( ['lang', ...PageVariant::FIELDS] ) ) + [
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
     * @param int $lft Left value in the page tree
     * @param int $rgt Right value in the page tree
     * @param array<string, mixed> $parent Record of the copied parent page
     * @return array<string, mixed> Record of the page tree
     */
    protected static function copyNode( string $lang, int $lft, int $rgt, array $parent ) : array
    {
        $node = ( new Page() )->forceFill( [
            'id' => ( new PageView() )->newUniqueId(),
            'tenant_id' => Tenancy::value(),
            'source' => $lang,
        ] );
        $node->rawNode( $lft, $rgt, (string) $parent['id'], (int) $parent[NestedSet::DEPTH] + 1 );
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
        $data = (array) ( $version->data ?? $variant->only( PageVariant::FIELDS ) );
        $aux = (array) ( $version->aux ?? self::variantAux( (string) $variant->id ) );
        $domain = (string) ( $data['domain'] ?? $variant->domain );

        $data['lang'] = $variant->lang;
        $data['domain'] = $domain;
        $data['path'] = self::uniquePath( $domain, (string) ( $data['path'] ?? $variant->path ), $variant->lang, $known, $loaded );

        $refs = $version ? [
            'files' => $version->files->pluck( 'id' )->all(),
            'elements' => $version->elements->pluck( 'id' )->all(),
        ] : self::refs( $aux, $user );

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
        $page->forceFill( ['status' => 0] + array_intersect_key( $data, array_flip( ['lang', ...PageVariant::FIELDS] ) ) + [
            'source' => $variant->lang,
            'hashes' => $variant->hashes,
            'stale' => $variant->stale,
            'editor' => $editor,
        ] );

        $node = self::createPage( $page, $ref, $parent );

        if( $size > 1 && ( $rgt = $node->getRgt() ) !== null )
        {
            $node->newNestedSetQuery()->makeGap( $rgt, 2 * ( $size - 1 ) );
            $node->refreshNode();
            $page->synced( $node );
        }

        return [self::addVersion( $page, $data, $aux, $editor, $refs ), $node->getAttributes()];
    }


    /**
     * Inserts the copied pages, their variants, versions and the references of the versions and resets the lists.
     *
     * @param array<int, array<string, mixed>> $nodes Records of the copied pages
     * @param array<int, array{variant: array<string, mixed>, version: array<string, mixed>, refs: array<string, array<int, string>>}> $rows Records of the copied variants
     * @return bool TRUE if records have been inserted
     */
    protected static function flushCopies( array &$nodes, array &$rows ) : bool
    {
        if( empty( $rows ) ) {
            return false;
        }

        $pivots = [];
        $version = new Version();

        foreach( $rows as $row )
        {
            foreach( $row['refs'] as $relation => $ids )
            {
                /** @var \Illuminate\Database\Eloquent\Relations\BelongsToMany<Base, Version> $rel */
                $rel = $version->{$relation}();

                foreach( array_unique( $ids ) as $refId ) {
                    $pivots[$rel->getTable()][] = [$rel->getForeignPivotKeyName() => $row['version']['id'], $rel->getRelatedPivotKeyName() => $refId];
                }
            }
        }

        // the pages first because of the foreign keys of the variants and versions before their references
        self::insertRows( Page::withoutGlobalScopes()->toBase(), $nodes );
        self::insertRows( Version::query()->toBase(), array_column( $rows, 'version' ) );
        self::insertRows( PageVariant::query()->toBase(), array_column( $rows, 'variant' ) );

        foreach( $pivots as $table => $list ) {
            self::insertRows( $version->getConnection()->table( $table ), $list );
        }

        $nodes = $rows = [];
        return true;
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

        foreach( $variants->pluck( 'latest_id' )->filter()->chunk( 500 ) as $chunk )
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


    /**
     * Returns the used paths the copies of the page variants can collide with.
     *
     * The paths of the copies are the paths of the variants or, if they are used, the paths
     * with the language and an optional number appended. Paths of drafts which differ from
     * the paths of their variants are checked by uniquePath() itself.
     *
     * @param Collection<int, PageVariant> $variants Page variants to copy
     * @return array{0: array<string, array<string, bool>>, 1: array<string, array<string, string>>} Used paths by domain
     *  and the languages by domain and path whose collision candidates are all included
     */
    protected static function knownPaths( Collection $variants ) : array
    {
        $known = $loaded = $list = [];

        // LIKE is case insensitive in SQLite and can't use the index, GLOB is case sensitive like the other databases
        $sqlite = ( new PageVariant() )->getConnection()->getDriverName() === 'sqlite';
        $sql = $sqlite ? 'path glob ?' : "path like ? escape '='";
        $map = $sqlite ? ['*' => '[*]', '?' => '[?]', '[' => '[[]'] : ['=' => '==', '%' => '=%', '_' => '=_'];
        $any = $sqlite ? '*' : '%';

        // domain and path are unique for all page variants
        foreach( $variants as $variant ) {
            $list[] = [(string) $variant->domain, (string) $variant->path, (string) $variant->lang];
            $loaded[(string) $variant->domain][(string) $variant->path] = (string) $variant->lang;
        }

        // up to three parameters per path stay below the limit of 2100 parameters of SQL Server
        foreach( array_chunk( $list, 600 ) as $chunk )
        {
            $groups = [];

            foreach( $chunk as [$domain, $path, $lang] ) {
                $groups[$domain][$path] = $lang;
            }

            // flat conditions allow the databases to use the index for each of them
            $query = PageVariant::withTrashed()->select( 'domain', 'path' )->where( function( $query ) use ( $groups, $sql, $map, $any ) {

                foreach( $groups as $domain => $paths )
                {
                    $query->orWhere( fn( $query ) => $query->where( 'domain', (string) $domain )
                        ->whereIn( 'path', array_map( 'strval', array_keys( $paths ) ) ) );

                    foreach( $paths as $path => $lang )
                    {
                        $base = $path === '' ? $lang : $path . '-' . $lang;
                        $query->orWhere( fn( $query ) => $query->where( 'domain', (string) $domain )
                            ->whereRaw( $sql, [strtr( $base, $map ) . $any] ) );
                    }
                }
            } );

            foreach( $query->toBase()->get() as $row ) {
                $known[(string) $row->domain][(string) $row->path] = true;
            }
        }

        return [$known, $loaded];
    }
}
