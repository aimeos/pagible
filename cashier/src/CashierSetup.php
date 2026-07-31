<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms;

use Aimeos\Cms\Concerns\CashierAccess as CashierAccessTrait;
use Composer\InstalledVersions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;


/**
 * Inspects the local Pagible Cashier installation without changing it.
 */
class CashierSetup
{
    private const MIGRATION = '2026_07_26_000000_add_users_access';

    private const PROVIDERS = [
        'mollie' => [
            'billable' => 'Laravel\\Cashier\\Billable',
            'columns' => ['access', 'mollie_customer_id', 'mollie_mandate_id'],
            'config' => ['MOLLIE_KEY' => 'mollie.key'],
            'package' => 'aimeos/pagible-cashier-mollie',
            'tables' => ['orders', 'order_items', 'subscriptions', 'payments', 'refunds'],
        ],
        'paddle' => [
            'billable' => 'Laravel\\Paddle\\Billable',
            'columns' => ['access'],
            'config' => [
                'PADDLE_CLIENT_SIDE_TOKEN' => 'cashier.client_side_token',
                'PADDLE_API_KEY/PADDLE_AUTH_CODE' => 'cashier.api_key',
            ],
            'package' => 'aimeos/pagible-cashier-paddle',
            'tables' => ['customers', 'subscriptions', 'subscription_items', 'transactions'],
            'webhook' => ['PADDLE_WEBHOOK_SECRET' => 'cashier.webhook_secret'],
        ],
        'stripe' => [
            'billable' => 'Laravel\\Cashier\\Billable',
            'columns' => ['access', 'stripe_id'],
            'config' => ['STRIPE_KEY' => 'cashier.key', 'STRIPE_SECRET' => 'cashier.secret'],
            'package' => 'aimeos/pagible-cashier-stripe',
            'tables' => ['subscriptions', 'subscription_items'],
            'webhook' => ['STRIPE_WEBHOOK_SECRET' => 'cashier.webhook.secret'],
        ],
    ];


    /**
     * Returns all locally verifiable readiness checks.
     *
     * @return array<int, array{name: string, ok: bool, message: string}>
     */
    public function checks() : array
    {
        $provider = $this->provider();
        $providers = $this->providers();

        return [
            $this->result(
                'Payment provider',
                $provider !== null,
                $provider ? ucfirst( $provider ) . ' is the only installed Pagible Cashier provider.' : '',
                $providers === []
                    ? 'Install exactly one of the Stripe, Paddle, or Mollie provider packages.'
                    : 'More than one Pagible Cashier provider is installed: ' . implode( ', ', $providers ) . '.',
            ),
            $this->binding( $provider ),
            $this->key(),
            $this->url(),
            $this->login(),
            $this->routes( $provider ),
            $this->model(),
            $this->trait( CashierAccessTrait::class, 'CashierAccess' ),
            $this->trait( $provider ? self::PROVIDERS[$provider]['billable'] : null, 'provider Billable' ),
            $this->database( $provider ),
            $this->catalog(),
            $this->credentials( $provider ),
            $this->webhook( $provider ),
        ];
    }


    /**
     * Returns an existing users.access ownership conflict before migrations run.
     */
    public function conflict() : ?string
    {
        if( !Schema::hasTable( 'users' ) || !Schema::hasColumn( 'users', 'access' ) ) {
            return null;
        }

        if( Schema::hasTable( 'migrations' )
            && DB::table( 'migrations' )->where( 'migration', self::MIGRATION )->exists()
        ) {
            return null;
        }

        return 'The users.access column already exists but is not owned by the Pagible Cashier migration. '
            . 'Rename or remove the conflicting application column before migrating.';
    }


    /**
     * Returns provider-specific steps which require a dashboard or a real purchase.
     *
     * @return array<int, string>
     */
    public function manual() : array
    {
        $provider = $this->provider();
        $steps = match( $provider ) {
            'mollie' => [
                'Enable the required Mollie payment methods and use amounts plus currencies in CMS pricing.',
                'Run Laravel\'s scheduler continuously so cashier:run processes recurring payments every five minutes.',
                'Keep the generated Mollie webhook routes public and monitor failed or exhausted deliveries.',
            ],
            'paddle' => [
                'Create recurring and/or one-time Paddle prices and use their pri_... IDs in CMS pricing.',
                'Create a public /paddle/webhook notification destination with the required lifecycle and adjustment events.',
                'Complete a sandbox purchase, cancellation, refund, and chargeback test before using live credentials.',
            ],
            'stripe' => [
                'Create recurring and/or one-time Stripe prices and use their price_... IDs in CMS pricing.',
                'Create the verified webhook with cms:install:cashier --webhook or cashier:webhook and save its signing secret.',
                'Complete a test purchase, cancellation, refund, and dispute before using live credentials.',
            ],
            default => [],
        };

        return [...$steps,
            'Use the same access value in the pricing package and the restricted page, then publish both.',
            'Verify a guest is sent to the named login route and a paid user can open the restricted page and private files.',
        ];
    }


