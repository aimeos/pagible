<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms\Commands;

use Aimeos\Cms\CashierSetup;
use Illuminate\Console\Command;


class CheckCashier extends Command
{
    protected $signature = 'cms:cashier:check';

    protected $description = 'Checks the Pagible Cashier payment setup';


    /**
     * Reports local readiness and the remaining provider dashboard steps.
     */
    public function handle(): int
    {
        $setup = app( CashierSetup::class );
        $failed = false;
        $next = [];

        $this->components->info( 'Pagible Cashier readiness' );

        foreach( $setup->checks() as $check )
        {
            $failed = $failed || !$check['ok'];
            $status = $check['ok'] ? '<info>PASS</info>' : '<error>FAIL</error>';
            $this->line( sprintf( '  %s  %s: %s', $status, $check['name'], $check['message'] ) );

            if( !$check['ok'] ) {
                $next[] = $check['name'] . ': ' . $check['message'];
            }
        }

        $this->newLine();
        $this->components->info( 'Developer next steps' );

        foreach( array_unique( [...$next, ...$setup->manual()] ) as $step ) {
            $this->line( '  - ' . $step );
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
