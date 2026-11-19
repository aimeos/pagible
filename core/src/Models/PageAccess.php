<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms\Models;

use Aimeos\Cms\Access;
use Aimeos\Cms\Concerns\Connection;
use Aimeos\Cms\Concerns\Tenancy as HasTenancy;
use Aimeos\Cms\DB;
use Aimeos\Cms\Exception;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Invalidation;
use Aimeos\Cms\Scout;
use Aimeos\Cms\Utils;
use Aimeos\Nestedset\NestedSet;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Expression;
use Aimeos\Cms\Tenancy;


/**
 * One explicit frontend access rule for a page.
 *
 * The absence of rows means public access. An empty value allows an authenticated
 * user of the current tenant; otherwise any value granted by Access allows the page
 * to be viewed.
 *
 * @property string $page_id
 * @property string $tenant_id
 * @property string $value
 * @property string $editor
 * @property-read PageView $page
 */
class PageAccess extends Model
{
    use Connection;
    use HasTenancy;

    public const CHUNK_SIZE = 250;
    public $incrementing = false;

    private const MAX_VALUES = 250;

    protected $table = 'cms_page_access';
    protected $fillable = ['page_id', 'value', 'editor'];


    /**
     * Adds frontend access decisions to a page query without loading rule rows.
     *
     * @param Builder<covariant PageView> $query
     */
    public static function flags( Builder $query, ?Authenticatable $user ) : void
    {
        $rules = self::rules( $user );

        $query->withAggregate( 'access as access_exists', new Expression( '1' ) )
            ->withAggregate( [
                'access as access_allowed' => function( Builder $query ) use ( $rules ) {
                    if( !$rules ) {
                        $query->whereRaw( '1 = 0' );
                        return;
                    }

                    $rules( $query );
                },
            ], new Expression( '1' ) );
    }


    /**
     * Limits a query to pages visible to the frontend user.
     *
     * @param Builder<covariant PageView> $query
     */
    public static function apply( Builder $query, ?Authenticatable $user ) : void
    {
        if( !( $rules = self::rules( $user ) ) ) {
            $query->wherePublic();
            return;
        }

        $query->where( function( Builder $query ) use ( $rules ) {
            $query->whereDoesntHave( 'access' )
                ->orWhereHas( 'access', $rules );
        } );
    }


    /**
     * Rejects the request if the page loaded with flags() is restricted for the frontend user.
     *
     * @throws AuthenticationException If no user is authenticated
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException If the user isn't allowed
     */
    public static function check( PageView $page, ?Authenticatable $user ) : void
    {
        if( $page->access_exists && !$page->access_allowed ) {
            $user ? abort( 403 ) : throw new AuthenticationException();
        }
    }


    /**
     * Returns the page owning this explicit frontend access rule.
     *
     * @return BelongsTo<PageView, $this>
     */
    public function page() : BelongsTo
    {
        return $this->belongsTo( PageView::class, 'page_id' );
    }


    /**
     * Replaces the immediate frontend access state for pages.
     *
     * NULL makes pages public, an empty list requires authentication and a
     * non-empty list grants access through any listed value.
     *
     * @param iterable<string> $ids Page IDs
     * @param array<int, mixed>|null $access Canonical access state
     */
    public static function set( iterable $ids, ?array $access, ?Authenticatable $user = null,
        bool $descendants = false ) : int
    {
        Permission::check( 'page:access', $user );

        $ids = PageView::bulkIds( $ids );
        sort( $ids, SORT_STRING );

        if( $descendants && count( $ids ) !== 1 ) {
            throw new Exception( 'Descendant access changes require exactly one root page.' );
        }

        $values = $access === null ? null : self::normalize( $access );

        [$pages, $changed, $reindex] = DB::transaction( function() use ( $descendants, $ids, $user, $values ) {
            $pages = $descendants ? self::subtree( $ids[0] ) : self::pages( $ids );
            PageView::checkBulk( count( $pages ) );

            [$changed, $reindex] = self::changes( $pages, $values );

            if( $values !== null && count( $changed ) * max( 1, count( $values ) ) > PageView::MAX_BULK ) {
                throw new Exception( sprintf( 'No more than %d page access assignments may be changed at once.', PageView::MAX_BULK ) );
            }

            if( $changed && $values === null ) {
                self::deleteAccess( $changed );
            }
            elseif( $changed ) {
                self::replaceAccess( $changed, $values, Utils::editor( $user ) );
            }

            return [$pages, $changed, $reindex];
        } );

        if( $changed )
        {
            Invalidation::pages( self::variants( $changed ) );

            if( Scout::usesExternalSearch() ) {
                Scout::reindex( PageView::class, $reindex );
            }
        }

        return count( $pages );
    }


    /**
     * Returns the canonical frontend access state for explicit rules.
     *
     * @param iterable<int, PageAccess> $rules
     * @return list<string>|null
     */
    public static function values( iterable $rules ) : ?array
    {
        $values = [];

        foreach( $rules as $rule )
        {
            if( $rule->value === '' ) {
                return [];
            }

            $values[] = $rule->value;
        }

        if( !$values ) {
            return null;
        }

        $values = array_values( array_unique( $values ) );
        sort( $values, SORT_STRING );

        return $values;
    }


