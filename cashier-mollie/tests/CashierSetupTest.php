<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Tests;

use Aimeos\Cms\CashierSetup;


class CashierSetupTest extends CashierTestAbstract
{
    public function testDetectsConfiguredMollieProvider(): void
    {
        config()->set( 'mollie.key', 'test_real_key' );

        $setup = app( CashierSetup::class );
        $checks = $setup->checks();

        $this->assertSame( 'mollie', $setup->provider() );
        $this->assertTrue( $this->passed( $checks, 'Payment routes' ) );
        $this->assertTrue( $this->passed( $checks, 'Provider registration' ) );
        $this->assertTrue( $this->passed( $checks, 'Provider credentials' ) );
        $this->assertTrue( $this->passed( $checks, 'Webhook verification' ) );
    }


    /**
     * @param array<int, array{name: string, ok: bool, message: string}> $checks
     */
    private function passed( array $checks, string $name ) : bool
    {
        foreach( $checks as $check ) {
            if( $check['name'] === $name ) {
                return $check['ok'];
            }
        }

        return false;
    }
}
