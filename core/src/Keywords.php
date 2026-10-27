<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\KeywordsBridge\Facades\Keywords as Bridge;
use Aimeos\KeywordsBridge\Keyword;
use Illuminate\Support\Facades\Cache;


/**
 * Cached keyword research data shared by the API interfaces.
 */
class Keywords
{
    // keyword data is updated monthly and Semrush doesn't allow caching it longer than a month
    private const TTL = 604800;


    /**
     * Returns the keywords related to the given keyword including their search volume.
     *
     * @param string $keyword Seed keyword
     * @param string|null $country Two letter ISO country code or NULL for worldwide data if available
     * @param int $limit Maximum number of related keywords
     * @return array<int, array{keyword: string, volume: int|null}> Related keywords ordered by volume
     */
    public static function related( string $keyword, ?string $country = null, int $limit = 50 ) : array
    {
        $keyword = Keyword::normalize( $keyword );
        $key = self::key( 'related', $country, $keyword );
        $entry = Cache::get( $key );

        if( is_array( $entry ) && $entry['limit'] >= $limit ) {
            return array_slice( $entry['items'], 0, $limit );
        }

        $list = self::fetch( fn() => Bridge::related( $keyword, $country, $limit ) );
        $items = $values = [];

        foreach( $list as $item )
        {
            // keywords are passed to the AI, so skip long ones and ones with control or invisible characters
            if( mb_strlen( $item->keyword ) > 80 || preg_match( '/\p{C}/u', $item->keyword ) !== 0 ) {
                continue;
            }

            $items[] = $item->jsonSerialize();
            // related keywords include their volume, so volume requests for them don't fetch it again
            $values[self::key( 'volume', $country, $item->keyword )] = [$item->volume];
        }

        // fewer keywords than requested means the list contains all related keywords
        $values[$key] = ['limit' => count( $list ) < $limit ? PHP_INT_MAX : $limit, 'items' => $items];
        Cache::putMany( $values, self::TTL );

        return $items;
    }


    /**
     * Returns the average monthly search volume of the given keywords.
     *
     * Each keyword is cached separately, so only keywords not requested
     * before consume API units of the keyword service.
     *
     * @param array<int, string> $keywords List of keywords
     * @param string|null $country Two letter ISO country code or NULL for worldwide data if available
     * @return array<int, array{keyword: string, volume: int|null}> Keywords in the requested order
     */
    public static function volume( array $keywords, ?string $country = null ) : array
    {
        $keywords = Keyword::unique( $keywords );
        $keys = array_combine( $keywords, array_map( fn( $keyword ) => self::key( 'volume', $country, $keyword ), $keywords ) );

        $cached = $keys ? Cache::many( array_values( $keys ) ) : [];
        $volumes = [];

        foreach( $keys as $keyword => $key )
        {
            // volumes are wrapped in the cache because NULL is a cache miss
            if( is_array( $value = $cached[$key] ?? null ) ) {
                $volumes[$keyword] = $value[0];
            }
        }

        if( $missing = array_values( array_diff( $keywords, array_keys( $volumes ) ) ) )
        {
            $fetched = $values = [];

            foreach( self::fetch( fn() => Bridge::volume( $missing, $country ) ) as $item ) {
                $fetched[$item->keyword] = $item->volume;
            }

            // also cache keywords without data
            foreach( $missing as $keyword ) {
                $volumes[$keyword] = $fetched[$keyword] ?? null;
                $values[$keys[$keyword]] = [$volumes[$keyword]];
            }

            Cache::putMany( $values, self::TTL );
        }

        return array_map( fn( $keyword ) => ['keyword' => $keyword, 'volume' => $volumes[$keyword]], $keywords );
    }


    /**
     * Returns the name of the configured keyword service driver.
     *
     * @return string Driver name or "none" if no keyword service is configured
     */
    protected static function driver() : string
    {
        return (string) config( 'keywords-bridge.default', 'none' );
    }


    /**
     * Fetches the data from the keyword service.
     *
     * @param \Closure $fetch Closure returning the data
     * @return array<int, Keyword> List of keyword objects
     * @throws \Aimeos\Cms\Exception If no keyword service is configured or it fails
     */
    protected static function fetch( \Closure $fetch ) : array
    {
        if( self::driver() === 'none' ) {
            throw new \Aimeos\Cms\Exception( 'No keyword service configured' );
        }

        try {
            return $fetch();
        } catch( \Throwable $e ) {
            // exception messages may contain internal details like service URLs
            report( $e );
            throw new \Aimeos\Cms\Exception( 'Fetching the keyword data failed' );
        }
    }


    /**
     * Returns the cache key for the keyword data.
     *
     * @param string $type Type of the data
     * @param string|null $country Two letter ISO country code or NULL
     * @param string $value Value identifying the data
     * @return string Cache key
     */
    protected static function key( string $type, ?string $country, string $value ) : string
    {
        return 'keywords:' . $type . ':' . self::driver() . ':' . strtolower( $country ?? '' ) . ':' . sha1( $value );
    }
}
