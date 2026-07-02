<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */
namespace Tests {

use Aimeos\Cms\Pulse\CmsMetricCard;
use Aimeos\Cms\Tenancy;


class PulseCardTest extends PulseTestCase
{
    public function testTenantScopeIsAppliedBeforePulseAggregate() : void
    {
        $this->application()->instance( Tenancy::class, new Tenancy( 'current' ) );

        $card = new TestingCmsCard;

        $card->rows( 'cms_page', 'count' );

        $this->assertSame( 'cms_page:current', $card->aggregateCalls[0]['type'] );
        $this->assertSame( 'count', $card->aggregateCalls[0]['aggregates'] );
    }


    public function testTenantlessCardsUseBasePulseType() : void
    {
        $this->application()->instance( Tenancy::class, new Tenancy( '' ) );

        $card = new TestingCmsCard;
        $card->rows( 'cms_page', 'count' );

        $this->assertSame( 'cms_page', $card->aggregateCalls[0]['type'] );
    }


    public function testAggregateRowsAreCappedBeforeCardProcessing() : void
    {
        $rows = collect( range( 1, 260 ) )
            ->map( fn( int $index ) => $this->aggregateRow( 'action-' . $index, 1 ) );

        $card = new TestingCmsCard( $rows );

        $this->assertCount( 250, $card->rows( 'cms_page', 'count' ) );
        $this->assertSame( 250, $card->aggregateCalls[0]['limit'] );
        $this->assertSame( 'desc', $card->aggregateCalls[0]['direction'] );
    }


    public function testSummariesAreCappedBeforeRendering() : void
    {
        $rows = collect( range( 1, 20 ) )
            ->map( fn( int $index ) => $this->aggregateRow( 'action-' . $index, $index ) );

        $entries = ( new TestingCmsCard( $rows ) )->summaries( 'cms_page', 'count', 'action' );

        $this->assertCount( 10, $entries );
        $this->assertSame( 'action-20', $entries->first()?->label );
    }


    public function testCountOnlySummariesDoNotExposeLatency() : void
    {
        $rows = collect( [$this->aggregateRow( 'saved', 5 )] );

        $entry = ( new TestingCmsCard( $rows ) )->summaries( 'cms_page', 'count', 'action' )->first();

        $this->assertNotNull( $entry );
        $this->assertNull( $entry->avg );
    }


    public function testLatencySummariesUseFetchedAverage() : void
    {
        $rows = collect( [
            $this->aggregateRow( 'search', 2, avg: 10.0 ),
            $this->aggregateRow( 'search', 1, avg: 20.0 ),
        ] );

        $entry = ( new TestingCmsCard( $rows ) )->summaries( 'cms_search', ['count', 'avg'], 'action' )->first();

        $this->assertNotNull( $entry );
        $this->assertSame( 13.3, $entry->avg );
    }


    public function testSummariesPreferBulkItemSumsForOrdering() : void
    {
        $rows = collect( [
            $this->aggregateRow( 'saved', 50, 0 ),
            $this->aggregateRow( 'bulk:saved', 1, 100 ),
        ] );

        $entries = ( new TestingCmsCard( $rows ) )->summaries( 'cms_page', ['count', 'sum'], 'action' );

        $this->assertSame( 'bulk:saved', $entries->first()?->label );
    }


    public function testMetricCardAvailabilityUsesConfiguredOrder() : void
    {
        $this->assertSame( [
            'request',
            'search',
            'contact',
            'jsonapi',
            'graphql',
            'mcp',
        ], array_keys( CmsMetricCard::available() ) );
    }


    public function testConfiguredCardsControlOrderAndVisibility() : void
    {
        $original = config( 'cms.pulse.cards' );

        try {
            config( ['cms.pulse.cards' => [
                'mcp' => ['title' => 'MCP', 'type' => 'cms_mcp'],
                'ghost' => ['title' => 'Ghost', 'type' => 'cms_ghost', 'events' => ['Aimeos\\Cms\\Missing']],
                'graphql' => ['title' => 'GraphQL', 'type' => 'cms_graphql'],
            ]] );

            // Order follows the config; the card whose event class is missing is hidden.
            $this->assertSame( ['mcp', 'graphql'], array_keys( CmsMetricCard::available() ) );
        } finally {
            config( ['cms.pulse.cards' => $original] );
        }
    }


    public function testCardsForMissingPackagesAreHidden() : void
    {
        $method = new \ReflectionMethod( CmsMetricCard::class, 'eventsAvailable' );

        $this->assertFalse( $method->invoke( null, ['events' => ['Aimeos\\Cms\\Missing']] ) );
        $this->assertTrue( $method->invoke( null, ['events' => ['Aimeos\\Cms\\Events\\CmsGraphql']] ) );
        $this->assertTrue( $method->invoke( null, [] ) );
    }


    public function testConfigCanDeclareArbitraryCards() : void
    {
        $original = config( 'cms.pulse.cards' );

        try {
            config( ['cms.pulse.cards' => [
                'graphql' => [
                    'title' => 'GraphQL',
                    'type' => 'cms_graphql',
                    'events' => ['Aimeos\\Cms\\Events\\CmsGraphql'],
                ],
                'custom' => [
                    'title' => 'Custom',
                    'type' => 'cms_custom',
                    'aggregates' => ['count', 'sum'],
                    'details' => ['domain'],
                ],
            ]] );

            $available = CmsMetricCard::available();

            $this->assertSame( ['graphql', 'custom'], array_keys( $available ) );
            $this->assertSame( 'cms_custom', $available['custom']['type'] );
            $this->assertSame( 'Custom', $available['custom']['title'] );
        } finally {
            config( ['cms.pulse.cards' => $original] );
        }
    }


    public function testAdminTransportBucketsUseLatencyAndSuccessMetrics() : void
    {
        $graphql = $this->metricDefinition( 'graphql' );
        $mcp = $this->metricDefinition( 'mcp' );

        $this->assertSame( 'cms_graphql', $graphql['type'] );
        $this->assertSame( ['count', 'avg', 'max'], $graphql['aggregates'] );
        $this->assertSame( ['domain'], $graphql['details'] );
        $this->assertTrue( $graphql['success'] );

        $this->assertSame( 'cms_mcp', $mcp['type'] );
        $this->assertSame( ['count', 'avg', 'max'], $mcp['aggregates'] );
        $this->assertSame( ['domain'], $mcp['details'] );
        $this->assertTrue( $mcp['success'] );
    }


    public function testContactCardUsesLowCardinalityDashboardMetrics() : void
    {
        $contact = $this->metricDefinition( 'contact' );

        $this->assertSame( 'cms_contact', $contact['type'] );
        $this->assertArrayNotHasKey( 'group', $contact );
        $this->assertSame( ['ip'], $contact['details'] );
    }


    private function aggregateRow( string $action, int $count, ?int $sum = null, ?float $avg = null ) : object
    {
        $row = [
            'key' => json_encode( ['action' => $action], JSON_THROW_ON_ERROR ),
            'count' => $count,
        ];

        if( $sum !== null ) {
            $row['sum'] = $sum;
        }

        if( $avg !== null ) {
            $row['avg'] = $avg;
        }

        return (object) $row;
    }


    /**
     * @return array<string, mixed>
     */
    private function metricDefinition( string $metric ) : array
    {
        $method = new \ReflectionMethod( CmsMetricCard::class, 'definition' );
        $definition = $method->invoke( new CmsMetricCard, $metric );

        if( !is_array( $definition ) ) {
            throw new \RuntimeException( 'Metric definition is not an array.' );
        }

        return $definition;
    }
}
}
