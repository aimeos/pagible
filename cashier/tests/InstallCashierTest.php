<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Tests;

use Aimeos\Cms\CashierSetup;
use Aimeos\Cms\Commands\CheckCashier;
use Aimeos\Cms\Commands\InstallCashier;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;


class InstallCashierTest extends CashierTestAbstract
{
    public function testCheckCommandReturnsFailureForMissingReadinessItem(): void
    {
        app()->instance( CashierSetup::class, new CashierCheckSetupStub() );

        $this->assertSame( 1, Artisan::call( 'cms:cashier:check' ) );
        $output = Artisan::output();
        $this->assertStringContainsString( 'Developer next steps', $output );
        $this->assertStringContainsString( 'Configure STRIPE_SECRET.', $output );
    }


    public function testCommandCreatesStripeWebhookWhenExplicit(): void
    {
        app()->instance( CashierSetup::class, new CashierSetupStub( 'stripe' ) );
        $this->stripeRoute();

        $command = new InstallCashierCommand();

        $this->assertSame( 0, $this->runCommand( $command, ['--webhook' => true, '--no-interaction' => true] ) );
        $this->assertNull( $command->question );
        $this->assertSame( [
            ['vendor:publish', ['--tag' => 'cashier-migrations']],
            ['migrate', []],
            ['cashier:webhook', []],
            ['cms:cashier:check', []],
        ], $command->calls );
    }


    public function testCommandIsRegistered(): void
    {
        $commands = app( Kernel::class )->all();
        $install = $commands['cms:install:cashier'];

        $this->assertInstanceOf( InstallCashier::class, $install );
        $this->assertInstanceOf( CheckCashier::class, $commands['cms:cashier:check'] );
        $this->assertFalse( $install->getDefinition()->hasOption( 'migrate' ) );
        $this->assertTrue( $install->getDefinition()->hasOption( 'no-migrate' ) );
    }


    public function testCommandOffersStripeWebhookByDefault(): void
    {
        app()->instance( CashierSetup::class, new CashierSetupStub( 'stripe' ) );
        $this->stripeRoute();

        $command = new InstallCashierCommand();
        $command->confirmed = true;

        $this->assertSame( 0, $this->runCommand( $command ) );
        $this->assertSame( 'Create a Stripe webhook for https://example.com/stripe/webhook?', $command->question );
        $this->assertTrue( $command->confirmDefault );
        $this->assertSame( [
            ['vendor:publish', ['--tag' => 'cashier-migrations']],
            ['migrate', []],
            ['cashier:webhook', []],
            ['cms:cashier:check', []],
        ], $command->calls );
    }


    public function testCommandPublishesMigrationsWithoutRunningThem(): void
    {
        app()->instance( CashierSetup::class, new CashierSetupStub( 'paddle' ) );

        $command = new InstallCashierCommand();

        $this->assertSame( 0, $this->runCommand( $command, ['--no-migrate' => true] ) );
        $this->assertSame( [
            ['vendor:publish', ['--tag' => 'cashier-migrations']],
            ['cms:cashier:check', []],
        ], $command->calls );
    }


    public function testCommandRejectsForceWithoutMigrations(): void
    {
        app()->instance( CashierSetup::class, new CashierSetupStub( 'paddle' ) );

        $command = new InstallCashierCommand();

        $this->assertSame( 1, $this->runCommand( $command, ['--no-migrate' => true, '--force' => true] ) );
        $this->assertSame( [['cms:cashier:check', []]], $command->calls );
    }


    public function testCommandRejectsMissingProvider(): void
    {
        app()->instance( CashierSetup::class, new CashierSetupStub( null ) );

        $command = new InstallCashierCommand();

        $this->assertSame( 1, $this->runCommand( $command ) );
        $this->assertSame( [['cms:cashier:check', []]], $command->calls );
    }


