<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Jobs\PruneVersions;
use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Resource\Bulk;
use Aimeos\Cms\Resource\Pages;
use Aimeos\Cms\Resource\Refs;
use Aimeos\Nestedset\NestedSet;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\ModelNotFoundException;


/**
 * @phpstan-import-type BulkResult from Bulk
 */
class Resource
{
    use Concerns\Copies;
    use Concerns\Lifecycle;
    use Concerns\ResourceElements;
    use Concerns\ResourceFiles;
    use Concerns\Variants;

    public const MAX_RELOCATE = 100;


    /**
     * Creates a new page with version and attached relations.
     *
     * Files and elements attached to the page are derived from the content, meta and config data.
     *
     * @param array<string, mixed> $input Page fields (content/meta/config go into version aux), "lang" defaults to the app locale
     * @param Authenticatable|null $user Authenticated user for permission-based validation and editor tracking
     * @param string|null $ref Sibling page ID to insert before
     * @param string|null $parent Parent page ID to append to
     * @return PageView
     * @throws \InvalidArgumentException On validation failure
     */
    public static function addPage( array $input, ?Authenticatable $user = null, ?string $ref = null, ?string $parent = null ) : PageView
    {
        $input['lang'] = ( $input['lang'] ?? null ) ?: (string) config( 'app.locale', 'en' );
        $input = Validation::page( $input, $user );
        $editor = Utils::editor( $user );

        return DB::lockedTransaction( function() use ( $input, $editor, $ref, $parent, $user ) {

            $aux = [
                'content' => $input['content'] ?? [],
                'meta' => $input['meta'] ?? [],
                'config' => $input['config'] ?? [],
            ];
            $refs = Refs::page( $aux, $user );

            $page = new PageView();
            $page->setUniqueIds();
            $page->fill( $input );
            $page->editor = $editor;

            // Inserts the page with its draft version so the draft (latest=true) row is indexed
            $page->newDraft( [
                'data' => array_diff_key( $input, $aux ),
                'lang' => $input['lang'] ?? null,
                'editor' => $editor,
                'aux' => $aux,
            ], $refs );

            Pages::create( $page, $ref, $parent );

            $page->files()->attach( $refs['files'] );
            $page->elements()->attach( $refs['elements'] );

            $page->announce( ContentAction::Added, $editor );

            return $page;
        } );
    }


    /**
     * Moves a page to a new position in the tree and broadcasts the change.
     *
     * @param string $id Page UUID
     * @param string|null $ref Sibling page ID to insert before
     * @param string|null $parent Parent page ID to append to
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return PageView
     * @throws ModelNotFoundException If page not found
     */
    public static function movePage( string $id, ?string $ref = null, ?string $parent = null, ?Authenticatable $user = null ) : PageView
    {
        $editor = Utils::editor( $user );

        $page = DB::lockedTransaction( function() use ( $id, $ref, $parent, $editor ) {

            $page = PageView::withTrashed()->findOrFail( $id );

            PageView::withoutSyncingToSearch( function() use ( $page, $ref, $parent, $editor ) {
                Pages::saveNode( $page, fn( Page $node ) => Pages::position( $node, $ref, $parent )->save() );
                Pages::saveVariant( $page, ['editor' => $editor] );
            } );

            return $page;
        } );

        $page->announce( ContentAction::Moved, $editor );
        return $page;
    }


    /**
     * Updates an existing page with a new version.
     *
     * @param string $id Page UUID
     * @param array<string, mixed> $input Changed fields (merged with latest version)
     * @param Authenticatable|null $user Authenticated user for permission-based validation and editor tracking
     * @param string|null $latestId Version ID the editor was working on (for conflict detection)
     * @param string|null $lang Language of the page variant or null for the source variant
     * @param bool $restore TRUE if the input restores an old version, which marks translations as outdated
     * @return PageView
     * @throws \InvalidArgumentException On validation failure
     * @throws ModelNotFoundException If page not found
     * @throws Exception If the new source language in $input['source'] has no variant
     */
    public static function savePage( string $id, array $input, ?Authenticatable $user = null, ?string $latestId = null,
        ?string $lang = null, bool $restore = false ) : PageView
    {
        $source = isset( $input['source'] ) ? (string) $input['source'] : null;
        $input = Validation::page( $input, $user );
        $editor = Utils::editor( $user );

        return DB::transaction( function() use ( $id, $input, $user, $editor, $latestId, $lang, $source, $restore ) {

            $page = PageView::withTrashed()->language( $lang )->with( 'latest' )->findOrFail( $id );

            if( !empty( $input ) || $source === null )
            {
                self::applyPage( $page, $input, $editor, $latestId, $user );
                $page->announce( ContentAction::Saved, $editor );
                PruneVersions::dispatchChunked( PageView::class, [$page->getVersionKey()] );
            }

            // a restored old translation may not match the current source anymore
            if( $restore && !$page->isSourceVariant() )
            {
                $page->variant()->toBase()->update( ['stale' => true] );
                $page->forceFill( ['stale' => true] )->syncOriginal();
            }

            if( $source !== null && $source !== $page->source )
            {
                self::setSource( (string) $page->id, $source, $user );

                $variant = $page->variant()->firstOrFail( ['id', 'hashes', 'stale'] );
                $page->forceFill( ['source' => $source, 'hashes' => $variant->hashes, 'stale' => $variant->stale] )->syncOriginal();
            }

            return $page;
        } );
    }


