<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\CashierServiceProvider;
use Illuminate\Console\Command;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;


class InstallCashier extends Command
{
    /**
     * Command name
     */
    protected $signature = 'cms:install:cashier';

    /**
     * Command description
     */
    protected $description = 'Installing Pagible CMS cashier package';


    /**
     * Execute command
     */
    public function handle(): int
    {
        $result = 0;

        $this->comment( '  Publishing CMS cashier configuration ...' );
        $result += $this->call( 'vendor:publish', ['--provider' => 'Aimeos\Cms\CashierServiceProvider'] );

        if( $this->option( 'quiet' ) )
        {
            $provider = (string) config( 'cms.cashier.provider', 'stripe' );
        }
        else
        {
            $provider = (string) select(
                label: 'Which payment provider do you want to use?',
                options: [
                    'stripe' => 'Stripe',
                    'paddle' => 'Paddle',
                    'mollie' => 'Mollie',
                ],
            );

            /** @var array<string, string> $env */
            $env = ['CMS_CASHIER_PROVIDER' => $provider];

            match( $provider ) {
                'stripe' => $this->setupStripe( $env ),
                'paddle' => $this->setupPaddle( $env ),
                'mollie' => $this->setupMollie( $env ),
                default => null,
            };

            $this->writeEnv( $env );
            info( 'Environment variables written to .env file.' );
        }

        $this->composer( $provider );
        $this->billable( $provider );

        if( !$this->option( 'quiet' ) )
        {
            match( $provider ) {
                'stripe' => $this->webhookStripe(),
                'paddle' => $this->webhookPaddle(),
                'mollie' => $this->webhookMollie(),
                default => null,
            };
        }

        return $result ? 1 : 0;
    }


    /**
     * Adds the Billable trait to app/Models/User.php or prints instructions
     */
    protected function billable( string $provider ): void
    {
        if( !isset( CashierServiceProvider::PROVIDERS[$provider] ) ) {
            return;
        }

        $trait = CashierServiceProvider::PROVIDERS[$provider][1] . '\Billable';

        $path = base_path( 'app/Models/User.php' );

        if( !file_exists( $path ) )
        {
            if( $this->output && !$this->option( 'quiet' ) )
            {
                note( '' );
                note( 'Add the Billable trait to your User model:' );
                note( '' );
                note( "  use {$trait};" );
                note( '' );
                note( '  class User extends Authenticatable' );
                note( '  {' );
                note( '      use Billable;' );
                note( '  }' );
                note( '' );
            }
            return;
        }

        $content = (string) file_get_contents( $path );

        if( str_contains( $content, $trait ) ) {
            $this->line( '  Billable trait already added to User model' );
            return;
        }

        $use = "use {$trait};";

        // Add import after last use statement
        if( preg_match( '/^(use\s+[^;]+;)\s*\n(?!use\s)/m', $content, $matches, PREG_OFFSET_CAPTURE ) ) {
            $pos = (int) $matches[1][1] + strlen( (string) $matches[1][0] );
            $content = substr_replace( $content, "\n{$use}", $pos, 0 );
        }

        // Add trait use inside class body
        if( preg_match( '/\{(\s*use\s+\w)/m', $content, $matches, PREG_OFFSET_CAPTURE ) ) {
            $pos = (int) $matches[1][1];
            $content = substr_replace( $content, "use Billable;\n    ", $pos, 0 );
        } elseif( preg_match( '/class\s+User[^{]*\{/m', $content, $matches, PREG_OFFSET_CAPTURE ) ) {
            $pos = (int) $matches[0][1] + strlen( (string) $matches[0][0] );
            $content = substr_replace( $content, "\n    use Billable;\n", $pos, 0 );
        }

        file_put_contents( $path, $content );

        if( $this->output && !$this->option( 'quiet' ) ) {
            info( 'Billable trait added to app/Models/User.php' );
        }
    }


    /**
     * Installs the required Composer package for the selected provider
     */
    protected function composer( string $provider ): void
    {
        if( !isset( CashierServiceProvider::PROVIDERS[$provider] ) ) {
            return;
        }

        [$package, $namespace] = CashierServiceProvider::PROVIDERS[$provider];

        if( class_exists( $namespace . '\CashierServiceProvider' ) ) {
            return;
        }

        $this->comment( "  Installing {$package} ..." );

        $process = new \Symfony\Component\Process\Process(
            ['composer', 'require', $package, '--no-scripts'],
            base_path()
        );

        $process->setTimeout( 300 );

        if( $this->option( 'quiet' ) ) {
            $process->run();
        } else {
            $process->run( fn( $type, $buffer ) => $this->getOutput()->write( $buffer ) );
        }

        if( !$process->isSuccessful() ) {
            $this->error( "  Failed to install {$package}. Please run: composer require {$package}" );
        }
    }


    /**
     * Collects Mollie credentials
     *
     * @param array<string, string> $env
     */
    protected function setupMollie( array &$env ): void
    {
        $env['MOLLIE_KEY'] = text(
            label: 'Mollie API Key',
            placeholder: 'test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
            required: true,
        );

        $env['CMS_CASHIER_SUCCESS_URL'] = text(
            label: 'Success redirect URL (after payment)',
            default: '/',
        );

        $env['CMS_CASHIER_CANCEL_URL'] = text(
            label: 'Cancel redirect URL',
            default: '/',
        );
    }


