<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Models;

use Aimeos\Cms\Concerns\Connection;
use Aimeos\Cms\Concerns\HasUuids;
use Aimeos\Cms\Concerns\PrunesTrashed;
use Aimeos\Cms\Concerns\SecondTimestamps;
use Aimeos\Cms\Concerns\Tenancy as HasTenancy;
use Aimeos\Cms\DB;
use Aimeos\Cms\Scout;
use Aimeos\Nestedset\NestedSet;
use Aimeos\Nestedset\NodeTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Date;
use Aimeos\Cms\Tenancy;


/**
 * Page model
 *
 * Owns the page tree (cms_pages) without the language specific data, which is
 * stored in the language variants (PageVariant). Use the PageView model to read
 * pages joined with one of their variants.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $parent_id
 * @property string $source
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PageVariant> $variants
 * @method static \Illuminate\Database\Eloquent\Builder<static> withoutTenancy()
 * @method static \Aimeos\Nestedset\QueryBuilder<static> withTrashed()
 * @method static \Aimeos\Nestedset\QueryBuilder<static> onlyTrashed()
 */
class Page extends Model
{
    use Connection;
    use HasUuids;
    use NodeTrait {
        deleteDescendants as protected deleteNodeDescendants;
        refreshNode as protected refreshNodeData;
    }
    use Prunable;
    use PrunesTrashed;
    use SecondTimestamps;
    use SoftDeletes;
    use HasTenancy;

    public const PERM = 'page';

