<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Aimeos\Cms\Exception;
use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageAccess;
use Aimeos\Cms\Models\PageNode;
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
     * @return Page Copy of the page in its source language
     */
    public static function copyPage( string $id, ?string $ref = null, ?string $parent = null, ?Authenticatable $user = null ) : Page
    {
        $editor = Utils::editor( $user );

        // the lock must last until all pages of the subtree are copied, the copied root page
        // takes about 0.05s and each other variant about 0.01s because they are inserted in bulk
        $node = Page::select( 'id', NestedSet::LFT, NestedSet::RGT )->findOrFail( $id );
        $variants = PageVariant::whereIn( 'page_id', fn( $q ) => $q->select( 'id' )->from( 'cms_pages' )
            ->where( 'tenant_id', Tenancy::value() )
            ->whereBetween( NestedSet::LFT, [$node->getLft(), $node->getRgt()] )
        )->count();
        $lifetime = (int) ceil( 1 + $variants / 100 );

        $result = Utils::lockedTransaction( function() use ( $id, $ref, $parent, $editor, $user ) {

            /** @var Page $root */
            $root = Page::select( 'id', 'tenant_id', NestedSet::LFT, NestedSet::RGT )->findOrFail( $id );

            $pages = Page::select( 'id', 'parent_id', 'source', NestedSet::LFT, NestedSet::RGT, NestedSet::DEPTH )
                ->where( NestedSet::LFT, '>=', $root->getLft() )
                ->where( NestedSet::RGT, '<=', $root->getRgt() )
                ->orderBy( NestedSet::LFT )
                ->get();

            Page::checkBulk( $pages->count() );

            $variants = PageVariant::select( ['id', 'page_id', 'lang', 'latest_id', 'hashes', 'stale', ...PageVariant::FIELDS] )
                ->whereIn( 'page_id', $pages->pluck( 'id' )->all() )
                ->orderBy( 'lang' )->get();

            $sizes = self::copySizes( self::pages( $pages ), $variants->pluck( 'page_id', 'page_id' )->all(), (string) $root->id );
            $variants = $variants->toBase()->groupBy( 'page_id' );
            $copies = $nodes = $next = $inserts = $rows = [];
            [$known, $loaded] = self::knownPaths( $variants->collapse() );
            $result = null;
            $bulk = false;

            // the latest versions with the content are loaded and the copies inserted per chunk to limit the memory
            foreach( $pages->toBase()->chunk( 50 ) as $chunk )
            {
                $copied = $variants->only( $chunk->pluck( 'id' )->all() )->collapse();
                $versions = self::copyVersions( $copied );

                /** @var Page $orig */
                foreach( $chunk as $orig )
                {
                    $list = $variants->get( (string) $orig->id );
                    $source = $list?->firstWhere( 'lang', $orig->source ) ?? $list?->first();

                    // pages whose parent isn't copied would be placed at the target position otherwise
                    if( !$list || !$source || !isset( $sizes[(string) $orig->id] ) ) {
                        continue;
                    }

                    $pid = (string) $orig->parent_id;
                    $page = $pageId = null;

                    foreach( $list->sortBy( fn( $v ) => $v->id === $source->id ? 0 : 1 ) as $variant )
                    {
                        $version = $versions->get( (string) $variant->latest_id );
                        $data = (array) ( $version->data ?? $variant->only( PageVariant::FIELDS ) );
                        $aux = (array) ( $version->aux ?? self::variantAux( (string) $variant->id ) );

                        $data['lang'] = $variant->lang;
                        $data['domain'] = (string) ( $data['domain'] ?? $variant->domain );
                        $data['path'] = self::uniquePath( $data['domain'], (string) ( $data['path'] ?? $variant->path ), $variant->lang, $known, $loaded );

                        $refs = $version ? [
                            'files' => $version->files->pluck( 'id' )->all(),
                            'elements' => $version->elements->pluck( 'id' )->all(),
                        ] : self::refs( $aux, $user );

                        // the other variants and their versions are inserted in bulk
                        if( $pageId !== null )
                        {
                            $rows[] = self::copyRows( $pageId, $variant, $data, $aux, $refs, $editor );
                            continue;
                        }

                        // descendants are placed into the gap opened once for the whole subtree and inserted in bulk
                        if( $orig->id !== $root->id && isset( $sizes[(string) $orig->id], $nodes[$pid], $next[$pid] ) )
                        {
                            $lft = $next[$pid];
                            $rgt = $lft + 2 * $sizes[(string) $orig->id] - 1;
                            $next[$pid] = $rgt + 1;

                            $inserts[] = $node = self::copyNode( $variant->lang, $lft, $rgt, $nodes[$pid] );
                            $rows[] = $row = self::copyRows( $pageId = (string) $node['id'], $variant, $data, $aux, $refs, $editor );

                            $page = ( new Page() )->newFromBuilder( ['id' => $pageId, 'latest_id' => $row['version']['id']] );
                            $page->setRelation( 'latest', ( new Version() )->newFromBuilder( $row['version'] ) );

                            $nodes[(string) $orig->id] = $node;
                            $next[(string) $orig->id] = $lft + 1;
                            $copies[] = $pageId;
                            continue;
                        }

                        // pages placed by the nested set must see the pages inserted before
                        $bulk = self::flushCopies( $inserts, $rows ) || $bulk;

                        $page = new Page();
                        // copies are disabled until published because the live columns come from the draft
                        $page->forceFill( ['status' => 0] + array_intersect_key( $data, array_flip( ['lang', ...PageVariant::FIELDS] ) ) + [
                            'source' => $variant->lang,
                            'hashes' => $variant->hashes,
                            'stale' => $variant->stale,
                            'editor' => $editor,
                        ] );

                        $node = self::createPage( $page, fn( $node ) => self::position( $node, $ref, $parent ) );
                        $size = $sizes[(string) $orig->id] ?? 1;

                        if( $size > 1 && ( $rgt = $node->getRgt() ) !== null )
                        {
                            $node->newNestedSetQuery()->makeGap( $rgt, 2 * ( $size - 1 ) );
                            $node->refreshNode();
                            self::syncNode( $page, $node );
                        }

                        $nodes[(string) $orig->id] = $node->getAttributes();
                        $next[(string) $orig->id] = $page->getLft() + 1;
                        $copies[] = $pageId = (string) $page->id;

                        $copy = self::addVersion( $page, $data, $aux, $editor, $refs );
                        $result ??= $copy;
                    }

                    $page?->announce( 'added', $editor );
                }

                $bulk = self::flushCopies( $inserts, $rows ) || $bulk;
            }

            if( !$result ) {
                throw new Exception( sprintf( 'Page "%1$s" has no variants', $id ) );
            }

            $restricted = self::copyAccess( array_map( fn( $node ) => (string) $node['id'], $nodes ), $editor );

            return [$result, $bulk ? $copies : [], $restricted];
        }, $lifetime );

        // the variants inserted in bulk aren't indexed by saving the models
        if( !empty( $result[1] ) && Scout::usesSearchIndex() ) {
            Scout::index( Page::class, $result[1] );
        }

        // external search engines store if pages are restricted, which was unknown when the copied page was saved
        if( Scout::usesExternalSearch() ) {
            Scout::reindex( Page::class, array_diff( $result[2], $result[1] ) );
        }

        return $result[0];
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
        $node = ( new PageNode() )->forceFill( [
            'id' => ( new Page() )->newUniqueId(),
            'tenant_id' => Tenancy::value(),
            'source' => $lang,
        ] );
        $node->rawNode( $lft, $rgt, (string) $parent['id'], (int) $parent[NestedSet::DEPTH] + 1 );
        $node->updateTimestamps();

        return $node->getAttributes();
    }


    /**
     * Inserts the copied pages, their variants and versions and resets the lists.
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

        // the pages first because of the foreign keys of the variants
        self::insertRows( PageNode::withoutGlobalScopes()->toBase(), $nodes );
        self::insertCopies( $rows );

        $nodes = $rows = [];
        return true;
    }


    /**
     * Inserts the copied page variants, their versions and the references of the versions.
     *
     * @param array<int, array{variant: array<string, mixed>, version: array<string, mixed>, refs: array<string, array<int, string>>}> $rows Records to insert
     */
    protected static function insertCopies( array $rows ) : void
    {
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

        // versions first because of the foreign keys of the references
        self::insertRows( Version::query()->toBase(), array_column( $rows, 'version' ) );
        self::insertRows( PageVariant::query()->toBase(), array_column( $rows, 'variant' ) );

        foreach( $pivots as $table => $list ) {
            self::insertRows( $version->getConnection()->table( $table ), $list );
        }
    }


    /**
     * Returns the number of copied pages in the subtree of each page copied below the root page.
     *
     * Pages are only copied if they have variants and their parent page is copied too.
     *
     * @param Collection<int, Page> $pages Pages of the subtree in tree order
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
        $known = $loaded = [];

        // LIKE is case insensitive in SQLite and can't use the index, GLOB is case sensitive like the other databases
        $sqlite = ( new PageVariant() )->getConnection()->getDriverName() === 'sqlite';
        $sql = $sqlite ? 'path glob ?' : "path like ? escape '='";
        $map = $sqlite ? ['*' => '[*]', '?' => '[?]', '[' => '[[]'] : ['=' => '==', '%' => '=%', '_' => '=_'];
        $any = $sqlite ? '*' : '%';

        foreach( $variants as $variant ) {
            $loaded[(string) $variant->domain][(string) $variant->path] = (string) $variant->lang;
        }

        $list = [];

        foreach( $loaded as $domain => $paths ) {
            foreach( $paths as $path => $lang ) {
                $list[] = [(string) $domain, (string) $path, $lang];
            }
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
