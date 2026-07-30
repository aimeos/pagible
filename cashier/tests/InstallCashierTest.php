<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Tests;

use Aimeos\Cms\Commands\InstallCashier;
use Illuminate\Contracts\Console\Kernel;


class InstallCashierTest extends CashierTestAbstract
{
    public function testCommandIsRegistered(): void
    {
        $this->assertInstanceOf(
            InstallCashier::class,
            app( Kernel::class )->all()['cms:install:cashier'],
        );
    }


    public function testCommandPublishesCashierMigrations(): void
    {
        $command = \Mockery::mock( InstallCashier::class )->makePartial();
        $command->shouldReceive( 'call' )
            ->once()
            ->with( 'vendor:publish', ['--tag' => 'cashier-migrations'] )
            ->andReturn( 0 );

        $this->assertSame( 0, $command->handle() );
    }
}
