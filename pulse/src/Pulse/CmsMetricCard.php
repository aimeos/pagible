<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Pulse;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View as ViewFactory;
use Laravel\Pulse\Livewire\Card;


class CmsMetricCard extends Card
{
    private const AGGREGATES = ['count', 'avg', 'max'];
    private const AGGREGATE_ROW_LIMIT = 250;
    private const SUMMARY_LIMIT = 10;

    public string $metric = 'graphql';


    /**
     * Returns the configured cards that can be shown, in dashboard order.
     *
     * @return list<string>
     */
    public static function available() : array
    {
        return array_keys( array_filter(
            self::cards(), fn( array $definition ) => self::requirementsAvailable( $definition )
        ) );
    }


    public function render() : View
    {
        $card = self::cards()[$this->metric] ?? null;

        return ViewFactory::make( 'cms-pulse::cms-metric-card', [
            'title' => is_array( $card ) ? (string) ( $card['title'] ?? '' ) : '',
            'entries' => is_array( $card ) ? $this->summary(
                'cms_' . $this->metric,
                (string) ( $card['group'] ?? 'action' ),
                array_values( array_filter( (array) ( $card['details'] ?? [] ), 'is_string' ) ),
                (bool) ( $card['success'] ?? false ),
            ) : collect(),
        ] );
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


    /**
     * Returns all card definitions from "cms.pulse.cards", keyed by metric, in display order.
     *
     * @return array<string, array<string, mixed>>
     */
    protected static function cards() : array
    {
        $cards = config( 'cms.pulse.cards', [] );

        return array_filter( is_array( $cards ) ? $cards : [], 'is_array' );
    }


    /**
     * Returns decoded latency rows from Pulse for the current tenant.
     *
     * @return Collection<int, object>
     */
    protected function decoded( string $type ) : Collection
    {
        return $this->aggregate(
            Metric::type( $type ), self::AGGREGATES, 'count', 'desc', self::AGGREGATE_ROW_LIMIT
        )
            ->take( self::AGGREGATE_ROW_LIMIT )
            ->map( fn( object $row ) => $this->row( $row ) )
            ->values();
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
     * @param array<string, mixed> $definition
     */
    protected static function requirementsAvailable( array $definition ) : bool
    {
        foreach( (array) ( $definition['requires'] ?? [] ) as $class )
        {
            if( !is_string( $class ) || !class_exists( $class ) ) {
                return false;
            }
        }

        return true;
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
    protected function successRate( Collection $rows ) : string
    {
        $total = (int) $rows->sum( 'count' );

        if( $total === 0 ) {
            return '';
        }

        $success = (int) $rows->filter( fn( object $row ) => (bool) ( $row->success ?? false ) )->sum( 'count' );

        return round( $success / $total * 100 ) . '% success';
    }


    /**
     * Summarizes latency rows by one decoded key field.
     *
     * @param list<string> $details
     * @return Collection<int, object{label: non-empty-string, count: int<0, max>, avg: float|null, max: mixed, detail: string}&\stdClass>
     */
    protected function summary( string $type, string $group, array $details = [], bool $success = false ) : Collection
    {
        return $this->decoded( $type )
            ->groupBy( fn( object $row ) => (string) ( $row->{$group} ?? 'unknown' ) )
            ->map( fn( Collection $rows, string $label ) => (object) [
                'label' => $label !== '' ? $label : 'unknown',
                'count' => (int) $rows->sum( 'count' ),
                'avg' => $this->avg( $rows ),
                'max' => $rows->max( 'max' ),
                'detail' => $this->detailText( $rows, $details, $success ),
            ] )
            ->sortByDesc( 'count' )
            ->take( self::SUMMARY_LIMIT )
            ->values();
    }
}
