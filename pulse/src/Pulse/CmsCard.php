<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Pulse;

use Illuminate\Support\Collection;
use Laravel\Pulse\Livewire\Card;


abstract class CmsCard extends Card
{
    private const AGGREGATE_ROW_LIMIT = 250;
    private const SUMMARY_LIMIT = 10;


    /**
     * Returns decoded aggregate rows from Pulse for the current tenant.
     *
     * @param 'count'|'min'|'max'|'sum'|'avg'|list<'count'|'min'|'max'|'sum'|'avg'> $aggregates
     * @return Collection<int, object>
     */
    protected function decoded( string $type, string|array $aggregates, string $orderBy = 'count' ) : Collection
    {
        return $this->aggregate( Metric::type( $type ), $aggregates, $orderBy, 'desc', self::AGGREGATE_ROW_LIMIT )
            ->take( self::AGGREGATE_ROW_LIMIT )
            ->map( fn( object $row ) => $this->row( $row ) )
            ->values();
    }


    /**
     * Summarizes aggregate rows by one decoded key field.
     *
     * @param 'count'|'min'|'max'|'sum'|'avg'|list<'count'|'min'|'max'|'sum'|'avg'> $aggregates
     * @param list<string> $details
     * @return Collection<int, object{label: non-empty-string, count: int<0, max>, sum: int, avg: float|null, max: mixed, detail: string}&\stdClass>
     */
    protected function summary( string $type, string|array $aggregates, string $group,
        array $details = [], bool $success = false ) : Collection
    {
        return $this->decoded( $type, $aggregates )
            ->groupBy( fn( object $row ) => (string) ( $row->{$group} ?? 'unknown' ) )
            ->map( fn( Collection $rows, string $label ) => (object) [
                'label' => $label !== '' ? $label : 'unknown',
                'count' => (int) $rows->sum( 'count' ),
                'sum' => (int) $rows->sum( 'sum' ),
                'avg' => $this->avg( $rows ),
                'max' => $rows->max( 'max' ),
                'detail' => $this->detailText( $rows, $details, $success ),
            ] )
            ->sortByDesc( fn( object $row ) => $row->sum ?: $row->count )
            ->take( self::SUMMARY_LIMIT )
            ->values();
    }


    /**
     * @param Collection<int, object> $rows
     * @param list<string> $details
     */
    protected function detailText( Collection $rows, array $details = [], bool $success = false ) : string
    {
        return trim( implode( ' | ', array_filter( [
            $details ? $this->detail( $rows, ...$details ) : '',
            $success ? $this->successRate( $rows ) : '',
        ] ) ) );
    }


    /**
     * @param Collection<int, object> $rows
     */
    protected function detail( Collection $rows, string ...$fields ) : string
    {
        return $rows
            ->flatMap( fn( object $row ) => collect( $fields )->map( fn( string $field ) => $row->{$field} ?? null ) )
            ->filter()
            ->unique()
            ->take( 4 )
            ->implode( ', ' );
    }


    /**
     * @param Collection<int, object> $rows
     */
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
        $rows = $rows->filter( fn( object $row ) => array_key_exists( 'avg', get_object_vars( $row ) ) );

        if( $rows->isEmpty() ) {
            return null;
        }

        $weighted = $rows->reduce( fn( float $sum, object $row ) =>
            $sum + ( (float) ( $row->avg ?? 0 ) * (int) ( $row->count ?? 0 ) ), 0.0
        );

        $count = (int) $rows->sum( 'count' );

        return $count > 0 ? round( $weighted / $count, 1 ) : null;
    }
}
