<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms\Commands;

use Aimeos\Cms\CashierSetup;
use Illuminate\Console\Command;


class InstallCashier extends Command
{
    protected $signature = 'cms:install:cashier
        {--no-migrate : Publish Cashier migrations without running them}
        {--force : Force migrations to run in production}
        {--webhook : Create the Stripe webhook without an interactive prompt}';

    protected $description = 'Installing Pagible CMS cashier package';


    /**
     * Publishes provider migrations and optionally completes local setup.
     */
    public function handle(): int
    {
        $setup = app( CashierSetup::class );
        $provider = $setup->provider();

        if( !$provider )
        {
            $installed = $setup->providers();
            $message = $installed === [] ? 'No Pagible Cashier provider is installed.'
                : 'More than one Pagible Cashier provider is installed: ' . implode( ', ', $installed ) . '.';
            $this->error( $message . ' Install exactly one of Stripe, Paddle, or Mollie.' );

            return $this->done( Command::FAILURE );
        }

        if( $this->option( 'webhook' ) && $provider !== 'stripe' ) {
            $this->error( 'The --webhook option is only available for the Stripe provider.' );
            return $this->done( Command::FAILURE );
        }

        if( $this->option( 'no-migrate' ) && $this->option( 'force' ) ) {
            $this->error( 'The --force option cannot be combined with --no-migrate.' );
            return $this->done( Command::FAILURE );
        }

        if( !$this->option( 'no-migrate' ) )
        {
            try {
                $conflict = $setup->conflict();
            } catch( \Throwable $e ) {
                $this->error( 'Unable to inspect users.access before migration: ' . $e->getMessage() );
                return $this->done( Command::FAILURE );
            }

            if( $conflict ) {
                $this->error( $conflict );
                return $this->done( Command::FAILURE );
            }
        }

        $this->components->info( 'Publishing ' . ucfirst( $provider ) . ' Cashier migrations' );
        $result = $this->call( 'vendor:publish', ['--tag' => 'cashier-migrations'] );

        if( $result !== Command::SUCCESS ) {
            return $this->done( Command::FAILURE );
        }

        if( !$this->option( 'no-migrate' ) ) {
            $options = $this->option( 'force' ) ? ['--force' => true] : [];
            $result = $this->call( 'migrate', $options );
        }

        if( $result === Command::SUCCESS && $provider === 'stripe' ) {
            $result = $this->stripe( $setup, (bool) $this->option( 'webhook' ) );
        }

        return $this->done( $result );
    }


    /**
     * Displays the developer's next steps and preserves the installation result.
     */
    private function done( int $result ) : int
    {
        if( $this->call( 'cms:cashier:check' ) !== Command::SUCCESS ) {
            $message = $result === Command::SUCCESS
                ? 'Installation actions completed, but the readiness check found configuration still to do.'
                : 'Review the Developer next steps above before retrying installation.';
            $this->warn( $message );
        }

        return $result === Command::SUCCESS ? Command::SUCCESS : Command::FAILURE;
    }


    /**
     * Creates the Stripe webhook explicitly or after an interactive confirmation.
     */
    private function stripe( CashierSetup $setup, bool $explicit ) : int
    {
        if( !$explicit && !$this->input->isInteractive() ) {
            return Command::SUCCESS;
        }

        if( $error = $setup->stripe() )
        {
            if( $explicit ) {
                $this->error( $error );
                return Command::FAILURE;
            }

            $this->warn( 'Stripe webhook creation is not ready: ' . $error );
            return Command::SUCCESS;
        }

        $url = route( 'cashier.webhook' );

        if( !$explicit && !$this->confirm( 'Create a Stripe webhook for ' . $url . '?', true ) ) {
            $this->warn( 'Stripe webhook creation skipped.' );
            return Command::SUCCESS;
        }

        $this->components->info( 'Creating Stripe webhook for ' . $url );

        return $this->call( 'cashier:webhook' );
    }
}