    /**
     * Returns the single installed provider name.
     */
    public function provider() : ?string
    {
        $providers = $this->providers();

        return count( $providers ) === 1 ? $providers[0] : null;
    }


    /**
     * Returns installed Pagible Cashier provider names.
     *
     * @return array<int, string>
     */
    public function providers() : array
    {
        return array_keys( array_filter(
            self::PROVIDERS,
            fn( array $provider ) => InstalledVersions::isInstalled( $provider['package'] ),
        ) );
    }


    /**
     * Returns a Stripe webhook creation preflight error.
     */
    public function stripe() : ?string
    {
        if( $this->provider() !== 'stripe' ) {
            return 'Stripe must be the installed Pagible Cashier provider.';
        }

        if( !$this->validUrl() ) {
            return 'Configure APP_URL as an absolute HTTP(S) URL before creating the Stripe webhook.';
        }

        if( !Route::has( 'cashier.webhook' ) ) {
            return 'Register the Stripe Cashier webhook route before creating the endpoint.';
        }

        $missing = $this->missing( self::PROVIDERS['stripe']['config'] );

        return $missing === [] ? null : 'Configure ' . implode( ', ', $missing ) . ' before creating the Stripe webhook.';
    }


    /**
     * Checks whether the provider service is registered.
     *
     * @return array{name: string, ok: bool, message: string}
     */
    private function binding( ?string $provider ) : array
    {
        return $this->result(
            'Provider registration',
            $provider !== null && app()->bound( CashierProvider::class ),
            'Laravel package discovery registered the Pagible provider.',
            $provider ? 'The provider package is installed but its service provider is not registered.'
                : 'Select one payment provider first.',
        );
    }


    /**
     * Checks the configured access catalog.
     *
     * @return array{name: string, ok: bool, message: string}
     */
    private function catalog() : array
    {
        try {
            $values = Permission::has( 'access:view' ) ? app( Access::class )->list() : [];
        } catch( \Throwable $e ) {
            return $this->result( 'Access catalog', false, '', 'Unable to load it: ' . $e->getMessage() );
        }

        return $this->result(
            'Access catalog',
            $values !== [],
            count( $values ) . ' frontend access value(s) are available.',
            'Configure Access::using() with at least one frontend access value, for example premium-members.',
        );
    }


    /**
     * Checks provider API credentials.
     *
     * @return array{name: string, ok: bool, message: string}
     */
    private function credentials( ?string $provider ) : array
    {
        $config = $provider ? self::PROVIDERS[$provider]['config'] : [];
        $missing = $this->missing( $config );

        if( $provider === 'mollie' && preg_match( '/^test_x+$/', (string) config( 'mollie.key' ) ) ) {
            $missing = ['MOLLIE_KEY'];
        }

        return $this->result(
            'Provider credentials',
            $provider !== null && $missing === [],
            'The required ' . ucfirst( (string) $provider ) . ' API credentials are configured.',
            $provider ? 'Configure ' . implode( ', ', $missing ) . '.' : 'Select one payment provider first.',
        );
    }


    /**
     * Checks the shared and provider database schema.
     *
     * @return array{name: string, ok: bool, message: string}
     */
    private function database( ?string $provider ) : array
    {
        try
        {
            if( !Schema::hasTable( 'users' ) ) {
                return $this->result( 'Database', false, '', 'The users table is missing. Run the application migrations.' );
            }

            if( $conflict = $this->conflict() ) {
                return $this->result( 'Database', false, '', $conflict );
            }

            $columns = $provider ? self::PROVIDERS[$provider]['columns'] : ['access'];
            $tables = $provider ? self::PROVIDERS[$provider]['tables'] : [];
            $missing = array_map( fn( string $name ) => 'users.' . $name, array_values( array_filter(
                $columns,
                fn( string $name ) => !Schema::hasColumn( 'users', $name ),
            ) ) );
            $missing = [...$missing, ...array_values( array_filter(
                $tables,
                fn( string $name ) => !Schema::hasTable( $name ),
            ) )];

            return $this->result(
                'Database',
                $provider !== null && $missing === [],
                'The shared and provider Cashier schema is present.',
                $missing === [] ? 'Select one payment provider first.' : 'Run cms:install:cashier; missing: '
                    . implode( ', ', $missing ) . '.',
            );
        }
        catch( \Throwable $e ) {
            return $this->result( 'Database', false, '', 'Unable to inspect it: ' . $e->getMessage() );
        }
    }


    /**
     * Checks the application encryption key.
     *
     * @return array{name: string, ok: bool, message: string}
     */
    private function key() : array
    {
        $ok = trim( (string) config( 'app.key' ) ) !== '';

        return $this->result( 'APP_KEY', $ok, 'The application key is configured.', 'Run php artisan key:generate.' );
    }


