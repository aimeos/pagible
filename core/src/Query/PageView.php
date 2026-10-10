<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Query;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;


/**
 * Read-only view joining the page tree and all its language variants.
 *
 * Databases refuse to change tables used by a view (SQLite rebuilds the table, PostgreSQL
 * can't change column types), so migrations changing columns used by the view must drop
 * the view before and create it again afterwards. Until then, the Page model can't be used.
 */
class PageView
{
    /**
     * Creates the view or replaces an existing one.
     */
    public static function create() : void
    {
        $db = self::connection();
        $cols = [];

        foreach( PageBuilder::PAGE_COLUMNS as $col ) {
            $cols[] = 'p.' . $col;
        }

        foreach( PageBuilder::VARIANT_COLUMNS as $alias => $col ) {
            $cols[] = $alias === $col ? 'v.' . $col : 'v.' . $col . ' as ' . $alias;
        }

        foreach( PageBuilder::SHARED_COLUMNS as $col ) {
            $cols[] = 'v.' . $col;
        }

        // tenant condition lets the tenant scope use the variant indexes
        $sql = $db->table( 'cms_pages as p' )->select( $cols )
            ->join( 'cms_page_variants as v', fn( $join ) => $join->on( 'v.page_id', '=', 'p.id' )->on( 'v.tenant_id', '=', 'p.tenant_id' ) )
            ->toSql();

        // MERGE keeps locking reads on the view locking the rows of the tables in MySQL
        $algorithm = in_array( $db->getDriverName(), ['mysql', 'mariadb'], true ) ? 'ALGORITHM=MERGE ' : '';

        self::drop();
        $db->statement( 'CREATE ' . $algorithm . 'VIEW ' . $db->getQueryGrammar()->wrapTable( PageBuilder::VIEW ) . ' AS ' . $sql );
    }


    /**
     * Drops the view if it exists.
     */
    public static function drop() : void
    {
        $db = self::connection();
        $db->statement( 'DROP VIEW IF EXISTS ' . $db->getQueryGrammar()->wrapTable( PageBuilder::VIEW ) );
    }


    /**
     * Returns the database connection of the pages.
     */
    protected static function connection() : Connection
    {
        return DB::connection( config( 'cms.db', 'sqlite' ) );
    }
}
