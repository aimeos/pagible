<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms;


class Merge
{
    /**
     * Three-way merge + diff for content block arrays, matched by block `id` (or `refid` for references).
     *
     * Returns [$result, $diff] where $diff is a flat map keyed by block id/refid, or null if no differences.
     *
     * @param array<int, array<string, mixed>> $base
     * @param array<int, array<string, mixed>> $current
     * @param array<int, array<string, mixed>> $incoming
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, array<string, mixed>>|null}
     */
    public static function content( array $base, array $current, array $incoming ) : array
    {
        $baseMap = self::indexBlocks( $base );
        $currentMap = self::indexBlocks( $current );
        $incomingMap = self::indexBlocks( $incoming );

        $result = [];
        $diff = [];

        // Process incoming blocks in incoming order
        foreach( $incoming as $block )
        {
            $block = (array) $block;
            $key = self::blockKey( $block );

            if( !$key ) {
                $result[] = $block;
                continue;
            }

            $b = $baseMap[$key] ?? null;
            $c = $currentMap[$key] ?? null;
            $i = $block;

            if( $c == $i )
            {
                $result[] = $i;
            }
            elseif( $b == $c )
            {
                $result[] = $i;
            }
            elseif( $b == $i )
            {
                $result[] = $c ?? $i;

                if( $c !== null ) {
                    $diff[$key] = ['previous' => $b, 'current' => $c];
                }
            }
            elseif( $b !== null && $c !== null )
            {
                $merged = self::try( (array) $b, (array) $c, (array) $i );
                $diff[$key] = ['previous' => $b, 'current' => $i, 'overwritten' => $c, 'merged' => $merged];
                $result[] = $i;
            }
            else
            {
                $result[] = $i;
            }
        }

        // Append blocks only in current (added by other editor)
        foreach( $currentMap as $key => $block )
        {
            if( !isset( $incomingMap[$key] ) )
            {
                if( isset( $baseMap[$key] ) ) {
                    continue; // removed by incoming
                }

                $result[] = $block;
                $diff[$key] = ['previous' => null, 'current' => $block];
            }
        }

        return [$result, $diff ?: null];
    }


    /**
     * Three-way merge + diff for key-value structures (scalar page data, meta, config, element data, file data).
     *
     * Returns [$result, $diff] where $diff is a flat map or null if no differences.
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $current
     * @param array<string, mixed> $incoming
     * @return array{0: array<string, mixed>, 1: array<string, array<string, mixed>>|null}
     */
    public static function structured( array $base, array $current, array $incoming ) : array
    {
        if( $base === $current ) {
            return [$incoming, null];
        }

        if( $current === $incoming ) {
            return [$incoming, null];
        }

        $diff = [];
        $result = [];
        $allKeys = array_keys( $base + $current + $incoming );

        foreach( $allKeys as $k )
        {
            $inBase = array_key_exists( $k, $base );
            $inCurrent = array_key_exists( $k, $current );
            $inIncoming = array_key_exists( $k, $incoming );

            $b = $base[$k] ?? null;
            $c = $current[$k] ?? null;
            $i = $incoming[$k] ?? null;

            if( self::eq( $c, $i ) )
            {
                $result[$k] = $i ?? $c;
            }
            elseif( !$inIncoming && $inCurrent )
            {
                // Key only in current (new from other editor)
                $diff[$k] = ['previous' => null, 'current' => $c];
                $result[$k] = $c;
            }
            elseif( $inIncoming && !$inCurrent && !$inBase )
            {
                // Key only in incoming (new from this editor)
                $result[$k] = $i;
            }
            elseif( self::eq( $b, $c ) )
            {
                $result[$k] = $i;
            }
            elseif( self::eq( $b, $i ) )
            {
                $diff[$k] = ['previous' => $b, 'current' => $c];
                $result[$k] = $c;
            }
            else
            {
                // Both changed differently from base — last-write-wins (incoming)
                $merged = self::isMap( $b ) && self::isMap( $c ) && self::isMap( $i ) ? self::try( (array) $b, (array) $c, (array) $i )
                    : ( is_string( $b ) && is_string( $c ) && is_string( $i ) ? self::tryString( $b, $c, $i ) : null );
                $diff[$k] = ['previous' => $b, 'current' => $i, 'overwritten' => $c, 'merged' => $merged];
                $result[$k] = $i;
            }
        }

        return [$result, $diff ?: null];
    }