    /**
     * Checks the named login route used by checkout.
     *
     * @return array{name: string, ok: bool, message: string}
     */
    private function login() : array
    {
        return $this->result(
            'Login route',
            Route::has( 'login' ),
            'The named login route is available.',
            'Define the named login route required for guest checkout.',
        );
    }


    /**
     * Returns missing configuration labels.
     *
     * @param array<string, string> $config
     * @return array<int, string>
     */
    private function missing( array $config ) : array
    {
        return array_keys( array_filter(
            $config,
            fn( string $key ) => trim( (string) config( $key ) ) === '',
        ) );
    }


    /**
     * Checks the authentication model.
     *
     * @return array{name: string, ok: bool, message: string}
     */
    private function model() : array
    {
        $model = $this->modelClass();

        return $this->result(
            'User model',
            $model !== null && class_exists( $model ),
            'Cashier uses ' . (string) $model . '.',
            $model ? 'The configured authentication model does not exist: ' . $model . '.'
                : 'Configure an Eloquent authentication provider model.',
        );
    }


    /**
     * Returns the authentication model class.
     */
    private function modelClass() : ?string
    {
        $guard = config( 'auth.defaults.guard' );
        $provider = is_string( $guard ) ? config( 'auth.guards.' . $guard . '.provider' ) : null;
        $model = is_string( $provider ) ? config( 'auth.providers.' . $provider . '.model' ) : null;
        $model ??= config( 'auth.providers.users.model' );

        return is_string( $model ) && trim( $model ) !== '' ? $model : null;
    }


    /**
     * Creates a readiness result.
     *
     * @return array{name: string, ok: bool, message: string}
     */
    private function result( string $name, bool $ok, string $success, string $failure ) : array
    {
        return ['name' => $name, 'ok' => $ok, 'message' => $ok ? $success : $failure];
    }


    /**
     * Checks shared checkout routes and provider webhook routes.
     *
     * @return array{name: string, ok: bool, message: string}
     */
    private function routes( ?string $provider ) : array
    {
        $routes = ['cms.cashier', 'cms.cashier.cancel'];
        $routes = [...$routes, ...match( $provider ) {
            'mollie' => ['webhooks.mollie.default', 'webhooks.mollie.aftercare', 'webhooks.mollie.first_payment'],
            'paddle', 'stripe' => ['cashier.webhook'],
            default => [],
        }];
        $missing = array_values( array_filter( $routes, fn( string $name ) => !Route::has( $name ) ) );

        return $this->result(
            'Payment routes',
            $provider !== null && $missing === [],
            'Checkout, cancellation, and provider webhook routes are registered.',
            $missing === [] ? 'Select one payment provider first.' : 'Missing route(s): ' . implode( ', ', $missing ) . '.',
        );
    }


    /**
     * Checks a required user model trait.
     *
     * @return array{name: string, ok: bool, message: string}
     */
    private function trait( ?string $trait, string $label ) : array
    {
        $model = $this->modelClass();
        $traits = $model && class_exists( $model ) ? class_uses_recursive( $model ) : [];
        $ok = $trait !== null && in_array( $trait, $traits, true );

        return $this->result(
            ucfirst( $label ) . ' trait',
            $ok,
            $model . ' uses ' . $trait . '.',
            $trait ? 'Add ' . $trait . ' to ' . ( $model ?: 'the authentication model' ) . '.'
                : 'Select one payment provider first.',
        );
    }


    /**
     * Checks the application URL.
     *
     * @return array{name: string, ok: bool, message: string}
     */
    private function url() : array
    {
        $url = (string) config( 'app.url' );
        $https = parse_url( $url, PHP_URL_SCHEME ) === 'https';
        $ok = $this->validUrl() && ( !app()->environment( 'production' ) || $https );

        return $this->result(
            'APP_URL',
            $ok,
            $https ? 'The application uses an absolute HTTPS URL.' : 'The application uses an absolute HTTP(S) URL.',
            'Configure an absolute HTTPS APP_URL for production checkout and webhooks.',
        );
    }


    /**
     * Tests the configured application URL.
     */
    private function validUrl() : bool
    {
        $url = (string) config( 'app.url' );
        $scheme = parse_url( $url, PHP_URL_SCHEME );

        return filter_var( $url, FILTER_VALIDATE_URL ) !== false && in_array( $scheme, ['http', 'https'], true );
    }


    /**
     * Checks the provider webhook secret when one is required.
     *
     * @return array{name: string, ok: bool, message: string}
     */
    private function webhook( ?string $provider ) : array
    {
        $config = $provider ? self::PROVIDERS[$provider]['webhook'] ?? [] : [];
        $missing = $this->missing( $config );
        $ok = $provider !== null && $missing === [];

        return $this->result(
            'Webhook verification',
            $ok,
            $config === [] ? 'The Mollie webhook paths are bound to APP_KEY.' : 'The provider webhook secret is configured.',
            $provider ? 'Configure ' . implode( ', ', $missing ) . '.' : 'Select one payment provider first.',
        );
    }
}
