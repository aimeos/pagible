<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;


class InstallCashierTest extends CashierTestAbstract
{
    public function testWriteEnvNew()
    {
        $path = base_path( '.env' );
        $backup = file_exists( $path ) ? file_get_contents( $path ) : null;

        try {
            file_put_contents( $path, "APP_NAME=Pagible\n" );

            $command = new \Aimeos\Cms\Commands\InstallCashier();
            $method = new \ReflectionMethod( $command, 'writeEnv' );

            $method->invoke( $command, ['CMS_CASHIER_PROVIDER' => 'stripe', 'STRIPE_KEY' => 'pk_test_123'] );

            $content = file_get_contents( $path );

            $this->assertStringContainsString( 'CMS_CASHIER_PROVIDER=stripe', $content );
            $this->assertStringContainsString( 'STRIPE_KEY=pk_test_123', $content );
            $this->assertStringContainsString( 'APP_NAME=Pagible', $content );
        } finally {
            if( $backup !== null ) {
                file_put_contents( $path, $backup );
            } else {
                @unlink( $path );
            }
        }
    }


    public function testWriteEnvUpdate()
    {
        $path = base_path( '.env' );
        $backup = file_exists( $path ) ? file_get_contents( $path ) : null;

        try {
            file_put_contents( $path, "CMS_CASHIER_PROVIDER=paddle\nSTRIPE_KEY=old\n" );

            $command = new \Aimeos\Cms\Commands\InstallCashier();
            $method = new \ReflectionMethod( $command, 'writeEnv' );

            $method->invoke( $command, ['CMS_CASHIER_PROVIDER' => 'stripe', 'STRIPE_KEY' => 'pk_test_new'] );

            $content = file_get_contents( $path );

            $this->assertStringContainsString( 'CMS_CASHIER_PROVIDER=stripe', $content );
            $this->assertStringContainsString( 'STRIPE_KEY=pk_test_new', $content );
            $this->assertStringNotContainsString( 'paddle', $content );
            $this->assertStringNotContainsString( 'old', $content );
        } finally {
            if( $backup !== null ) {
                file_put_contents( $path, $backup );
            } else {
                @unlink( $path );
            }
        }
    }


    public function testWriteEnvSkipsEmpty()
    {
        $path = base_path( '.env' );
        $backup = file_exists( $path ) ? file_get_contents( $path ) : null;

        try {
            file_put_contents( $path, '' );

            $command = new \Aimeos\Cms\Commands\InstallCashier();
            $method = new \ReflectionMethod( $command, 'writeEnv' );

            $method->invoke( $command, ['CMS_CASHIER_PROVIDER' => 'stripe', 'EMPTY_VAR' => '', 'NULL_VAR' => null] );

            $content = file_get_contents( $path );

            $this->assertStringContainsString( 'CMS_CASHIER_PROVIDER=stripe', $content );
            $this->assertStringNotContainsString( 'EMPTY_VAR', $content );
            $this->assertStringNotContainsString( 'NULL_VAR', $content );
        } finally {
            if( $backup !== null ) {
                file_put_contents( $path, $backup );
            } else {
                @unlink( $path );
            }
        }
    }


    public function testWriteEnvEscapesSpaces()
    {
        $path = base_path( '.env' );
        $backup = file_exists( $path ) ? file_get_contents( $path ) : null;

        try {
            file_put_contents( $path, '' );

            $command = new \Aimeos\Cms\Commands\InstallCashier();
            $method = new \ReflectionMethod( $command, 'writeEnv' );

            $method->invoke( $command, ['APP_NAME' => 'My App'] );

            $content = file_get_contents( $path );

            $this->assertStringContainsString( 'APP_NAME="My App"', $content );
        } finally {
            if( $backup !== null ) {
                file_put_contents( $path, $backup );
            } else {
                @unlink( $path );
            }
        }
    }


    public function testBillableFileNotFound()
    {
        $command = new \Aimeos\Cms\Commands\InstallCashier();
        $method = new \ReflectionMethod( $command, 'billable' );

        // Should not throw when file doesn't exist
        $method->invoke( $command, 'stripe' );

        $this->assertTrue( true );
    }


    public function testBillableAddsTraitStripe()
    {
        $path = base_path( 'app/Models/User.php' );
        $dir = dirname( $path );

        if( !is_dir( $dir ) ) {
            mkdir( $dir, 0755, true );
        }

        $original = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory;
}
PHP;

        try {
            file_put_contents( $path, $original );

            $command = new \Aimeos\Cms\Commands\InstallCashier();
            $method = new \ReflectionMethod( $command, 'billable' );
            $method->invoke( $command, 'stripe' );

            $content = file_get_contents( $path );

            $this->assertStringContainsString( 'use Laravel\Cashier\Billable;', $content );
            $this->assertStringContainsString( 'use Billable;', $content );
        } finally {
            @unlink( $path );
        }
    }


    public function testBillableAlreadyAdded()
    {
        $path = base_path( 'app/Models/User.php' );
        $dir = dirname( $path );

        if( !is_dir( $dir ) ) {
            mkdir( $dir, 0755, true );
        }

        $original = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Cashier\Billable;

class User extends Authenticatable
{
    use Billable;
}
PHP;

        try {
            file_put_contents( $path, $original );

            $this->artisan( 'cms:install:cashier' )
                ->expectsQuestion( 'Which payment provider do you want to use?', 'stripe' )
                ->expectsQuestion( 'Stripe Publishable Key', 'pk_test_123' )
                ->expectsQuestion( 'Stripe Secret Key', 'sk_test_123' );

            $content = file_get_contents( $path );

            $this->assertStringContainsString( 'use Laravel\Cashier\Billable;', $content );
        } finally {
            @unlink( $path );
        }
    }
}
