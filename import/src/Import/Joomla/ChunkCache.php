<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * Keeps the values of the Joomla articles imported next per chunk, e.g. their texts or their tags and custom field values.
 *
 * prefetch() replaces the values of the former chunk by the ones of the next chunk and keeps the ones of these
 * articles fetched before. The values of other articles are fetched on first use and kept until the next chunk is
 * prefetched. This needs extra queries per article, so it's shown in the debug output. The size of the chunks is
 * limited by the caller, so the kept values stay small.
 *
 * @template TValue
 */
final class ChunkCache
{
    /** @var array<int|string, TValue|null> Values of the articles of the current chunk and of the ones fetched on their own by ID, NULL if there's none */
    private array $values = [];


    /**
     * Initializes the object.
     *
     * @param \Closure(list<int|string>): array<int|string, TValue> $fetch Fetches the values of the articles by ID, articles without values are missing
     * @param string $unfetched Debug message shown if the values of an article are fetched on their own with "%s" for the article ID
     * @param \Closure(string): void|null $debug Shows a debug message or NULL to ignore them
     */
    public function __construct(
        private \Closure $fetch,
        private string $unfetched,
        private ?\Closure $debug = null
    ) {
    }


    /**
     * Fetches the values of the articles imported next at once.
     *
     * The values of the former chunk are discarded, the ones of these articles fetched before are kept.
     *
     * @param array<int|string> $ids IDs of the articles
     */
    public function prefetch( array $ids ): void
    {
        $ids = array_unique( $ids );
        $kept = array_intersect_key( $this->values, array_flip( $ids ) );

        $this->values = $kept + $this->fetched( array_values( array_diff( $ids, array_keys( $kept ) ) ) );
    }


    /**
     * Returns the values of the article, which are fetched on their own if they weren't prefetched.
     *
     * @param int|string $id ID of the article
     * @return TValue|null Values of the article or NULL if there are none
     */
    public function get( int|string $id ): mixed
    {
        if( !array_key_exists( $id, $this->values ) )
        {
            if( $this->debug ) {
                ( $this->debug )( sprintf( $this->unfetched, $id ) );
            }

            $this->values += $this->fetched( [$id] );
        }

        return $this->values[$id];
    }


    /**
     * Returns the values of the article if they are kept without fetching them.
     *
     * @param int|string $id ID of the article
     * @return TValue|null Values of the article or NULL if there are none or they aren't kept
     */
    public function kept( int|string $id ): mixed
    {
        return $this->values[$id] ?? null;
    }


    /**
     * Fetches the values of the articles, articles without values are remembered too, so they aren't fetched again.
     *
     * @param list<int|string> $ids IDs of the articles
     * @return array<int|string, TValue|null> Values by article ID, NULL if there are none
     */
    private function fetched( array $ids ): array
    {
        return $ids !== [] ? ( $this->fetch )( $ids ) + array_fill_keys( $ids, null ) : [];
    }
}