    /**
     * Attempts a structural three-way merge for arrays with non-overlapping changes.
     *
     * Returns the merged array if all changed keys are non-conflicting, or null if any key
     * was changed by both sides to different values.
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $current
     * @param array<string, mixed> $incoming
     * @return array<string, mixed>|null
     */
    public static function try( array $base, array $current, array $incoming ) : ?array
    {
        $result = $incoming;

        foreach( array_keys( $base + $current ) as $k )
        {
            $bv = $base[$k] ?? null;
            $cv = $current[$k] ?? null;
            $iv = $incoming[$k] ?? null;

            if( !self::eq( $cv, $bv ) && self::eq( $iv, $bv ) )
            {
                $result[$k] = $cv;
            }
            elseif( !self::eq( $cv, $bv ) && !self::eq( $iv, $bv ) && !self::eq( $cv, $iv ) )
            {
                if( self::isMap( $bv ) && self::isMap( $cv ) && self::isMap( $iv ) )
                {
                    $sub = self::try( (array) $bv, (array) $cv, (array) $iv );

                    if( $sub === null ) {
                        return null;
                    }

                    $result[$k] = $sub;
                }
                elseif( is_string( $base[$k] ?? null ) && is_string( $current[$k] ?? null ) && is_string( $incoming[$k] ?? null ) )
                {
                    $sub = self::tryString( $base[$k], $current[$k], $incoming[$k] );

                    if( $sub === null ) {
                        return null;
                    }

                    $result[$k] = $sub;
                }
                else
                {
                    return null;
                }
            }
        }

        return $result;
    }


    /**
     * Deep-compares two values that may contain nested stdClass objects from JSON decode.
     *
     * Uses strict identity for scalars/nulls, loose comparison for objects/arrays
     * which compares object properties recursively without json_encode overhead.
     *
     * @param mixed $a
     * @param mixed $b
     * @return bool
     */
    protected static function eq( mixed $a, mixed $b ) : bool
    {
        if( $a === $b ) {
            return true;
        }

        if( is_scalar( $a ) || is_scalar( $b ) || is_null( $a ) || is_null( $b ) ) {
            return false;
        }

        return $a == $b;
    }


    /**
     * Attempts a word-level three-way merge for strings using LCS alignment.
     *
     * Splits strings by whitespace, computes change regions via LCS for each side,
     * and merges if the change regions don't overlap.
     *
     * @param string $base
     * @param string $current
     * @param string $incoming
     * @return string|null
     */
    protected static function tryString( string $base, string $current, string $incoming ) : ?string
    {
        $bw = preg_split( '/\s+/', $base, -1, PREG_SPLIT_NO_EMPTY ) ?: [];
        $cw = preg_split( '/\s+/', $current, -1, PREG_SPLIT_NO_EMPTY ) ?: [];
        $iw = preg_split( '/\s+/', $incoming, -1, PREG_SPLIT_NO_EMPTY ) ?: [];

        $cc = self::wordChanges( $bw, $cw );
        $ic = self::wordChanges( $bw, $iw );

        foreach( $cc as [$cs, $ce, $_] ) {
            foreach( $ic as [$is, $ie, $_2] ) {
                if( $cs < $ie && $is < $ce ) {
                    return null;
                }
            }
        }

        $all = array_merge( $cc, $ic );
        usort( $all, fn( $a, $b ) => $b[0] - $a[0] );

        $result = $bw;

        foreach( $all as [$start, $end, $words] ) {
            array_splice( $result, $start, $end - $start, $words );
        }

        return implode( ' ', $result );
    }


