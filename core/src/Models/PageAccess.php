<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms\Models;

use Closure;
use Aimeos\Cms\Access;
use Aimeos\Cms\Concerns\Tenancy;
use Aimeos\Cms\Exception;
use Aimeos\Cms\Events\PagesInvalidated;
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
    private const MAX_VALUES = 250;
    private const MAX_LENGTH = 100;

    protected $table = 'cms_page_access';
    protected $fillable = ['page_id', 'value', 'editor'];


    /**
     * Tests whether the user satisfies any of the page's access rules.
     *
     * @param iterable<int, PageAccess> $rules
     */
    public static function allows( iterable $rules, ?Authenticatable $user ) : bool
    {
        $values = [];
        $restricted = false;
        $authenticationOnly = false;

        foreach( $rules as $rule )
        {
            if( !$rule instanceof self ) {
                continue;
            }

            $restricted = true;

            if( $rule->value === '' ) {
                $authenticationOnly = true;
            }
            else {
                $values[$rule->value] = true;
            }
        }

        if( !$restricted ) {
            return true;
        }

        if( !$user || !\Aimeos\Cms\Tenancy::allows( $user, \Aimeos\Cms\Tenancy::value() ) ) {
            return false;
        }

        return $authenticationOnly || app( Access::class )->allowed( $user, array_keys( $values ) ) !== [];
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
     * Removes explicit access restrictions from pages.
     *
     * @param iterable<string> $ids Page IDs
     */
    public static function release( iterable $ids ) : int
    {
        return self::apply(
            fn() => self::pages( $ids ),
            fn( array $ids ) => self::deleteAccess( $ids ),
        );
    }


    /**
     * Removes explicit access restrictions from a complete page subtree.
     */
    public static function releaseSubtree( Page $root ) : int
    {
        return self::apply(
            fn() => self::subtree( $root ),
            fn( array $ids ) => self::deleteAccess( $ids ),
        );
    }


    /**
     * Restricts pages to current-tenant users or users with one of the access values.
     *
     * @param iterable<string> $ids Page IDs
     * @param array<int, mixed>|null $values NULL grants authenticated current-tenant users
     */
    public static function restrict( iterable $ids, ?array $values, ?Authenticatable $user = null ) : int
    {
        $values = self::normalize( $values );
        $editor = Utils::editor( $user );

        return self::apply(
            fn() => self::pages( $ids ),
            fn( array $ids ) => self::replaceAccess( $ids, $values, $editor ),
        );
    }


    /**
     * Restricts a complete page subtree.
     *
     * @param array<int, mixed>|null $values
     */
    public static function restrictSubtree( Page $root, ?array $values, ?Authenticatable $user = null ) : int
    {
        $values = self::normalize( $values );
        $editor = Utils::editor( $user );

        return self::apply(
            fn() => self::subtree( $root ),
            fn( array $ids ) => self::replaceAccess( $ids, $values, $editor ),
        );
    }


    /**
     * @param Closure():list<Nav> $load
     * @param Closure(list<string>): void $persist
     */
    private static function apply( Closure $load, Closure $persist ) : int
    {
        [$pages, $ids] = Utils::lockedTransaction( function() use ( $load, $persist ) {
            $pages = $load();
            $ids = [];

            foreach( $pages as $page ) {
                $ids[] = (string) $page->id;
            }

            if( $ids ) {
                $persist( $ids );
            }

            return [$pages, $ids];
        } );

        if( $pages ) {
            PagesInvalidated::dispatch( $pages );
            Scout::syncPages( $ids );
        }

        return count( $pages );
    }


    /**
     * @param iterable<string> $ids
     * @return list<Nav>
     */
    private static function pages( iterable $ids ) : array
    {
        $keys = [];

        foreach( $ids as $id ) {
            if( is_string( $id ) ) {
                $keys[$id] = true;
            }
        }

        $ids = array_keys( $keys );
        sort( $ids, SORT_STRING );
        $pages = [];

        foreach( array_chunk( $ids, self::CHUNK_SIZE ) as $chunk )
        {
            $query = Nav::select( 'id', 'domain', 'path' )
                ->withoutGlobalScope( 'jsonapi' )
                ->whereIn( 'id', $chunk )
                ->orderBy( 'id' )
                ->lockForUpdate();

            /** @var list<Nav> $results */
            $results = $query->get()->all();
            array_push( $pages, ...$results );
        }

        return $pages;
    }


    /**
     * @param array<int, mixed>|null $values
     * @return array<int, string>|null
     */
    private static function normalize( ?array $values ) : ?array
    {
        if( !Access::isAvailable() ) {
            throw new Exception( 'Frontend access restrictions are not available.' );
        }

        if( $values === null ) {
            return null;
        }

        $result = [];

        foreach( $values as $value )
        {
            if( !is_string( $value ) || ( $value = trim( $value ) ) === '' ) {
                throw new Exception( 'Access values must be non-empty strings.' );
            }

            if( mb_strlen( $value ) > self::MAX_LENGTH ) {
                throw new Exception( sprintf( 'Access values may not exceed %d characters.', self::MAX_LENGTH ) );
            }

            $result[$value] = true;
        }

        if( count( $result ) > self::MAX_VALUES ) {
            throw new Exception( sprintf( 'A page may not require more than %d access values.', self::MAX_VALUES ) );
        }

        if( !$result ) {
            return null;
        }

        $result = array_keys( $result );
        sort( $result, SORT_STRING );

        if( $unknown = array_diff( $result, app( Access::class )->all() ) ) {
            throw new Exception( sprintf( 'Unknown frontend access value "%s".', reset( $unknown ) ) );
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
     * @param list<string> $ids
     * @param array<int, mixed>|null $values
     */
    private static function replaceAccess( array $ids, ?array $values, string $editor ) : void
    {
        $now = now()->startOfSecond();
        $model = new self();
        $table = $model->getConnection()->table( $model->getTable() );
        $tenant = \Aimeos\Cms\Tenancy::value();

        foreach( array_chunk( $ids, self::CHUNK_SIZE ) as $chunk ) {
            ( clone $table )->where( 'tenant_id', $tenant )->whereIn( 'page_id', $chunk )->delete();
        }

        $rows = [];

        foreach( $ids as $id )
        {
            foreach( $values ?? [''] as $value )
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
    private static function subtree( Page $node ) : array
    {
        $root = Page::query()
            ->withoutGlobalScope( 'jsonapi' )
            ->select( 'id', 'tenant_id', NestedSet::LFT, NestedSet::RGT )
            ->whereKey( $node->getKey() )
            ->lockForUpdate()
            ->firstOrFail();

        $query = Nav::query()
            ->select( 'id', 'tenant_id', 'domain', 'path', NestedSet::LFT )
            ->withoutGlobalScope( 'jsonapi' )
            ->where( NestedSet::LFT, '>=', $root->getLft() )
            ->where( NestedSet::RGT, '<=', $root->getRgt() )
            ->orderBy( NestedSet::LFT )
            ->lockForUpdate();

        /** @var list<Nav> $pages */
        $pages = $query->get()->all();

        return $pages;
    }
}
