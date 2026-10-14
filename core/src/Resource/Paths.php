<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Resource;

use Aimeos\Cms\DB;
use Aimeos\Cms\Models\PageVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;


/**
 * Finds unused paths for new page variants.
 *
 * @internal
 */
final class Paths
{
    /**
     * Returns the used paths the copies of the page variants can collide with.
     *
     * The paths of the copies are the paths of the variants or, if they are used, the paths
     * with the language and an optional number appended. Paths of drafts which differ from
     * the paths of their variants are checked by unique() itself.
     *
     * @param Collection<int, PageVariant> $variants Page variants to copy
     * @return array{0: array<string, array<string, bool>>, 1: array<string, array<string, string>>} Used paths by domain
     *  and the languages by domain and path whose collision candidates are all included
     */
    public static function known( Collection $variants ) : array
    {
        $known = $loaded = $list = [];

        // domain and path are unique for all page variants
        foreach( $variants as $variant ) {
            $list[] = [(string) $variant->domain, (string) $variant->path, (string) $variant->lang];
            $loaded[(string) $variant->domain][(string) $variant->path] = (string) $variant->lang;
        }

        // up to three parameters per path stay below the limit of 2100 parameters of SQL Server
        foreach( array_chunk( $list, 600 ) as $chunk )
        {
            $groups = [];

            foreach( $chunk as [$domain, $path, $lang] ) {
                $groups[$domain][$path] = $lang;
            }

            $query = PageVariant::withTrashed()->select( 'domain', 'path' )
                ->where( fn( Builder $query ) => self::conditions( $query, $groups ) );

            foreach( $query->toBase()->get() as $row ) {
                $known[(string) $row->domain][(string) $row->path] = true;
            }
        }

        return [$known, $loaded];
    }


    /**
     * Returns a path that isn't used by another page variant in the domain.
     *
     * @param string $domain Domain name
     * @param string $path Preferred path
     * @param string $lang Language code appended on collisions
     * @param array<string, array<string, bool>> $known Known paths by domain, TRUE if used, updated with the returned path
     * @param array<string, array<string, string>> $loaded Languages by domain and path whose candidates are all in $known
     * @return string Unique path
     */
    public static function unique( string $domain, string $path, string $lang, array &$known, array $loaded ) : string
    {
        $base = $path === '' ? $lang : $path . '-' . $lang;
        $complete = ( $loaded[$domain][$path] ?? null ) === $lang;
        $candidate = $path;
        $num = 1;

        while( $known[$domain][$candidate] ?? ( !$complete && PageVariant::withTrashed()->where( 'domain', $domain )->where( 'path', $candidate )->exists() ) ) {
            $candidate = $num > 1 ? $base . '-' . $num : $base;
            $num++;
        }

        $known[$domain][$candidate] = true;
        return $candidate;
    }


    /**
     * Adds the conditions matching the paths and their collision candidates.
     *
     * Flat conditions allow the databases to use the index for each of them.
     *
     * @param Builder<PageVariant> $query Query builder
     * @param array<string, array<string, string>> $groups Languages by domain and path
     */
    private static function conditions( Builder $query, array $groups ) : void
    {
        foreach( $groups as $domain => $paths )
        {
            $query->orWhere( fn( Builder $query ) => $query->where( 'domain', (string) $domain )
                ->whereIn( 'path', array_map( 'strval', array_keys( $paths ) ) ) );

            foreach( $paths as $path => $lang )
            {
                $base = $path === '' ? $lang : $path . '-' . $lang;

                $query->orWhere( function( Builder $query ) use ( $domain, $base ) {
                    DB::wherePrefix( $query->where( 'domain', (string) $domain ), 'path', $base );
                } );
            }
        }
    }
}
