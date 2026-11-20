<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Models;

use Aimeos\Nestedset\NestedSet;
use Illuminate\Database\Query\Expression;


/**
 * Nav model
 *
 * @property bool $access_exists
 */
class Nav extends PageView
{
    /** @var list<string> Columns required by navigation and ancestor projections */
    public const SELECT_COLUMNS = [
        'id', 'variant_id', 'tenant_id', 'parent_id', 'name', 'title', 'tag', 'path', 'domain', 'lang', 'source', 'to',
        'status', 'config', 'latest_id', NestedSet::LFT, NestedSet::RGT, NestedSet::DEPTH,
    ];


    /**
     * Finds the lightweight published page projection for a frontend route.
     *
     * The returned model is partial and must be treated as read-only.
     */
    public static function lookup( string $path, string $domain ) : ?self
    {
        return self::query()
            ->allVariants()
            ->select( 'id', 'variant_id', 'tenant_id', 'domain', 'path', 'to', 'cache', 'status' )
            ->withAggregate( 'access as access_exists', new Expression( '1' ) )
            ->whereIn( 'status', [1, 2] )
            ->where( 'domain', $domain )
            ->where( 'path', $path )
            ->first();
    }


    /**
     * Returns the first non-null config value of the published public root pages, in tree order.
     *
     * Used for publicly cacheable files, so restricted root pages are skipped for all users.
     *
     * @param string $domain Requested domain, empty to match all domains
     * @param \Closure(self): mixed $fn Receives each partial, read-only root page and returns its value or NULL
     * @param string|null $lang Language of the root page variants, NULL for the source variants
     * @return mixed First non-null value or NULL if no root page provides one
     */
    public static function rootConfig( string $domain, \Closure $fn, ?string $lang = null ) : mixed
    {
        return self::query()
            ->when( (string) $lang !== '', fn( $q ) => $q->visible( (string) $lang ) )
            ->select( 'config' )
            ->whereNull( 'parent_id' )
            ->whereIn( 'status', [1, 2] )
            ->when( $domain !== '', fn( $q ) => $q->where( 'domain', $domain ) )
            ->wherePublic()
            ->orderBy( NestedSet::LFT )
            ->cursor()
            ->map( $fn )
            ->first( fn( $value ) => $value !== null );
    }


    /**
     * Returns the text content of the page.
     *
     * @return string Text content
     */
    public function __toString() : string
    {
        return trim( ( $this->name ?? '' ) . "\n" . ( $this->title ?? '' ) );
    }
}
