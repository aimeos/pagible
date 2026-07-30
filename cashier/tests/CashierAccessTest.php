<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Tests;

use Aimeos\Cms\Access;
use Aimeos\Cms\CashierAccess;
use Aimeos\Cms\CashierProvider;
use Aimeos\Cms\CashierToken;
use Aimeos\Cms\Concerns\CashierAccess as CashierAccessConcern;
use Aimeos\Cms\Tenancy;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;


class CashierAccessTest extends CashierTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    private \App\Models\User $stored;


    protected function setUp(): void
    {
        parent::setUp();

        $this->stored = \App\Models\User::forceCreate( [
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'password',
        ] );
    }


    public function testAccessIsHiddenAndCast(): void
    {
        app( CashierAccess::class )->grant(
            $this->stored,
            $this->tenant(),
            'frontend.once',
            'stripe',
            'pi_once',
            null,
        );

        $user = \App\Models\User::findOrFail( $this->stored->id );

        $this->assertIsArray( $this->storedAccess( $user ) );
        $this->assertArrayNotHasKey( 'access', $user->toArray() );
    }


    public function testAccessIsGuardedForUnguardedUserModels(): void
    {
        $user = new class extends \Illuminate\Database\Eloquent\Model {
            use CashierAccessConcern;

            protected $guarded = [];
        };

        $user->fill( ['access' => ['stripe:forged' => ['role' => 'frontend.pro']]] );

        $this->assertTrue( $user->isGuarded( 'access' ) );
        $this->assertNull( $user->getAttribute( 'access' ) );
    }


    public function testAccessResolverMergesActiveCashierRoles(): void
    {
        Access::using(
            list: fn() => ['frontend.expired', 'frontend.once', 'frontend.pro', 'member'],
            grants: fn() => ['member'],
        );
        $this->stored->forceFill( ['access' => [
            $this->tenant() . '|stripe|sub_expired' => [
                'role' => 'frontend.expired',
                'end' => now()->subMinute()->getTimestampMs(),
            ],
            $this->tenant() . '|paddle|txn_once' => [
                'role' => 'frontend.once',
                'end' => null,
            ],
            $this->tenant() . '|stripe|sub_pro' => [
                'role' => 'frontend.pro',
                'end' => now()->addMinute()->getTimestampMs(),
            ],
        ]] );

        $this->assertSame(
            ['frontend.once', 'frontend.pro', 'member'],
            app( Access::class )->allowed( $this->stored ),
        );
        $this->assertSame(
            ['frontend.pro'],
            app( Access::class )->allowed( $this->stored, ['frontend.pro', 'frontend.expired'] ),
        );
    }


    public function testDuplicateMutationsDoNotUpdateUser(): void
    {
        $access = app( CashierAccess::class );
        $end = now()->addMonth()->startOfSecond();
        $at = now()->startOfSecond();

        $access->grant(
            $this->stored, $this->tenant(), 'frontend.pro', 'stripe', 'sub_1', $end, $at,
        );
        $access->remove( $this->stored, $this->tenant(), 'stripe', 'missing', $at );
        DB::flushQueryLog();
        DB::enableQueryLog();

        $access->grant(
            $this->stored, $this->tenant(), 'frontend.pro', 'stripe', 'sub_1', $end, $at,
        );
        $access->remove( $this->stored, $this->tenant(), 'stripe', 'missing', $at );

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount( 2, $queries );

        foreach( $queries as $query ) {
            $this->assertStringStartsWith( 'select ', strtolower( $query['query'] ) );
        }
    }


    public function testAvailableChecksCheckoutCapacity(): void
    {
        $access = [];

        for( $i = 0; $i < CashierAccess::LIMIT - 1; $i++ ) {
            $access[$this->tenant() . '|stripe|old_' . $i] = [
                'role' => null,
                'end' => null,
                'at' => $i,
            ];
        }

        $this->stored->forceFill( ['access' => $access] )->saveQuietly();
        $projection = app( CashierAccess::class );
        $this->assertTrue( $projection->available( $this->stored ) );

        $access[$this->tenant() . '|stripe|last'] = [
            'role' => null,
            'end' => null,
            'at' => CashierAccess::LIMIT,
        ];
        $this->stored->forceFill( ['access' => $access] )->saveQuietly();

        $this->assertFalse( $projection->available( $this->stored ) );
    }


    public function testGrantKeepsIndependentSourcesAndRenewalReplacesOne(): void
    {
        $access = app( CashierAccess::class );
        $first = now()->addMonth()->startOfSecond();
        $renewed = now()->addMonths( 2 )->startOfSecond();
        $created = now()->startOfSecond();
        $updated = $created->copy()->addSecond();

        $access->grant(
            $this->stored, $this->tenant(), 'frontend.pro', 'stripe', 'sub_1', $first, $created,
        );
        $access->grant(
            $this->stored, $this->tenant(), 'frontend.pro', 'paddle', 'txn_1', null, $created,
        );
        $access->grant(
            $this->stored, $this->tenant(), 'frontend.pro', 'stripe', 'sub_1', $renewed, $updated,
        );

        $stored = $this->storedAccess( $this->stored );
        $this->assertIsArray( $stored );

        $this->assertCount( 2, $stored );
        $this->assertSame( [
            'role' => 'frontend.pro',
            'end' => $renewed->timestamp * 1000,
            'at' => $updated->timestamp * 1000,
        ], $stored[$this->tenant() . '|stripe|sub_1'] );
    }


    public function testGrantMovesProviderSourceBetweenRoles(): void
    {
        $access = app( CashierAccess::class );
        $created = now()->startOfSecond();

        $access->grant(
            $this->stored, $this->tenant(), 'frontend.old', 'stripe', 'sub_1',
            now()->addMonth(), $created,
        );
        $access->grant(
            $this->stored, $this->tenant(), 'frontend.new', 'stripe', 'sub_1',
            now()->addMonth(), $created->copy()->addSecond(),
        );

        $stored = $this->storedAccess( $this->stored );
        $this->assertIsArray( $stored );
        $this->assertSame( 'frontend.new', $stored[$this->tenant() . '|stripe|sub_1']['role'] );
    }


    public function testNewerRevocationRejectsDelayedGrant(): void
    {
        $access = app( CashierAccess::class );
        $created = now()->subMinutes( 2 )->startOfSecond();
        $revoked = $created->copy()->addMinute();

        $access->remove( $this->stored, $this->tenant(), 'stripe', 'pi_1', $revoked );
        $access->grant(
            $this->stored,
            $this->tenant(),
            'frontend.course',
            'stripe',
            'pi_1',
            null,
            $created,
        );

        $stored = $this->storedAccess( $this->stored );
        $this->assertIsArray( $stored );
        $this->assertSame( [
            'role' => null,
            'end' => null,
            'at' => $revoked->timestamp * 1000,
        ], $stored[$this->tenant() . '|stripe|pi_1'] );
        $this->assertSame( [], $access->roles( $this->stored ) );
    }


    public function testOwnerResolvedRevocationCannotCreateUnknownTombstone(): void
    {
        $access = app( CashierAccess::class );
        $created = now()->subMinutes( 2 )->startOfSecond();
        $revoked = $created->copy()->addMinute();
        $provider = new class(
            $access,
            app( CashierToken::class ),
            $this->stored,
        ) extends CashierProvider {
            protected string $provider = 'stripe';


            public function __construct( CashierAccess $access, CashierToken $tokens,
                private Authenticatable $owner
            ) {
                parent::__construct( $access, $tokens );
            }


            public function cancel( Authenticatable $user, string $subscription ) : void
            {
            }


            public function revoke( string $id, \DateTimeInterface $at ) : void
            {
                $this->remove( [], $id, $at );
            }


            protected function owner( array|object $data, string $id ) : Authenticatable
            {
                return $this->owner;
            }


            protected function start( Authenticatable $user, array $product, array $metadata ) : mixed
            {
                return null;
            }
        };

        $provider->revoke( 'pi_1', $revoked );
        $access->grant(
            $this->stored,
            $this->tenant(),
            'frontend.course',
            'stripe',
            'pi_1',
            null,
            $created,
        );

        $stored = $this->storedAccess( $this->stored );
        $this->assertIsArray( $stored );
        $this->assertSame( [
            'role' => 'frontend.course',
            'end' => null,
            'at' => $created->getTimestamp() * 1000,
        ], $stored[$this->tenant() . '|stripe|pi_1'] );
        $this->assertArrayNotHasKey( '|stripe|pi_1', $stored );
        $this->assertSame( ['frontend.course'], $access->roles( $this->stored ) );
    }


    public function testPaymentSourceRejectsSeparator(): void
    {
        $this->expectException( \InvalidArgumentException::class );

        app( CashierAccess::class )->grant(
            $this->stored,
            $this->tenant(),
            'frontend.pro',
            'stripe',
            'sub|1',
            now()->addMonth(),
        );
    }


    public function testRemoveOnlyDropsMatchingSource(): void
    {
        $access = app( CashierAccess::class );

        $access->grant(
            $this->stored, $this->tenant(), 'frontend.pro', 'stripe', 'sub_1', now()->addMonth(),
        );
        $access->grant( $this->stored, $this->tenant(), 'frontend.pro', 'paddle', 'txn_1', null );
        $access->remove( $this->stored, $this->tenant(), 'stripe', 'sub_1' );

        $stored = $this->storedAccess( $this->stored );
        $this->assertIsArray( $stored );
        $this->assertSame( [
            $this->tenant() . '|paddle|txn_1' => [
                'role' => 'frontend.pro',
                'end' => null,
                'at' => $stored[$this->tenant() . '|paddle|txn_1']['at'],
            ],
            $this->tenant() . '|stripe|sub_1' => [
                'role' => null,
                'end' => null,
                'at' => $stored[$this->tenant() . '|stripe|sub_1']['at'],
            ],
        ], $stored );
    }


    public function testRemoveUnknownTenantDropsMatchingSources(): void
    {
        $access = app( CashierAccess::class );
        $created = now()->startOfSecond();
        $revoked = $created->copy()->addSecond();

        $access->grant( $this->stored, 'tenant|a', 'frontend.pro', 'stripe', 'sub_1', now()->addMonth(), $created );
        $access->grant( $this->stored, 'tenant-b', 'frontend.pro', 'stripe', 'sub_1', now()->addMonth(), $created );
        $access->grant( $this->stored, 'tenant-b', 'frontend.pro', 'paddle', 'sub_1', now()->addMonth(), $created );
        $access->remove( $this->stored, null, 'stripe', 'sub_1', $revoked );

        $stored = $this->storedAccess( $this->stored );
        $this->assertIsArray( $stored );
        $this->assertNull( $stored['tenant|a|stripe|sub_1']['role'] );
        $this->assertNull( $stored['tenant-b|stripe|sub_1']['role'] );
        $this->assertSame( 'frontend.pro', $stored['tenant-b|paddle|sub_1']['role'] );
    }


    public function testUnknownUnverifiedRemovalDoesNotCreateTombstone(): void
    {
        app( CashierAccess::class )->remove(
            $this->stored,
            null,
            'stripe',
            'missing',
        );

        $this->assertNull( $this->storedAccess( $this->stored ) );
    }


    public function testRolesAreTenantScoped(): void
    {
        $access = app( CashierAccess::class );
        $access->grant(
            $this->stored,
            'tenant|a',
            'frontend.pro',
            'stripe',
            'sub_1',
            now()->addMonth(),
        );

        $stored = $this->storedAccess( $this->stored );
        $this->assertIsArray( $stored );
        $this->assertArrayHasKey( 'tenant|a|stripe|sub_1', $stored );
        $this->assertSame(
            ['frontend.pro'],
            Tenancy::run( 'tenant|a', fn() => $access->roles( $this->stored ) ),
        );
        $this->assertSame(
            [],
            Tenancy::run( 'tenant-b', fn() => $access->roles( $this->stored ) ),
        );
    }


    public function testSubscriptionNamesAreCanonical(): void
    {
        $prefix = CashierAccess::subscription( 'test' );
        $name = CashierAccess::subscription( 'test', ' frontend.pro ' );

        $this->assertSame(
            CashierAccess::SUBSCRIPTION_PREFIX . hash( 'sha256', 'test' ) . ':',
            $prefix,
        );
        $this->assertSame( $prefix . 'frontend.pro', $name );
        $this->assertSame( 'frontend.pro', CashierAccess::subscriptionAccess( $name, 'test' ) );
        $this->assertNull( CashierAccess::subscriptionAccess( $name, 'other' ) );
        $this->assertNull( CashierAccess::subscriptionAccess( 'application:frontend.pro', 'test' ) );
        $this->assertNull( CashierAccess::subscriptionAccess( $prefix, 'test' ) );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function storedAccess( \App\Models\User $user ): ?array
    {
        $access = $user->getAttribute( 'access' );
        return is_array( $access ) ? $access : null;
    }


    private function tenant(): string
    {
        return 'test';
    }
}