    /**
     * Applies the same partial input to multiple pages, optionally including all sub-pages.
     *
     * @param array<string> $ids Page IDs to update
     * @param array<string, mixed> $input Partial page input applied to every page
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param bool $descendants TRUE to also update all sub-pages of the given pages
     * @param string|null $lang Language of the page variants to update or NULL for the source variants
     * @return BulkResult
     */
    public static function bulkPage( array $ids, array $input, ?Authenticatable $user = null, bool $descendants = false,
        ?string $lang = null ) : array
    {
        $input = Validation::page( $input, $user );

        // recursive save: expand to the whole subtree in depth-first order (defaultOrder() is the
        // nested-set pre-order traversal) so each parent is saved before its children
        if( $descendants && $ids )
        {
            PageView::checkBulk( count( array_unique( $ids ) ) );
            $roots = PageView::topmost( PageView::withTrashed()->whereIn( 'id', $ids )->get( ['id', NestedSet::LFT, NestedSet::RGT] ) );
            $ids = [];

            // disjoint chunks in tree order keep the pre-order traversal across chunks
            foreach( $roots->chunk( Base::CHUNK ) as $chunk )
            {
                /** @var array<string> $ids */
                $ids = array_merge( $ids, PageView::subtrees( $chunk )->defaultOrder()
                    ->limit( PageView::MAX_BULK + 1 - count( $ids ) )->pluck( 'id' )->all() );

                if( count( $ids ) > PageView::MAX_BULK ) {
                    break;
                }
            }
        }

        return Bulk::save( PageView::class, $ids, $input, $user, function( string $id, array $refs, string $editor ) use ( $input, $user, $lang ) : ?PageView {

            // pages without a variant in the language are skipped
            if( !( $page = PageView::withTrashed()->language( $lang, trashed: true )->with( 'latest' )->lockForUpdate()->find( $id ) ) ) {
                return null;
            }

            // bulk only creates new draft versions, so the cached published output is unchanged
            self::applyPage( $page, $input, $editor, null, $user, $refs[$page->latest_id ?? ''] ?? null );

            return $page;
        }, $lang );
    }


    /**
     * Creates a new version for an already loaded page from the given input.
     *
     * Shared by savePage() (single) and bulkPage() (bulk) so the versioning, merge
     * and reference handling stays in one place. Must run inside a transaction.
     *
     * @param PageView $page Page model with the "latest" relation loaded
     * @param array<string, mixed> $input Validated page input
     * @param string $editor Name of the editing user
     * @param string|null $latestId Version ID the editor was working on for conflict detection
     * @param Authenticatable|null $user Current user
     * @param array<string, array<string>>|null $refs Prefetched references of the latest version to copy
     */
    protected static function applyPage( PageView $page, array $input, string $editor,
        ?string $latestId, ?Authenticatable $user, ?array $refs = null ) : void
    {
        $aux = array_intersect_key( $input, array_flip( ['meta', 'config', 'content'] ) );
        $data = array_diff_key( $input, $aux );
        $accepts = (bool) $aux;

        if( isset( $input['lang'] ) && $input['lang'] !== $page->lang )
        {
            // new languages must be configured so they don't become allowed for AI translation
            if( !Utils::isLocale( (string) $input['lang'] ) ) {
                throw new Exception( sprintf( 'Invalid language code "%1$s"', $input['lang'] ) );
            }

            if( PageVariant::withTrashed()->where( 'page_id', $page->id )->where( 'lang', $input['lang'] )->exists() ) {
                throw new Exception( sprintf( 'The page already has a variant in "%1$s"', $input['lang'] ) );
            }
        }

        [$data, $aux, $diffs] = Merge::page( $page, $data, $aux, $latestId, $user );

        $data['domain'] ??= $page->domain;

        // without new content, meta or config, the references of the previous version are copied
        Pages::addVersion( $page, $data, $aux, $editor, $accepts ? Refs::page( $aux, $user ) : $refs, $diffs, $input['lang'] ?? $page->latest?->lang );
    }
}
