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
        ], array_keys( CmsMetricCard::available() ) );
    }
}
}
