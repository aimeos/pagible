<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Tests;

use Aimeos\Cms\CashierSetup;


class CashierSetupTest extends CashierTestAbstract
{
    public function testDetectsConfiguredStripeProvider(): void
    {
        config()->set( 'cashier.key', 'pk_test' );
        config()->set( 'cashier.secret', 'sk_test' );
        config()->set( 'cashier.webhook.secret', 'whsec_test' );

        $setup = app( CashierSetup::class );
        $checks = $setup->checks();

        $this->assertSame( 'stripe', $setup->provider() );
        $this->assertTrue( $this->passed( $checks, 'Payment routes' ) );
        $this->assertTrue( $this->passed( $checks, 'Provider registration' ) );
        $this->assertTrue( $this->passed( $checks, 'Provider credentials' ) );
        $this->assertTrue( $this->passed( $checks, 'Webhook verification' ) );
        $this->assertNull( $setup->stripe() );
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
