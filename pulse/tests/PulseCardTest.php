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


    public function testMetricCardAvailabilityIncludesInstalledPackages() : void
    {
        $this->assertSame( [
            'page',
            'element',
            'file',
            'auth',
            'ai',
            'search',
            'contact',
            'jsonapi',
            'request',
        ], array_keys( CmsMetricCard::available() ) );
    }


    public function testContentCardsFetchBulkItemSums() : void
    {
        foreach( ['page', 'element', 'file'] as $metric )
        {
            $this->assertSame( ['count', 'sum'], $this->metricDefinition( $metric )['aggregates'] ?? null );
        }
    }


    public function testAuthAndContactCardsUseLowCardinalityDashboardMetrics() : void
    {
        $auth = $this->metricDefinition( 'auth' );
        $contact = $this->metricDefinition( 'contact' );

        $this->assertSame( 'cms_auth', $auth['type'] );
        $this->assertArrayNotHasKey( 'details', $auth );

        $this->assertSame( 'cms_contact', $contact['type'] );
        $this->assertArrayNotHasKey( 'group', $contact );
        $this->assertArrayNotHasKey( 'details', $contact );
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
