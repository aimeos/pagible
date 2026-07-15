<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */
namespace Tests {

use Aimeos\Cms\Pulse\CmsMetricCard;
use Aimeos\Cms\Tenancy;


class PulseCardTest extends PulseTestCase
{
    public function testAggregateRowsAreCappedBeforeCardProcessing() : void
    {
        $rows = collect( range( 1, 260 ) )
            ->map( fn( int $index ) => $this->aggregateRow( 'action-' . $index, 1 ) );

        $card = new TestingCmsCard( $rows );

        $this->assertCount( 250, $card->rows( 'cms_page' ) );
        $this->assertSame( 250, $card->aggregateCalls[0]['limit'] );
        $this->assertSame( 'desc', $card->aggregateCalls[0]['direction'] );
    }


    public function testConfiguredCardsControlOrderVisibilityAndMetricContract() : void
    {
        $original = config( 'cms.pulse.cards' );

        try {
            config( ['cms.pulse.cards' => [
                'mcp' => ['title' => 'MCP'],
                'ghost' => ['title' => 'Ghost', 'requires' => ['Aimeos\\Cms\\Missing']],
                'custom' => ['title' => 'Custom'],
            ]] );

            $this->assertSame( ['mcp', 'custom'], CmsMetricCard::available() );

            $card = new TestingCmsCard;
            $card->metric = 'custom';
            $data = $card->render()->getData();

            $this->assertSame( 'Custom', $data['title'] );
            $this->assertSame( 'cms_custom', $card->aggregateCalls[0]['type'] );
            $this->assertSame( ['count', 'avg', 'max'], $card->aggregateCalls[0]['aggregates'] );
        } finally {
            config( ['cms.pulse.cards' => $original] );
        }
    }


    public function testLatencySummariesUseFetchedAverage() : void
    {
        $rows = collect( [
            $this->aggregateRow( 'search', 2, 10.0 ),
            $this->aggregateRow( 'search', 1, 20.0 ),
        ] );

        $entry = ( new TestingCmsCard( $rows ) )->summaries( 'cms_search', 'action' )->first();

        $this->assertNotNull( $entry );
        $this->assertSame( 13.3, $entry->avg );
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
        ], CmsMetricCard::available() );
    }


    public function testSummariesAreCappedBeforeRendering() : void
    {
        $rows = collect( range( 1, 20 ) )
            ->map( fn( int $index ) => $this->aggregateRow( 'action-' . $index, $index ) );

        $entries = ( new TestingCmsCard( $rows ) )->summaries( 'cms_page', 'action' );

        $this->assertCount( 10, $entries );
        $this->assertSame( 'action-20', $entries->first()?->label );
    }


    public function testTenantScopeIsAppliedBeforePulseAggregate() : void
    {
        $this->application()->instance( Tenancy::class, new Tenancy( 'current' ) );

        $card = new TestingCmsCard;
        $card->rows( 'cms_page' );

        $this->assertSame( 'cms_page:current', $card->aggregateCalls[0]['type'] );
        $this->assertSame( ['count', 'avg', 'max'], $card->aggregateCalls[0]['aggregates'] );
    }


    public function testTenantlessCardsUseBasePulseType() : void
    {
        $this->application()->instance( Tenancy::class, new Tenancy( '' ) );

        $card = new TestingCmsCard;
        $card->rows( 'cms_page' );

        $this->assertSame( 'cms_page', $card->aggregateCalls[0]['type'] );
    }


    public function testUnknownMetricRendersNoEntries() : void
    {
        $card = new TestingCmsCard;
        $card->metric = 'missing';
        $data = $card->render()->getData();

        $this->assertSame( '', $data['title'] );
        $this->assertTrue( $data['entries']->isEmpty() );
        $this->assertSame( [], $card->aggregateCalls );
    }


    private function aggregateRow( string $action, int $count, ?float $avg = null ) : object
    {
        $row = [
            'key' => json_encode( ['action' => $action], JSON_THROW_ON_ERROR ),
            'count' => $count,
        ];

        if( $avg !== null ) {
            $row['avg'] = $avg;
        }

        return (object) $row;
    }
}
}
