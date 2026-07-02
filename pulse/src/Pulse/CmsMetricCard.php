<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Pulse;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFactory;


class CmsMetricCard extends CmsCard
{
    public string $metric = 'graphql';


    public function render() : View
    {
        $card = $this->definition( $this->metric );

        /** @var 'count'|'min'|'max'|'sum'|'avg'|list<'count'|'min'|'max'|'sum'|'avg'> $aggregates */
        $aggregates = ( is_string( $card['aggregates'] ?? null ) || is_array( $card['aggregates'] ?? null ) )
            ? $card['aggregates'] : 'count';

        $details = array_values( array_filter( (array) ( $card['details'] ?? [] ), 'is_string' ) );

        return ViewFactory::make( 'cms-pulse::cms-metric-card', [
            'title' => (string) ( $card['title'] ?? '' ),
            'entries' => $this->summary(
                (string) ( $card['type'] ?? '' ),
                $aggregates,
                (string) ( $card['group'] ?? 'action' ),
                $details,
                (bool) ( $card['success'] ?? false ),
            ),
        ] );
    }


    /**
     * Returns the configured cards that can be shown, in dashboard order.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function available() : array
    {
        return array_filter( self::cards(), fn( array $definition ) => self::eventsAvailable( $definition ) );
    }


    /**
     * Returns all card definitions from "cms.pulse.cards", keyed by metric, in display order.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function cards() : array
    {
        $cards = config( 'cms.pulse.cards', [] );

        return array_filter( is_array( $cards ) ? $cards : [], 'is_array' );
    }


    /**
     * @return array<string, mixed>
     */
    protected function definition( string $metric ) : array
    {
        $cards = self::cards();

        return $cards[$metric] ?? ( reset( $cards ) ?: [] );
    }


    /**
     * @param array<string, mixed> $definition
     */
    protected static function eventsAvailable( array $definition ) : bool
    {
        foreach( (array) ( $definition['events'] ?? [] ) as $event )
        {
            if( !is_string( $event ) || !class_exists( $event ) ) {
                return false;
            }
        }

        return true;
    }
}
