<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Models;

use Aimeos\Cms\Concerns\Tenancy;
use Aimeos\Cms\Query\PageBuilder;
use Aimeos\Nestedset\NodeTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Date;


/**
 * Node of the page tree
 *
 * Owns the page structure (cms_pages) without the language variants. The Page
 * model reads the pages joined with their variants, Resource writes the tree
 * columns through this model and the language specific columns through PageVariant.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $parent_id
 * @property string $source
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @method static \Illuminate\Database\Eloquent\Builder<static> withoutTenancy()
 * @method static \Aimeos\Nestedset\QueryBuilder<static> withTrashed()
 */
class PageNode extends Model
{
    use NodeTrait {
        deleteDescendants as protected deleteNodeDescendants;
    }
    use SoftDeletes;
    use Tenancy;

    /** @var list<string> Columns stored in the cms_pages table */
    public const COLUMNS = [...PageBuilder::PAGE_COLUMNS, ...PageBuilder::SHARED_COLUMNS];

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
     * Don't fire model events for each descendant for performance reasons.
     *
     * @return bool FALSE to disable firing events for descendants
     */
    protected function shouldFireDescendantEvents() : bool
    {
        return false;
    }
}
