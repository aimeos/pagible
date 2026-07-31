<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Tests;

use Aimeos\Cms\CashierAccess;
use Aimeos\Cms\CashierProvider;
use Aimeos\Cms\CashierToken;
use Aimeos\Cms\Tenancy;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;


class CashierTokenTest extends CashierTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    public function testCustomTenantMembershipIsUsedAndStored(): void
    {
        $user = \App\Models\User::forceCreate( [
            'name' => 'Test',
            'email' => 'member@example.com',
            'password' => 'password',
        ] );
        Tenancy::$access = fn( $candidate, string $tenant ) =>
            $candidate->getAuthIdentifier() === $user->getAuthIdentifier() && $tenant === 'member';

        try
        {
            $tokens = app( CashierToken::class );
            $token = Tenancy::run( 'member', fn() => $tokens->create( $user, 'test', [
                'access' => 'frontend.pro',
                'kind' => 'once',
                'reference' => 'price_test',
            ] ) );

            ( new CashierTokenDriver( app( CashierAccess::class ), $tokens ) )->receive( [
                'metadata' => ['cms' => $token],
            ] );

            $access = $user->refresh()->getAttribute( 'access' );
            $this->assertIsArray( $access );
            $this->assertSame(
                'frontend.pro',
                $access['member|test|source']['role'] ?? null,
            );
            $this->assertSame(
                ['frontend.pro'],
                Tenancy::run( 'member', fn() => app( CashierAccess::class )->roles( $user ) ),
            );
            $this->assertSame(
                [],
                Tenancy::run( 'test', fn() => app( CashierAccess::class )->roles( $user ) ),
            );
        }
        finally {
            Tenancy::$access = null;
        }
    }


    public function testEloquentPrimaryKeyIsSignedAndResolved(): void
    {
        config()->set( 'auth.providers.users.model', CashierAuthUser::class );
        $user = CashierAuthUser::forceCreate( [
            'name' => 'Test',
            'email' => 'auth@example.com',
            'password' => 'password',
        ] );
        $tokens = app( CashierToken::class );
        $token = $tokens->create( $user, 'test', [
            'access' => 'frontend.pro',
            'kind' => 'once',
            'reference' => 'price_test',
        ] );

        ( new CashierTokenDriver( app( CashierAccess::class ), $tokens ) )->receive( [
            'metadata' => ['cms' => $token],
        ] );

        $this->assertSame( 'auth@example.com', $user->getAuthIdentifier() );
        $this->assertSame( (string) $user->getKey(), $tokens->read( $token )['user'] ?? null );
        $this->assertSame(
            'frontend.pro',
            $user->refresh()->getAttribute( 'access' )['test|test|source']['role'] ?? null,
        );
    }


    public function testPreviousApplicationKeyVerifiesExistingToken(): void
    {
        $old = 'base64:' . base64_encode( 'old-cashier-signing-key' );
        $new = 'base64:' . base64_encode( 'new-cashier-signing-key' );
        $tokens = app( CashierToken::class );

        config()->set( 'app.key', $old );
        config()->set( 'app.previous_keys', [] );
        $token = $tokens->make( ['plan' => 'cms.monthly'] );

        config()->set( 'app.key', $new );
        config()->set( 'app.previous_keys', [$old] );
        $this->assertSame( ['plan' => 'cms.monthly'], $tokens->parse( $token ) );

        config()->set( 'app.previous_keys', [] );
        $this->assertNull( $tokens->parse( $token ) );
    }
}


class CashierAuthUser extends \App\Models\User
{
    protected $table = 'users';


    public function getAuthIdentifierName() : string
    {
        return 'email';
    }
}


class CashierTokenDriver extends CashierProvider
{
    protected string $provider = 'test';


    public function cancel( Authenticatable $user, string $subscription ) : void
    {
    }


    /**
     * @param array<string, mixed> $data
     */
    public function receive( array $data ) : void
    {
        $this->grant( $data, 'source', 'once' );
    }


    protected function start( Authenticatable $user, array $product, array $metadata ) : mixed
    {
        return null;
    }
}
