<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Resource\Support;

use Aimeos\Cms\ContentAction;
use Aimeos\Cms\DB;
use Aimeos\Cms\Exceptions\ConflictException;
use Aimeos\Cms\Exceptions\InvalidInputException;
use Aimeos\Cms\Jobs\PruneVersions;
use Aimeos\Cms\Merge;
use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Resource\BulkResult;
use Aimeos\Cms\Resource\Variants;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Validation;
use Aimeos\Nestedset\NestedSet;
use Illuminate\Contracts\Auth\Authenticatable;


/**
 * Saves new versions of page variants shared by the Pages and Variants resource classes.
 *
 * @internal Use Pages::save()/bulk() for the source variants and Variants::save()/bulk() for the others
 */
final class PageEdits
{
    /**
     * Updates the variant of an existing page with a new version.
     *
     * @param string $id Page UUID
     * @param array<string, mixed> $input Changed fields (merged with latest version)
     * @param Authenticatable|null $user Authenticated user for permission-based validation and editor tracking
     * @param string|null $latestId Version ID the editor was working on (for conflict detection)
     * @param string|null $lang Language of the page variant or NULL for the source variant
     * @param bool $restore TRUE if the input restores an old version, which marks translations as outdated
     * @return PageView
     */
    public static function save( string $id, array $input, ?Authenticatable $user, ?string $latestId,
        ?string $lang, bool $restore = false ) : PageView
    {
        Permission::check( 'page:save', $user );

        $source = isset( $input['source'] ) ? (string) $input['source'] : null;
        $input = Validation::page( $input, $user );
        $editor = Utils::editor( $user );

        return DB::transaction( function() use ( $id, $input, $user, $editor, $latestId, $lang, $source, $restore ) {

            $page = PageView::withTrashed()->language( $lang )->with( 'latest' )->findOrFail( $id );

            if( !empty( $input ) || $source === null )
            {
                self::apply( $page, $input, $editor, $latestId, $user );
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
                Variants::setSource( (string) $page->id, $source, $user );

                $variant = $page->variant()->firstOrFail( ['id', 'hashes', 'stale'] );
                $page->forceFill( ['source' => $source, 'hashes' => $variant->hashes, 'stale' => $variant->stale] )->syncOriginal();
            }

            return $page;
        } );
    }


    /**
     * Applies the same partial input to the variants of multiple pages, optionally including all sub-pages.
     *
     * @param array<string> $ids Page IDs to update
     * @param array<string, mixed> $input Partial page input applied to every page
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param bool $descendants TRUE to also update all sub-pages of the given pages
     * @param string|null $lang Language of the page variants to update or NULL for the source variants
     * @return BulkResult
     */
    public static function bulk( array $ids, array $input, ?Authenticatable $user, bool $descendants, ?string $lang ) : BulkResult
    {
        Permission::check( 'page:save', $user );

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
            self::apply( $page, $input, $editor, null, $user, $refs[$page->latest_id ?? ''] ?? null );

            return $page;
        }, $lang );
    }


    /**
     * Creates a new version for an already loaded page from the given input.
     *
     * Shared by save() (single) and bulk() (bulk) so the versioning, merge
     * and reference handling stays in one place. Must run inside a transaction.
     *
     * @param PageView $page Page model with the "latest" relation loaded
     * @param array<string, mixed> $input Validated page input
     * @param string $editor Name of the editing user
     * @param string|null $latestId Version ID the editor was working on for conflict detection
     * @param Authenticatable|null $user Current user
     * @param array<string, array<string>>|null $refs Prefetched references of the latest version to copy
     */
    private static function apply( PageView $page, array $input, string $editor,
        ?string $latestId, ?Authenticatable $user, ?array $refs = null ) : void
    {
        $aux = array_intersect_key( $input, array_flip( ['meta', 'config', 'content'] ) );
        $data = array_diff_key( $input, $aux );
        $accepts = (bool) $aux;

        if( isset( $input['lang'] ) && $input['lang'] !== $page->lang )
        {
            // new languages must be configured so they don't become allowed for AI translation
            if( !Utils::isLocale( (string) $input['lang'] ) ) {
                throw new InvalidInputException( sprintf( 'Invalid language code "%1$s"', $input['lang'] ) );
            }

            if( PageVariant::withTrashed()->where( 'page_id', $page->id )->where( 'lang', $input['lang'] )->exists() ) {
                throw new ConflictException( sprintf( 'The page already has a variant in "%1$s"', $input['lang'] ) );
            }
        }

        [$data, $aux, $diffs] = Merge::page( $page, $data, $aux, $latestId, $user );

        $data['domain'] ??= $page->domain;

        // without new content, meta or config, the references of the previous version are copied
        PageRows::addVersion( $page, $data, $aux, $editor, $accepts ? Refs::page( $aux, $user ) : $refs, $diffs, $input['lang'] ?? $page->latest?->lang );
    }
}