    /**
     * Collects Paddle credentials
     *
     * @param array<string, string> $env
     */
    protected function setupPaddle( array &$env ): void
    {
        $env['PADDLE_SELLER_ID'] = text(
            label: 'Paddle Seller ID',
            required: true,
        );

        $env['PADDLE_AUTH_CODE'] = text(
            label: 'Paddle API Auth Code',
            required: true,
        );

        $env['PADDLE_RETAIN_KEY'] = text(
            label: 'Paddle Retain Key (optional)',
        );

        $env['PADDLE_SANDBOX'] = (string) select(
            label: 'Use Paddle Sandbox?',
            options: ['true' => 'Yes (testing)', 'false' => 'No (production)'],
            default: 'true',
        );

        $env['CMS_CASHIER_SUCCESS_URL'] = text(
            label: 'Success redirect URL (after payment)',
            default: '/',
        );
    }


    /**
     * Collects Stripe credentials
     *
     * @param array<string, string> $env
     */
    protected function setupStripe( array &$env ): void
    {
        $env['STRIPE_KEY'] = text(
            label: 'Stripe Publishable Key',
            placeholder: 'pk_test_...',
            required: true,
        );

        $env['STRIPE_SECRET'] = text(
            label: 'Stripe Secret Key',
            placeholder: 'sk_test_...',
            required: true,
        );

        $env['CMS_CASHIER_SUCCESS_URL'] = text(
            label: 'Success redirect URL (after payment)',
            default: '/',
        );

        $env['CMS_CASHIER_CANCEL_URL'] = text(
            label: 'Cancel redirect URL',
            default: '/',
        );
    }


    /**
     * Prints Mollie webhook instructions
     */
    protected function webhookMollie(): void
    {
        $url = url( '/mollie/webhook' );

        note( '' );
        note( '── Webhook Setup ──' );
        note( 'Mollie webhooks must be configured manually:' );
        note( '' );
        note( '  1. Log in to your Mollie Dashboard: https://my.mollie.com' );
        note( '  2. Go to Developers > Webhooks' );
        note( "  3. Set the webhook URL to: {$url}" );
        note( '  4. Save the webhook configuration' );
        note( '' );
        note( 'The webhook handles payment status updates (paid, failed, refunded).' );
        note( 'Make sure your application is accessible from the internet.' );
        note( '' );
    }


    /**
     * Prints Paddle webhook instructions
     */
    protected function webhookPaddle(): void
    {
        $url = url( '/paddle/webhook' );

        note( '' );
        note( '── Webhook Setup ──' );
        note( 'Paddle webhooks must be configured manually:' );
        note( '' );
        note( '  1. Log in to your Paddle Dashboard: https://vendors.paddle.com' );
        note( '     (Sandbox: https://sandbox-vendors.paddle.com)' );
        note( '  2. Go to Developer Tools > Notifications' );
        note( '  3. Click "New Destination"' );
        note( "  4. Set the endpoint URL to: {$url}" );
        note( '  5. Select these events:' );
        note( '     - subscription.created' );
        note( '     - subscription.updated' );
        note( '     - subscription.paused' );
        note( '     - subscription.canceled' );
        note( '     - subscription.activated' );
        note( '     - transaction.completed' );
        note( '     - transaction.updated' );
        note( '  6. Save the notification destination' );
        note( '' );
        note( 'Copy the webhook secret and add it to your .env file:' );
        note( '  PADDLE_WEBHOOK_SECRET=pdl_ntfset_...' );
        note( '' );
    }


    /**
     * Registers Stripe webhook automatically
     */
    protected function webhookStripe(): void
    {
        note( '' );
        note( '── Webhook Setup ──' );

        if( class_exists( CashierServiceProvider::PROVIDERS['stripe'][1] . '\CashierServiceProvider' ) )
        {
            $this->comment( '  Registering Stripe webhook ...' );
            $this->call( 'cashier:webhook' );

            note( '' );
            note( 'Stripe webhook registered automatically.' );
            note( 'Copy the webhook signing secret from your Stripe Dashboard' );
            note( 'and add it to your .env file:' );
            note( '  STRIPE_WEBHOOK_SECRET=whsec_...' );
        }
        else
        {
            $url = url( '/stripe/webhook' );

            note( '' );
            note( 'Install laravel/cashier and run "php artisan cashier:webhook"' );
            note( 'to register the webhook automatically, or configure it manually:' );
            note( '' );
            note( '  1. Log in to your Stripe Dashboard: https://dashboard.stripe.com' );
            note( '  2. Go to Developers > Webhooks' );
            note( '  3. Click "Add endpoint"' );
            note( "  4. Set the endpoint URL to: {$url}" );
            note( '  5. Select these events:' );
            note( '     - customer.subscription.created' );
            note( '     - customer.subscription.updated' );
            note( '     - customer.subscription.deleted' );
            note( '     - customer.updated' );
            note( '     - customer.deleted' );
            note( '     - invoice.payment_action_required' );
            note( '     - invoice.payment_succeeded' );
            note( '  6. Copy the signing secret and add it to your .env file:' );
            note( '     STRIPE_WEBHOOK_SECRET=whsec_...' );
        }

        note( '' );
    }


    /**
     * Writes environment variables to the .env file
     *
     * @param array<string, string|null> $vars
     */
    protected function writeEnv( array $vars ): void
    {
        $path = base_path( '.env' );
        $content = file_exists( $path ) ? (string) file_get_contents( $path ) : '';

        foreach( $vars as $key => $value )
        {
            if( $value === '' || $value === null ) {
                continue;
            }

            $escaped = str_contains( $value, ' ' ) ? "\"{$value}\"" : $value;

            $replaced = (string) preg_replace( "/^{$key}=.*/m", "{$key}={$escaped}", $content, 1, $count );
            $content = $count ? $replaced : rtrim( $content ) . "\n{$key}={$escaped}\n";
        }

        file_put_contents( $path, $content );
    }
}