    public function testCommandRunsMigrationsByDefault(): void
    {
        app()->instance( CashierSetup::class, new CashierSetupStub( 'paddle' ) );

        $command = new InstallCashierCommand();

        $this->assertSame( 0, $this->runCommand( $command ) );
        $this->assertSame( [
            ['vendor:publish', ['--tag' => 'cashier-migrations']],
            ['migrate', []],
            ['cms:cashier:check', []],
        ], $command->calls );
    }


    public function testCommandRunsMigrationsWithForce(): void
    {
        app()->instance( CashierSetup::class, new CashierSetupStub( 'paddle' ) );

        $command = new InstallCashierCommand();

        $this->assertSame( 0, $this->runCommand( $command, ['--force' => true] ) );
        $this->assertSame( [
            ['vendor:publish', ['--tag' => 'cashier-migrations']],
            ['migrate', ['--force' => true]],
            ['cms:cashier:check', []],
        ], $command->calls );
    }


    public function testCommandSkipsDefaultStripeWebhookNonInteractively(): void
    {
        app()->instance( CashierSetup::class, new CashierSetupStub( 'stripe' ) );

        $command = new InstallCashierCommand();

        $this->assertSame( 0, $this->runCommand( $command, ['--no-interaction' => true] ) );
        $this->assertNull( $command->question );
        $this->assertSame( [
            ['vendor:publish', ['--tag' => 'cashier-migrations']],
            ['migrate', []],
            ['cms:cashier:check', []],
        ], $command->calls );
    }


    public function testCommandStopsForExistingAccessColumn(): void
    {
        $setup = new CashierSetupStub( 'mollie' );
        $setup->conflict = 'Existing users.access conflict.';
        app()->instance( CashierSetup::class, $setup );

        $command = new InstallCashierCommand();

        $this->assertSame( 1, $this->runCommand( $command ) );
        $this->assertSame( [['cms:cashier:check', []]], $command->calls );
    }


    /**
     * Runs a test command through Symfony so options and output are initialized.
     *
     * @param array<string, mixed> $options
     */
    private function runCommand( InstallCashier $command, array $options = [] ) : int
    {
        $command->setLaravel( app() );
        $interactive = !( $options['--no-interaction'] ?? false );
        unset( $options['--no-interaction'] );
        $input = new ArrayInput( $options );
        $input->setInteractive( $interactive );

        return $command->run( $input, new BufferedOutput() );
    }


    /**
     * Registers the exact URL shown by the Stripe webhook prompt.
     */
    private function stripeRoute() : void
    {
        config()->set( 'app.url', 'https://example.com' );
        Route::post( 'stripe/webhook', fn() => '' )->name( 'cashier.webhook' );
        url()->forceRootUrl( 'https://example.com' );
        url()->forceScheme( 'https' );
    }
}


class CashierCheckSetupStub extends CashierSetup
{
    public function checks() : array
    {
        return [[
            'name' => 'Provider credentials',
            'ok' => false,
            'message' => 'Configure STRIPE_SECRET.',
        ]];
    }


    public function manual() : array
    {
        return [];
    }
}


class CashierSetupStub extends CashierSetup
{
    public ?string $conflict = null;


    public function __construct( private ?string $providerName )
    {
    }


    public function conflict() : ?string
    {
        return $this->conflict;
    }


    public function provider() : ?string
    {
        return $this->providerName;
    }


    public function providers() : array
    {
        return $this->providerName ? [$this->providerName] : [];
    }


    public function stripe() : ?string
    {
        return null;
    }
}


class InstallCashierCommand extends InstallCashier
{
    /** @var array<int, array{0: mixed, 1: array<string, mixed>}> */
    public array $calls = [];
    public bool $confirmed = false;
    public bool $confirmDefault = false;
    public ?string $question = null;


    /**
     * @param array<string, mixed> $arguments
     */
    public function call( mixed $command, array $arguments = [] ) : int
    {
        $this->calls[] = [$command, $arguments];

        return 0;
    }


    public function confirm( mixed $question, mixed $default = false ) : bool
    {
        $this->confirmDefault = (bool) $default;
        $this->question = (string) $question;

        return $this->confirmed;
    }
}