    /**
     * Returns the pages whose access state differs from the new one.
     *
     * @param list<string> $pages IDs of the pages to update
     * @param array<int, string>|null $values New access state
     * @return array{0: list<string>, 1: list<string>} IDs of the changed pages and of the pages switching between public and restricted
     */
    private static function changes( array $pages, ?array $values ) : array
    {
        $current = self::currentValues( $pages );
        $restricted = $values !== null;
        $changed = $reindex = [];

        foreach( $pages as $id )
        {
            if( ( $current[$id] ?? null ) === $values ) {
                continue;
            }

            $changed[] = $id;

            if( array_key_exists( $id, $current ) !== $restricted ) {
                $reindex[] = $id;
            }
        }

        return [$changed, $reindex];
    }


    /**
     * Returns the canonical explicit access state keyed by page ID.
     *
     * Pages without an entry are public and therefore absent from the result.
     *
     * @param list<string> $ids
     * @return array<string, list<string>>
     */
    private static function currentValues( array $ids ) : array
    {
        $result = [];

        foreach( array_chunk( $ids, self::CHUNK_SIZE ) as $chunk )
        {
            foreach( self::whereIn( 'page_id', $chunk )->get( ['page_id', 'value'] )->groupBy( 'page_id' ) as $id => $rules ) {
                $result[(string) $id] = (array) self::values( $rules );
            }
        }

        return $result;
    }


    /**
     * Deletes access rows for the supplied page IDs in bounded chunks.
     *
     * @param list<string> $ids
     */
    private static function deleteAccess( array $ids ) : void
    {
        foreach( array_chunk( $ids, self::CHUNK_SIZE ) as $chunk ) {
            self::whereIn( 'page_id', $chunk )->delete();
        }
    }


    /**
     * Validates and canonicalizes submitted frontend access values.
     *
     * @param array<int, mixed> $values
     * @return array<int, string>
     */
    private static function normalize( array $values ) : array
    {
        if( !Permission::has( 'access:view' ) ) {
            throw new Exception( 'Frontend access restrictions are not available.' );
        }

        $result = Access::normalize( $values );

        if( count( $result ) > self::MAX_VALUES ) {
            throw new Exception( sprintf( 'A page may not require more than %d access values.', self::MAX_VALUES ) );
        }

        if( $unknown = array_diff( $result, app( Access::class )->known( $result ) ) ) {
            throw new Exception( sprintf( 'Unknown frontend access value "%s".', reset( $unknown ) ) );
        }

        return $result;
    }


    /**
     * Returns the IDs of the existing pages in bounded chunks.
     *
     * @param list<string> $ids
     * @return list<string>
     */
    private static function pages( array $ids ) : array
    {
        $pages = [];

        foreach( array_chunk( $ids, self::CHUNK_SIZE ) as $chunk ) {
            array_push( $pages, ...Nav::whereIn( 'id', $chunk )->orderBy( 'id' )->pluck( 'id' )->map( strval( ... ) )->all() );
        }

        return $pages;
    }


    /**
     * Replaces access rows for the supplied pages.
     *
     * @param list<string> $ids
     * @param array<int, string> $values
     */
    private static function replaceAccess( array $ids, array $values, string $editor ) : void
    {
        $now = now()->startOfSecond();
        $model = new self();
        $table = $model->getConnection()->table( $model->getTable() );
        $tenant = Tenancy::value();

        self::deleteAccess( $ids );

        $rows = [];

        foreach( $ids as $id )
        {
            foreach( $values ?: [''] as $value )
            {
                $rows[] = [
                    'page_id' => $id,
                    'tenant_id' => $tenant,
                    'value' => $value,
                    'editor' => $editor,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::insertRows( $table, $rows );
    }


    /**
     * Returns the access-rule predicate for a tenant-valid frontend user.
     *
     * @return (\Closure(Builder<PageAccess>): void)|null
     */
    private static function rules( ?Authenticatable $user ) : ?\Closure
    {
        if( !$user || !Tenancy::allows( $user, Tenancy::value() ) ) {
            return null;
        }

        $values = app( Access::class )->allowed( $user );

        return function( Builder $query ) use ( $values ) : void {
            $query->where( 'value', '' );

            if( $values ) {
                $query->orWhereIn( 'value', $values );
            }
        };
    }


    /**
     * Returns the page IDs of a bounded subtree for a recursive access mutation.
     *
     * @return list<string>
     */
    private static function subtree( string $id ) : array
    {
        $root = PageView::query()
            ->select( 'id', 'tenant_id', NestedSet::LFT, NestedSet::RGT )
            ->whereKey( $id )
            ->firstOrFail();

        return array_values( Nav::query()
            ->whereDescendantOrSelf( $root )
            ->orderBy( NestedSet::LFT )
            ->limit( PageView::MAX_BULK + 1 )
            ->pluck( 'id' )->map( strval( ... ) )->all() );
    }


    /**
     * Loads the routes of all language variants of the pages, whose cached responses must be invalidated.
     *
     * @param list<string> $ids Page IDs
     * @return list<PageVariant> Variants with their domain and path
     */
    private static function variants( array $ids ) : array
    {
        $variants = [];

        foreach( array_chunk( $ids, DB::IN_SIZE ) as $chunk ) {
            array_push( $variants, ...PageVariant::whereIn( 'page_id', $chunk )->get( ['domain', 'path'] )->all() );
        }

        return $variants;
    }
}
