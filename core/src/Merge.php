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

            $bj = $b !== null ? json_encode( $b ) : null;
            $cj = $c !== null ? json_encode( $c ) : null;
            $ij = json_encode( $i );

            if( $cj === $ij )
            {
                $result[] = $i;
            }
            elseif( $bj === $cj )
            {
                $result[] = $i;
            }
            elseif( $bj === $ij )
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
        $allKeys = array_unique( array_merge( array_keys( $base ), array_keys( $current ), array_keys( $incoming ) ) );

        foreach( $allKeys as $k )
        {
            $inBase = array_key_exists( $k, $base );
            $inCurrent = array_key_exists( $k, $current );
            $inIncoming = array_key_exists( $k, $incoming );

            $b = $base[$k] ?? null;
            $c = $current[$k] ?? null;
            $i = $incoming[$k] ?? null;

            $bj = json_encode( $b );
            $cj = json_encode( $c );
            $ij = json_encode( $i );

            if( $cj === $ij )
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
            elseif( $bj === $cj )
            {
                $result[$k] = $i;
            }
            elseif( $bj === $ij )
            {
                $diff[$k] = ['previous' => $b, 'current' => $c];
                $result[$k] = $c;
            }
            else
            {
                // Both changed differently from base — last-write-wins (incoming)
                $merged = is_array( $b ) && is_array( $c ) && is_array( $i ) ? self::try( $b, $c, $i ) : null;
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

        foreach( array_unique( array_merge( array_keys( $base ), array_keys( $current ) ) ) as $k )
        {
            $bj = json_encode( $base[$k] ?? null );
            $cj = json_encode( $current[$k] ?? null );
            $ij = json_encode( $incoming[$k] ?? null );

            if( $cj !== $bj && $ij === $bj )
            {
                $result[$k] = $current[$k];
            }
            elseif( $cj !== $bj && $ij !== $bj && $cj !== $ij )
            {
                if( is_array( $base[$k] ?? null ) && is_array( $current[$k] ?? null ) && is_array( $incoming[$k] ?? null ) )
                {
                    $sub = self::try( $base[$k], $current[$k], $incoming[$k] );

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