    /**
     * Computes change regions between base and modified word arrays using LCS.
     *
     * @param array<int, string> $base
     * @param array<int, string> $mod
     * @return array<int, array{0: int, 1: int, 2: array<int, string>}> [baseStart, baseEnd, replacementWords]
     */
    protected static function wordChanges( array $base, array $mod ) : array
    {
        $matches = self::wordLcs( $base, $mod );
        $changes = [];
        $bi = 0;
        $mi = 0;

        foreach( $matches as [$bIdx, $mIdx] )
        {
            if( $bi < $bIdx || $mi < $mIdx ) {
                $changes[] = [$bi, $bIdx, array_slice( $mod, $mi, $mIdx - $mi )];
            }

            $bi = $bIdx + 1;
            $mi = $mIdx + 1;
        }

        if( $bi < count( $base ) || $mi < count( $mod ) ) {
            $changes[] = [$bi, count( $base ), array_slice( $mod, $mi )];
        }

        return $changes;
    }


    /**
     * Computes the Longest Common Subsequence positions between two word arrays.
     *
     * @param array<int, string> $a
     * @param array<int, string> $b
     * @return array<int, array{0: int, 1: int}> Pairs of [posInA, posInB]
     */
    protected static function wordLcs( array $a, array $b ) : array
    {
        $m = count( $a );
        $n = count( $b );

        // Direction matrix: 0=diagonal(match), 1=up, 2=left — uses O(m×n) bytes instead of O(m×n) ints for full DP
        // Two rolling rows for DP values — uses O(n) instead of O(m×n)
        $dir = [];
        $prev = array_fill( 0, $n + 1, 0 );

        for( $i = 1; $i <= $m; $i++ )
        {
            $curr = [0];

            for( $j = 1; $j <= $n; $j++ )
            {
                if( $a[$i - 1] === $b[$j - 1] ) {
                    $curr[$j] = $prev[$j - 1] + 1;
                    $dir[$i][$j] = 0;
                } elseif( $prev[$j] >= $curr[$j - 1] ) {
                    $curr[$j] = $prev[$j];
                    $dir[$i][$j] = 1;
                } else {
                    $curr[$j] = $curr[$j - 1];
                    $dir[$i][$j] = 2;
                }
            }

            $prev = $curr;
        }

        $result = [];
        $i = $m;
        $j = $n;

        while( $i > 0 && $j > 0 )
        {
            if( $dir[$i][$j] === 0 ) {
                $result[] = [$i - 1, $j - 1];
                $i--;
                $j--;
            } elseif( $dir[$i][$j] === 1 ) {
                $i--;
            } else {
                $j--;
            }
        }

        unset( $dir, $prev );
        return array_reverse( $result );
    }


    /**
     * Returns the key for a content block (id or refid).
     *
     * @param array<string, mixed>|object $block
     * @return string|null
     */
    protected static function blockKey( array|object $block ) : ?string
    {
        $block = (array) $block;
        return $block['id'] ?? $block['refid'] ?? null;
    }


    /**
     * Checks if a value is an array or object (stdClass from JSON decoding).
     *
     * @param mixed $value
     * @return bool
     */
    protected static function isMap( mixed $value ) : bool
    {
        return is_array( $value ) || $value instanceof \stdClass;
    }


    /**
     * Indexes an array of content blocks by their key (id or refid).
     *
     * @param array<int, array<string, mixed>> $blocks
     * @return array<string, array<string, mixed>>
     */
    protected static function indexBlocks( array $blocks ) : array
    {
        $map = [];

        foreach( $blocks as $block )
        {
            $block = (array) $block;
            $key = self::blockKey( $block );

            if( $key ) {
                $map[$key] = $block;
            }
        }

        return $map;
    }
}
