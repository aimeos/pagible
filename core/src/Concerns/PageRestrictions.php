<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Aimeos\Cms\Models\PageAccess;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;


/**
 * Frontend access rules of the page view
 */
trait PageRestrictions
{
    /**
     * Explicit frontend access rules for this page.
     *
     * @return HasMany<PageAccess, $this>
     */
    public function access() : HasMany
    {
        return $this->hasMany( PageAccess::class, 'page_id' );
    }


    /**
     * Returns the canonical immediate frontend access state.
     *
     * @return list<string>|null
     */
    public function accessValues() : ?array
    {
        return PageAccess::values( $this->access );
    }


    /**
     * Returns whether the page has explicit frontend access rules.
     */
    public function restricted() : bool
    {
        if( $this->relationLoaded( 'access' ) ) {
            return $this->getRelation( 'access' )->isNotEmpty();
        }

        $count = $this->getAttribute( 'access_count' );

        return $count !== null
            ? (int) $count > 0
            : $this->access()->exists();
    }


    /**
     * Limits a query to pages visible to the frontend user.
     *
     * @param Builder<static> $query
     */
    public function scopeAccess( Builder $query, ?Authenticatable $user ) : void
    {
        PageAccess::apply( $query, $user );
    }


    /**
     * Adds frontend access decisions to the page query without loading rule rows.
     *
     * @param Builder<static> $query
     */
    public function scopeWithAccess( Builder $query, ?Authenticatable $user ) : void
    {
        PageAccess::flags( $query, $user );
    }


    /**
     * Limits a query to pages without an explicit frontend access rule.
     *
     * @param Builder<static> $query
     */
    public function scopeWherePublic( Builder $query ) : void
    {
        $query->whereDoesntHave( 'access' );
    }


    /**
     * Whether one explicit frontend access rule allows the current user.
     *
     * @return Attribute<bool, never>
     */
    protected function accessAllowed() : Attribute
    {
        return Attribute::get( fn( $value ) => (bool) $value );
    }


    /**
     * Whether the page has explicit frontend access rules.
     *
     * @return Attribute<bool, never>
     */
    protected function accessExists() : Attribute
    {
        return Attribute::get( fn( $value ) => (bool) $value );
    }
}
