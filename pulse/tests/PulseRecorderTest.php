<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */
namespace Tests {

use Aimeos\Cms\Events\Observed;
use Aimeos\Cms\Pulse\Recorder;
use Laravel\Pulse\Facades\Pulse as PulseFacade;
use Laravel\Pulse\Pulse as LaravelPulse;


class PulseRecorderTest extends PulseTestCase
{
    protected FakePulse $pulse;


    public function testRecordsTenantScopedLatencyAndDimensions() : void
    {
        ( new Recorder )->record(
            new Observed( source: 'graphql', action: 'pages', durationMs: 12.7, tenant: 'test',
                dimensions: ['domain' => 'example.org', 'success' => true] )
        );

        $this->assertCount( 1, $this->pulse->entries );
        $this->assertSame( 'cms_graphql:test', $this->pulse->entries[0]->type );
        $this->assertSame( 13, $this->pulse->entries[0]->value );
        $this->assertSame( ['count', 'avg', 'max'], $this->pulse->entries[0]->aggregates );

        $key = $this->key( 0 );

        $this->assertSame( 'pages', $key['action'] );
        $this->assertSame( 'example.org', $key['domain'] );
        $this->assertTrue( $key['success'] );
        $this->assertArrayNotHasKey( 'source', $key );
        $this->assertArrayNotHasKey( 'tenant', $key );
    }


    public function testTenantlessEntriesKeepBaseType() : void
    {
        ( new Recorder )->record(
            new Observed( source: 'graphql', action: 'page', durationMs: 1.0 )
        );

        $this->assertSame( 'cms_graphql', $this->pulse->entries[0]->type );
    }


    public function testFiltersEmptyDimensionsWithoutDroppingFalseValues() : void
    {
        ( new Recorder )->record( new Observed(
            source: 'mcp', action: 'save-page', tenant: 'test',
            dimensions: ['domain' => '', 'optional' => null, 'success' => false],
        ) );

        $key = $this->key( 0 );

        $this->assertSame( ['success' => false, 'action' => 'save-page'], $key );
    }


    public function testSamplingOnlyAppliesToMarkedObservations() : void
    {
        config( ['cms.watch.sample' => 0.0] );

        ( new Recorder )->record( new Observed(
            source: 'search', action: 'theme:search', tenant: 'test', sample: true,
        ) );

        ( new Recorder )->record( new Observed(
            source: 'contact', action: 'theme:contact', tenant: 'test',
        ) );

        $this->assertCount( 1, $this->pulse->entries );
        $this->assertSame( 'cms_contact:test', $this->pulse->entries[0]->type );
    }


    /**
     * @return array<string, mixed>
     */
    protected function key( int $index ) : array
    {
        return json_decode( $this->pulse->entries[$index]->key, true, flags: JSON_THROW_ON_ERROR );
    }


    protected function setUp() : void
    {
        parent::setUp();

        $this->pulse = new FakePulse;
        $this->application()->instance( LaravelPulse::class, $this->pulse );
        PulseFacade::clearResolvedInstance( LaravelPulse::class );
    }
}
}
