<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Models;

use Aimeos\Cms\Concerns\HasUuids;
use Aimeos\Cms\Concerns\Tenancy;
use Aimeos\Cms\Query\PageBuilder;
use Aimeos\Cms\Scout;
use Aimeos\Nestedset\NestedSet;
use Aimeos\Nestedset\NodeTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Date;


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
    use HasUuids;
    use NodeTrait {
        deleteDescendants as protected deleteNodeDescendants;
    }
    use Prunable;
    use SoftDeletes;
    use Tenancy;

    public const PERM = 'page';

    /** @var list<string> Columns stored in the cms_pages table */
    public const COLUMNS = [...PageBuilder::PAGE_COLUMNS, ...PageBuilder::SHARED_COLUMNS];

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
     * Updates the search index and removes the variants of purged pages.
     */
    protected static function booted() : void
    {
        static::deleted( function( Page $page ) {
            $page->isForceDeleting() ? $page->unindex() : $page->index( true );
        } );

        static::forceDeleting( fn( Page $page ) => $page->removeVariants() );
        static::restored( fn( Page $page ) => $page->index( false ) );
    }


    /**
     * Get the current timestamp in seconds precision.
     *
     * @return \Illuminate\Support\Carbon Current timestamp
     */
    public function freshTimestamp()
    {
        return Date::now()->startOfSecond(); // SQL Server workaround
    }


    /**
     * Get the connection name for the model.
     *
     * @return string Name of the database connection to use
     */
    public function getConnectionName() : string
    {
        return config( 'cms.db', 'sqlite' );
    }


    /**
     * Get the prunable model query.
     *
     * @return Builder<static> Eloquent query builder for pruning trashed pages
     */
    public function prunable() : Builder
    {
        return static::withoutTenancy()
            ->select( 'id', 'tenant_id', 'parent_id', 'deleted_at', NestedSet::LFT, NestedSet::RGT, NestedSet::DEPTH )
            ->where( 'deleted_at', '<=', now()->subDays( config( 'cms.prune', 30 ) ) );
    }


    /**
     * Prunes the trashed page including its descendants, variants and versions.
     *
     * @return bool TRUE on success
     */
    public function prune()
    {
        // the nested set queries are tenant scoped, so they must run in the tenant of the page
        \Aimeos\Cms\Tenancy::run( (string) $this->tenant_id, function() {
            // pages removed together with an ancestor don't exist anymore and the positions may be outdated
            static::withTrashed()->find( $this->id )?->forceDelete();
        } );

        return true;
    }


    /**
     * Relation to the explicit frontend access rules of the page.
     *
     * @return HasMany<PageAccess, $this>
     */
    public function access() : HasMany
    {
        return $this->hasMany( PageAccess::class, 'page_id' );
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

        $ids = $this->newNestedSetQuery()->whereDescendantOrSelf( $this )->pluck( 'id' )->map( strval( ... ) )->all();

        if( !$trashed || config( 'scout.soft_delete' ) ) {
            Scout::reindex( PageView::class, $ids );
            return;
        }

        $keys = PageVariant::withTrashed()->whereIn( 'page_id', $ids )->pluck( 'id' )->map( strval( ... ) )->all();
        Scout::afterCommit( PageView::class, fn() => Scout::unindex( PageView::class, $keys ) );
    }


    /**
     * Permanently deletes the variants and their versions of the page and its descendants.
     */
    protected function removeVariants() : void
    {
        $ids = $this->newNestedSetQuery()->whereDescendantOrSelf( $this )->pluck( 'id' )->all();

        foreach( array_chunk( array_map( strval( ... ), $ids ), 500 ) as $chunk )
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

        if( $ids && Scout::syncs( PageView::class ) ) {
            Scout::afterCommit( PageView::class, fn() => Scout::unindex( PageView::class, $ids ) );
        }
    }
}
