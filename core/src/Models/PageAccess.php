<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms\Models;

use Aimeos\Cms\Access;
use Aimeos\Cms\Concerns\Tenancy;
use Aimeos\Cms\Exception;
use Aimeos\Cms\Events\PagesInvalidated;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Scout;
use Aimeos\Cms\Utils;
use Aimeos\Nestedset\NestedSet;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


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
 * @property-read Page $page
 */
class PageAccess extends Model
{
    use Tenancy;

    public const CHUNK_SIZE = 250;
    public $incrementing = false;

    private const MAX_VALUES = 250;

    protected $table = 'cms_page_access';
    protected $fillable = ['page_id', 'value', 'editor'];


    /**
     * Tests whether the user satisfies any of the page's access rules.
     *
     * @param iterable<int, PageAccess> $rules
     */
    public static function allows( iterable $rules, ?Authenticatable $user ) : bool
    {
        if( ( $values = self::values( $rules ) ) === null ) {
            return true;
        }

        if( !$user || !\Aimeos\Cms\Tenancy::allows( $user, \Aimeos\Cms\Tenancy::value() ) ) {
            return false;
        }

        return $values === [] || app( Access::class )->allowed( $user, $values ) !== [];
    }


    public function getConnectionName() : string
    {
        return config( 'cms.db', 'sqlite' );
    }


    /**
     * @return BelongsTo<Page, $this>
     */
    public function page() : BelongsTo
    {
        return $this->belongsTo( Page::class, 'page_id' );
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
        $ids = self::ids( $ids );

        if( $descendants && count( $ids ) !== 1 ) {
            throw new Exception( 'Descendant access changes require exactly one root page.' );
        }

        $values = $access === null ? null : self::normalize( $access );

        [$pages, $changed, $reindex] = Utils::transaction( function() use ( $descendants, $ids, $user, $values ) {
            $pages = $descendants ? self::subtree( $ids[0] ) : self::pages( $ids );
            Page::checkBulk( count( $pages ) );

            $pageIds = array_map( fn( Nav $page ) => (string) $page->id, $pages );

            if( !$pageIds ) {
                return [$pages, [], []];
            }

            $current = self::currentValues( $pageIds );
            $restricted = $values !== null;
            $changed = $changedIds = $reindex = [];

            foreach( $pages as $page )
            {
                $id = (string) $page->id;

                if( ( $current[$id] ?? null ) === $values ) {
                    continue;
                }

                $changed[] = $page;
                $changedIds[] = $id;

                if( array_key_exists( $id, $current ) !== $restricted ) {
                    $reindex[] = $id;
                }
            }

            self::checkAssignments( $changedIds, $values );

            if( $changedIds && $values === null ) {
                self::deleteAccess( $changedIds );
            }
            elseif( $changedIds ) {
                self::replaceAccess( $changedIds, $values, Utils::editor( $user ) );
            }

            return [$pages, $changed, $reindex];
        } );

        if( $changed ) {
            PagesInvalidated::dispatch( array_map( fn( Nav $page ) => [
                'domain' => (string) $page->domain,
                'path' => (string) $page->path,
            ], $changed ) );
            if( Scout::usesExternalSearch() ) {
                Scout::reindex( Page::class, $reindex );
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
            if( !$rule instanceof self ) {
                continue;
            }

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
     * @param list<string> $ids
     * @param array<int, string>|null $values
     */
    private static function checkAssignments( array $ids, ?array $values ) : void
    {
        $count = count( $ids ) * max( 1, count( $values ?? [] ) );

        if( $values !== null && $count > Page::MAX_BULK ) {
            throw new Exception( sprintf(
                'No more than %d page access assignments may be changed at once.',
                Page::MAX_BULK,
            ) );
        }
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
            /** @var array<string, list<PageAccess>> $groups */
            $groups = [];

            foreach( self::whereIn( 'page_id', $chunk )->get( ['page_id', 'value'] ) as $rule ) {
                $groups[(string) $rule->page_id][] = $rule;
            }

            foreach( $groups as $id => $rules ) {
                $result[$id] = self::values( $rules ) ?? [];
            }
        }

        return $result;
    }


    /** @param list<string> $ids */
    private static function deleteAccess( array $ids ) : void
    {
        foreach( array_chunk( $ids, self::CHUNK_SIZE ) as $chunk ) {
            self::whereIn( 'page_id', $chunk )->delete();
        }
    }


    /**
     * @param iterable<string> $ids
     * @return list<string>
     */
    private static function ids( iterable $ids ) : array
    {
        $keys = [];

        foreach( $ids as $id ) {
            if( is_string( $id ) ) {
                $keys[$id] = true;
                Page::checkBulk( count( $keys ) );
            }
        }

        $ids = array_keys( $keys );
        sort( $ids, SORT_STRING );

        return $ids;
    }


    /**
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

        if( $unknown = array_diff( $result, app( Access::class )->list() ) ) {
            throw new Exception( sprintf( 'Unknown frontend access value "%s".', reset( $unknown ) ) );
        }

        return $result;
    }


    /**
     * @param list<string> $ids
     * @return list<Nav>
     */
    private static function pages( array $ids ) : array
    {
        $pages = [];

        foreach( array_chunk( $ids, self::CHUNK_SIZE ) as $chunk )
        {
            $query = Nav::select( 'id', 'domain', 'path' )
                ->withoutGlobalScope( 'jsonapi' )
                ->whereIn( 'id', $chunk )
                ->orderBy( 'id' );

            /** @var list<Nav> $results */
            $results = $query->get()->all();
            array_push( $pages, ...$results );
        }

        return $pages;
    }


    /**
     * @param list<string> $ids
     * @param array<int, string> $values
     */
    private static function replaceAccess( array $ids, array $values, string $editor ) : void
    {
        $now = now()->startOfSecond();
        $model = new self();
        $table = $model->getConnection()->table( $model->getTable() );
        $tenant = \Aimeos\Cms\Tenancy::value();

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

                if( count( $rows ) >= self::CHUNK_SIZE ) {
                    $table->insert( $rows );
                    $rows = [];
                }
            }
        }

        if( $rows ) {
            $table->insert( $rows );
        }
    }


    /**
     * @return list<Nav>
     */
    private static function subtree( string $id ) : array
    {
        $root = Page::query()
            ->withoutGlobalScope( 'jsonapi' )
            ->select( 'id', 'tenant_id', NestedSet::LFT, NestedSet::RGT )
            ->whereKey( $id )
            ->firstOrFail();

        $query = Nav::query()
            ->select( 'id', 'tenant_id', 'domain', 'path', NestedSet::LFT )
            ->withoutGlobalScope( 'jsonapi' )
            ->where( NestedSet::LFT, '>=', $root->getLft() )
            ->where( NestedSet::RGT, '<=', $root->getRgt() )
            ->orderBy( NestedSet::LFT )
            ->limit( Page::MAX_BULK + 1 );

        /** @var list<Nav> $pages */
        $pages = $query->get()->all();

        return $pages;
    }
}
