<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Pulse;

use Aimeos\Cms\Tenancy;
use Illuminate\Support\Collection;
use Laravel\Pulse\Livewire\Card;


abstract class CmsCard extends Card
{
    /**
     * Returns decoded, tenant-filtered aggregate rows from Pulse.
     *
     * @param 'count'|'min'|'max'|'sum'|'avg'|list<'count'|'min'|'max'|'sum'|'avg'> $aggregates
     * @return Collection<int, object>
     */
    protected function decoded( string $type, string|array $aggregates, ?string $orderBy = 'count' ) : Collection
    {
        return $this->aggregate( $type, $aggregates, $orderBy )
            ->map( fn( object $row ) => $this->row( $row ) )
            ->filter( fn( object $row ) => $this->tenant( $row ) )
            ->values();
    }


    /**
     * Summarizes aggregate rows by one decoded key field.
     *
     * @param 'count'|'min'|'max'|'sum'|'avg'|list<'count'|'min'|'max'|'sum'|'avg'> $aggregates
     * @param \Closure(Collection<int, object>): string|null $detail
     * @return Collection<int, object>
     */
    protected function summary( string $type, string|array $aggregates, string $group,
        ?\Closure $detail = null, ?string $orderBy = 'count' ) : Collection
    {
        return $this->decoded( $type, $aggregates, $orderBy )
            ->groupBy( fn( object $row ) => (string) ( $row->{$group} ?? 'unknown' ) )
            ->map( fn( Collection $rows, string $label ) => (object) [
                'label' => $label !== '' ? $label : 'unknown',
                'count' => (int) $rows->sum( 'count' ),
                'sum' => (int) $rows->sum( 'sum' ),
                'avg' => $this->avg( $rows ),
                'max' => $rows->max( 'max' ),
                'detail' => $detail ? $detail( $rows ) : '',
            ] )
            ->sortByDesc( fn( object $row ) => $row->count ?: $row->sum )
            ->values();
    }


    protected function detail( Collection $rows, string ...$fields ) : string
    {
        return $rows
            ->flatMap( fn( object $row ) => collect( $fields )->map( fn( string $field ) => $row->{$field} ?? null ) )
            ->filter()
            ->unique()
            ->take( 4 )
            ->implode( ', ' );
    }


    protected function successRate( Collection $rows ) : string
    {
        $total = (int) $rows->sum( 'count' );

        if( $total === 0 ) {
            return '';
        }

        $success = (int) $rows->filter( fn( object $row ) => (bool) ( $row->success ?? false ) )->sum( 'count' );

        return round( $success / $total * 100 ) . '% success';
    }


    protected function row( object $row ) : object
    {
        $values = get_object_vars( $row );
        $key = (string) ( $values['key'] ?? '' );
        unset( $values['key'] );

        $payload = json_decode( $key, true );

        return (object) array_merge( is_array( $payload ) ? $payload : ['key' => $key], $values );
    }


    /**
     * @param Collection<int, object> $rows
     */
    protected function avg( Collection $rows ) : ?float
    {
        $weighted = $rows->reduce( fn( float $sum, object $row ) =>
            $sum + ( (float) ( $row->avg ?? 0 ) * (int) ( $row->count ?? 0 ) ), 0.0
        );

        $count = (int) $rows->sum( 'count' );

        return $count > 0 ? round( $weighted / $count, 1 ) : null;
    }


    protected function tenant( object $row ) : bool
    {
        $tenant = Tenancy::value();

        return $tenant === '' || !isset( $row->tenant ) || $row->tenant === '' || $row->tenant === $tenant;
    }
}