    /** @var list<string> Columns stored in the cms_pages table besides the timestamps */
    public const FIELDS = ['id', 'tenant_id', 'parent_id', '_lft', '_rgt', 'depth', 'source', 'deleted_at'];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'tenant_id' => '',
        'source' => '',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'source',
    ];

    /**
     * The data type of the primary key.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cms_pages';

    /**
     * Search keys of the variants removed together with the page.
     *
     * @var array<int, string>
     */
    private array $removed = [];

    /**
     * If the tree positions of loaded pages are up to date and don't need to be re-read.
     */
    private static bool $trusted = false;

    /**
     * Updates the search index and removes the variants of purged pages.
     */
    protected static function booted() : void
    {
        static::deleted( function( Page $page ) {
            $page->isForceDeleting() ? $page->unindex() : $page->index( trashed: true );
        } );

        static::forceDeleting( fn( Page $page ) => $page->removeVariants() );
        static::restored( fn( Page $page ) => $page->index( trashed: false ) );
    }


    /**
     * Get the prunable model query.
     *
     * @return Builder<static> Eloquent query builder for pruning trashed pages
     */
    public function prunable() : Builder
    {
        $table = $this->getTable();
        $date = now()->subDays( config( 'cms.prune', 30 ) );

        // descendants are pruned together with their topmost expired ancestor
        return static::expired()
            ->whereNotExists( fn( $query ) => $query->selectRaw( '1' )->from( $table . ' as parent' )
                ->whereColumn( 'parent.id', $table . '.parent_id' )
                ->where( 'parent.deleted_at', '<=', $date ) )
            ->select( 'id', 'tenant_id', 'parent_id', 'deleted_at', NestedSet::LFT, NestedSet::RGT, NestedSet::DEPTH );
    }


    /**
     * Prunes the trashed page including its descendants, variants and versions.
     *
     * @return bool TRUE on success
     */
    public function prune()
    {
        // the nested set queries are tenant scoped, so they must run in the tenant of the page
        Tenancy::run( (string) $this->tenant_id, function() {
            // pages removed together with an ancestor don't exist anymore and the positions may be outdated
            static::withTrashed()->find( $this->id )?->forceDelete();
        } );

        return true;
    }


    /**
     * Permanently deletes the passed pages and their descendants whose variants have been removed by the caller.
     *
     * Doesn't fire model events because the caller updates the search index. Removes the rightmost
     * subtree first because closing its gap doesn't shift the positions of the subtrees left of it,
     * so the positions of the passed pages stay valid without reloading them.
     *
     * @param iterable<Model> $pages Pages with current tree positions
     */
    public static function purge( iterable $pages ) : void
    {
        $model = new self();

        foreach( PageView::topmost( $pages )->reverse() as $page )
        {
            $lft = (int) $page->getAttribute( NestedSet::LFT );
            $rgt = (int) $page->getAttribute( NestedSet::RGT );

            $model->newNestedSetQuery()->whereBetween( NestedSet::LFT, [$lft, $rgt] )->toBase()->delete();
            $model->newNestedSetQuery()->makeGap( $rgt + 1, $lft - $rgt - 1 );
        }
    }


    /**
     * Restores the passed pages and their descendants which have been trashed together with them.
     *
     * Doesn't fire model events because the caller updates the search index. Each page restores
     * the descendants trashed at the same time or later, so descendants trashed before stay in the trash.
     *
     * @param iterable<Model> $pages Pages with current tree positions
     * @param \DateTimeInterface $at Time of the change
     */
    public static function restoreSubtrees( iterable $pages, \DateTimeInterface $at ) : void
    {
        $model = new self();
        $pages = collect( $pages )->filter( fn( Model $page ) => $page->getAttribute( 'deleted_at' ) !== null );

        if( $pages->isEmpty() ) {
            return;
        }

        $model->newNestedSetQuery()->where( function( $query ) use ( $model, $pages ) {
            foreach( $pages as $page )
            {
                $deleted = Date::make( $page->getAttribute( 'deleted_at' ) )?->startOfSecond();
                $bounds = [$page->getAttribute( NestedSet::LFT ), $page->getAttribute( NestedSet::RGT )];

                $query->orWhere( fn( $query ) => $query->whereBetween( NestedSet::LFT, $bounds )
                    ->where( 'deleted_at', '>=', $model->fromDateTime( $deleted ) ) );
            }
        } )->toBase()->update( ['deleted_at' => null, 'updated_at' => $model->fromDateTime( $at )] );
    }


    /**
     * Moves the passed pages and their descendants into the trash.
     *
     * Doesn't fire model events because the caller updates the search index. Descendants which are
     * already in the trash keep their deletion time, so they aren't restored together with the pages.
     *
     * @param iterable<Model> $pages Pages with current tree positions
     * @param \DateTimeInterface $at Time of the change
     */
    public static function trashSubtrees( iterable $pages, \DateTimeInterface $at ) : void
    {
        $model = new self();
        $roots = PageView::topmost( $pages );

        if( $roots->isEmpty() ) {
            return;
        }

        $ids = collect( $pages )->map( fn( Model $page ) => (string) $page->getKey() )->all();
        $time = $model->fromDateTime( $at );

        $model->newNestedSetQuery()
            ->where( function( $query ) use ( $roots ) {
                foreach( $roots as $root ) {
                    $query->orWhereBetween( NestedSet::LFT, [$root->getAttribute( NestedSet::LFT ), $root->getAttribute( NestedSet::RGT )] );
                }
            } )
            ->where( fn( $query ) => $query->whereNull( 'deleted_at' )->orWhereIn( 'id', $ids ) )
            ->toBase()->update( ['deleted_at' => $time, 'updated_at' => $time] );
    }


    /**
     * Re-reads the tree position of the page unless the positions of loaded pages are trusted.
     */
    public function refreshNode() : void
    {
        if( !self::$trusted ) {
            $this->refreshNodeData();
        }
    }


    /**
     * Runs the callback with the tree positions of loaded pages trusted.
     *
     * Only for code that loads the pages within the locked transaction immediately before
     * changing the tree and doesn't reuse them after the tree has been changed.
     *
     * @param \Closure $callback Callback changing the page tree
     * @return mixed Result of the callback
     */
    public static function trusted( \Closure $callback ) : mixed
    {
        $prev = self::$trusted;
        self::$trusted = true;

        try {
            return $callback();
        } finally {
            self::$trusted = $prev;
        }
    }


    /**
     * Relation to the language variants of the page.
     *
     * @return HasMany<PageVariant, $this>
     */
    public function variants() : HasMany
    {
        return $this->hasMany( PageVariant::class, 'page_id' );
    }


    /**
     * Deletes the descendants of the node.
     *
     * The nested set purges descendants without the global scopes, so the
     * descendants of a purged page would be deleted in all tenants. Applies the
     * scopes except the soft delete scope to purge the trashed descendants too.
     */
    protected function deleteDescendants() : void
    {
        if( !$this->forceDeleting )
        {
            $this->deleteNodeDescendants();
            return;
        }

        $this->newNestedSetQuery()->whereDescendantOf( $this )->toBase()->delete();
        $this->newNestedSetQuery()->makeGap( $this->getRgt() + 1, -( $this->getRgt() - $this->getLft() + 1 ) );
        $this->makeRoot();

        static::$actionsPerformed++;
    }


    /**
     * Updates the search index entries of the variants in the subtree of the page after the transaction commits.
     *
     * @param bool $trashed TRUE if the page has been moved into the trash
     */
    protected function index( bool $trashed ) : void
    {
        if( !Scout::syncs( PageView::class ) || $trashed && !Scout::usesExternalSearch() ) {
            return;
        }

        // the cms index keeps the entries of trashed descendants, so only the restored page changes
        $ids = Scout::usesExternalSearch() ? $this->subtreeIds() : [(string) $this->id];

        if( !$trashed || config( 'scout.soft_delete' ) ) {
            Scout::reindex( PageView::class, $ids );
            return;
        }

        foreach( array_chunk( $ids, DB::IN_SIZE ) as $chunk ) {
            Scout::remove( PageView::class, PageVariant::withTrashed()->whereIn( 'page_id', $chunk )->pluck( 'id' )->map( strval( ... ) )->all() );
        }
    }


    /**
     * Permanently deletes the variants and their versions of the page and its descendants.
     */
    protected function removeVariants() : void
    {
        foreach( array_chunk( $this->subtreeIds(), DB::IN_SIZE ) as $chunk )
        {
            $variantIds = PageVariant::withoutGlobalScopes()->whereIn( 'page_id', $chunk )->pluck( 'id' )->map( strval( ... ) )->all();

            PageVariant::purge( $variantIds );
            array_push( $this->removed, ...$variantIds );
        }
    }


    /**
     * Don't fire model events for each descendant for performance reasons.
     *
     * @return bool FALSE to disable firing events for descendants
     */
    protected function shouldFireDescendantEvents() : bool
    {
        return false;
    }



    /**
     * Removes the search index entries of the variants removed together with the page after the transaction commits.
     */
    protected function unindex() : void
    {
        $ids = $this->removed;
        $this->removed = [];

        if( Scout::syncs( PageView::class ) ) {
            Scout::remove( PageView::class, $ids );
        }
    }


    /**
     * Returns the IDs of the page and its descendants.
     *
     * @return array<int, string> Page IDs
     */
    protected function subtreeIds() : array
    {
        return $this->newNestedSetQuery()->whereDescendantOrSelf( $this )->pluck( 'id' )->map( strval( ... ) )->all();
    }
}
