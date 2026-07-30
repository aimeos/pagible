<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Tests;

use Aimeos\Cms\Access;
use Aimeos\Cms\CashierAccess;
use Aimeos\Cms\CashierProduct;
use Aimeos\Cms\CashierProvider;
use Aimeos\Cms\CashierToken;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Tenancy;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;


class CashierControllerTest extends CashierTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    private Page $page;


    protected function setUp(): void
    {
        parent::setUp();

        Access::using( fn() => ['frontend.pro'] );
        app()->instance( CashierProvider::class, new CashierControllerDriver(
            app( CashierAccess::class ),
            app( CashierToken::class ),
        ) );
        $this->user = \App\Models\User::forceCreate( [
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'password',
        ] );
        $this->page = Page::forceCreate( [
            'lang' => 'en',
            'name' => 'Pricing',
            'title' => 'Pricing',
            'path' => 'pricing',
            'status' => 1,
            'editor' => 'test@example.com',
            'content' => [[
                'id' => 'pricing',
                'type' => 'pricing',
                'group' => 'main',
                'data' => [
                    'items' => [[
                        'id' => 'professional',
                        'access' => 'frontend.pro',
                        'name' => 'Professional',
                        'url' => '/account',
                        'prices' => [[
                            'id' => 'once',
                            'reference' => 'price_test123',
                            'kind' => 'once',
                            'currency' => 'EUR',
                            'amount' => 99.0,
                            'unit' => 'once',
                        ]],
                    ]],
                ],
            ]],
        ] );
    }


    public function testCancellationUsesInstalledDriver(): void
    {
        $provider = new class(
            app( CashierAccess::class ),
            app( CashierToken::class ),
        ) extends CashierProvider
        {
            public string $subscription = '';
            public ?Authenticatable $user = null;

            protected string $provider = 'stripe';


            public function cancel( Authenticatable $user, string $subscription ): void
            {
                $this->subscription = $subscription;
                $this->user = $user;
            }


            protected function start( Authenticatable $user, array $product, array $metadata ): mixed
            {
                return null;
            }
        };
        app()->instance( CashierProvider::class, $provider );

        $this->actingAs( $this->storedUser() )
            ->delete( route( 'cms.cashier.cancel', ['subscription' => 'sub_1'] ) )
            ->assertNoContent();

        $this->assertSame( 'sub_1', $provider->subscription );
        $this->assertSame( $this->storedUser()->id, $provider->user?->getAuthIdentifier() );
    }


    public function testCheckoutAcceptsBillingIntervalInDays(): void
    {
        $content = json_decode( json_encode( $this->page->content, JSON_THROW_ON_ERROR ), true, flags: JSON_THROW_ON_ERROR );
        $content[0]['data']['items'][0]['prices'][0]['interval'] = 30;
        $this->page->forceFill( ['content' => $content] )->saveQuietly();

        $product = app( CashierProduct::class )->find(
            $this->storedUser(),
            (string) $this->page->id,
            'pricing',
            'professional',
            'once',
        );

        $this->assertSame( 30, $product['interval'] );
    }


    public function testCheckoutAcceptsSeveralPrices(): void
    {
        $content = json_decode( json_encode( $this->page->content, JSON_THROW_ON_ERROR ), true, flags: JSON_THROW_ON_ERROR );
        $content[0]['data']['items'][0]['prices'][] = [
            'id' => 'monthly',
            'reference' => 'price_monthly',
            'kind' => 'subscription',
            'currency' => 'EUR',
        ];
        $content[0]['data']['items'][0]['prices'][] = [
            'id' => 'yearly',
            'reference' => 'price_yearly',
            'kind' => 'subscription',
            'currency' => 'EUR',
        ];
        $this->page->forceFill( ['content' => $content] )->saveQuietly();

        $this->actingAs( $this->storedUser() )->post(
            route( 'cms.cashier' ),
            $this->checkout( ['price' => 'yearly'] ),
        )->assertRedirect();
    }


    public function testCheckoutCancelExternal(): void
    {
        $response = $this->actingAs( $this->storedUser() )->post(
            route( 'cms.cashier' ),
            $this->checkout(),
            ['referer' => 'https://evil.com/phishing?id=123'],
        );

        $response->assertRedirect( url( '/phishing?id=123' ) );
    }


    public function testCheckoutCancelPrevious(): void
    {
        $response = $this->actingAs( $this->storedUser() )->post(
            route( 'cms.cashier' ),
            $this->checkout(),
            ['referer' => url( '/pricing?plan=pro#compare' )],
        );

        $response->assertRedirect( url( '/pricing?plan=pro#compare' ) );
    }


    public function testCheckoutCancelSlashes(): void
    {
        $response = $this->actingAs( $this->storedUser() )->post(
            route( 'cms.cashier' ),
            $this->checkout(),
            ['referer' => url( '/' ) . '//evil.com'],
        );

        $response->assertRedirect( url( '/evil.com' ) );
    }


    public function testCheckoutIgnoresClientPaymentData(): void
    {
        $response = $this->actingAs( $this->storedUser() )->post(
            route( 'cms.cashier' ),
            $this->checkout() + [
                'provider' => 'paddle',
                'reference' => 'attacker-product',
                'access' => 'frontend.admin',
                'kind' => 'subscription',
            ],
        );

        $response->assertRedirect();
    }


    public function testCheckoutInlinePricingUsesOneQuery(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        app( CashierProduct::class )->find(
            $this->storedUser(),
            (string) $this->page->id,
            'pricing',
            'professional',
            'once',
        );

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount( 1, $queries );
    }


    public function testCheckoutMissingField(): void
    {
        $response = $this->actingAs( $this->storedUser() )->postJson( route( 'cms.cashier' ) );

        $response->assertStatus( 422 );
        $response->assertJsonValidationErrors( ['page', 'element', 'package', 'price'] );
    }


    public function testCheckoutMissingSession(): void
    {
        $this->actingAs( $this->storedUser() )->get( route( 'cms.cashier' ) )->assertNotFound();
    }


    public function testCheckoutPriceUnknown(): void
    {
        $response = $this->actingAs( $this->storedUser() )->postJson(
            route( 'cms.cashier' ),
            $this->checkout( ['price' => 'unknown'] ),
        );

        $response->assertNotFound();
    }


    public function testCheckoutPriceTooLong(): void
    {
        $response = $this->actingAs( $this->storedUser() )->postJson(
            route( 'cms.cashier' ),
            $this->checkout( ['price' => str_repeat( 'a', 101 )] ),
        );

        $response->assertStatus( 422 );
        $response->assertJsonValidationErrors( 'price' );
    }


    public function testCheckoutRejectsDuplicatePackage(): void
    {
        $content = json_decode( json_encode( $this->page->content, JSON_THROW_ON_ERROR ), true, flags: JSON_THROW_ON_ERROR );
        $content[0]['data']['items'][] = $content[0]['data']['items'][0];
        $this->page->forceFill( ['content' => $content] )->saveQuietly();

        $this->actingAs( $this->storedUser() )->postJson(
            route( 'cms.cashier' ),
            $this->checkout(),
        )->assertNotFound();
    }


    public function testCheckoutRejectsDuplicatePrice(): void
    {
        $content = json_decode( json_encode( $this->page->content, JSON_THROW_ON_ERROR ), true, flags: JSON_THROW_ON_ERROR );
        $content[0]['data']['items'][0]['prices'][] = $content[0]['data']['items'][0]['prices'][0];
        $this->page->forceFill( ['content' => $content] )->saveQuietly();

        $this->actingAs( $this->storedUser() )->postJson(
            route( 'cms.cashier' ),
            $this->checkout(),
        )->assertNotFound();
    }


    public function testCheckoutRejectsExcessivePaymentHistory(): void
    {
        $access = [];

        for( $i = 0; $i < CashierAccess::LIMIT; $i++ ) {
            $access['test|stripe|source_' . $i] = [
                'role' => null,
                'end' => null,
                'at' => $i,
            ];
        }

        $this->storedUser()->forceFill( ['access' => $access] )->saveQuietly();

        $this->actingAs( $this->storedUser() )->postJson(
            route( 'cms.cashier' ),
            $this->checkout(),
        )->assertConflict();
    }


    public function testCheckoutRejectsInvalidBillingIntervals(): void
    {
        $content = json_decode( json_encode( $this->page->content, JSON_THROW_ON_ERROR ), true, flags: JSON_THROW_ON_ERROR );

        foreach( [-1, 366, '30'] as $interval )
        {
            $content[0]['data']['items'][0]['prices'][0]['interval'] = $interval;
            $this->page->forceFill( ['content' => $content] )->saveQuietly();

            $this->actingAs( $this->storedUser() )->postJson(
                route( 'cms.cashier' ),
                $this->checkout(),
            )->assertNotFound();
        }
    }


    public function testCheckoutRejectsMoreThanFivePrices(): void
    {
        $content = json_decode( json_encode( $this->page->content, JSON_THROW_ON_ERROR ), true, flags: JSON_THROW_ON_ERROR );

        for( $i = 0; $i < 5; $i++ ) {
            $content[0]['data']['items'][0]['prices'][] = [
                'id' => 'price-' . $i,
                'reference' => 'price_' . $i,
                'kind' => 'subscription',
            ];
        }

        $this->page->forceFill( ['content' => $content] )->saveQuietly();

        $this->actingAs( $this->storedUser() )->postJson(
            route( 'cms.cashier' ),
            $this->checkout(),
        )->assertNotFound();
    }


    public function testCheckoutRejectsInvalidCurrency(): void
    {
        $content = json_decode( json_encode( $this->page->content, JSON_THROW_ON_ERROR ), true, flags: JSON_THROW_ON_ERROR );

        foreach( ['eur', 'EU', 'EURO', 'EU1'] as $currency )
        {
            $content[0]['data']['items'][0]['prices'][0]['currency'] = $currency;
            $this->page->forceFill( ['content' => $content] )->saveQuietly();

            $this->actingAs( $this->storedUser() )->postJson(
                route( 'cms.cashier' ),
                $this->checkout(),
            )->assertNotFound();
        }
    }


    public function testCheckoutFallsBackForExternalTarget(): void
    {
        $content = json_decode( json_encode( $this->page->content, JSON_THROW_ON_ERROR ), true, flags: JSON_THROW_ON_ERROR );
        $content[0]['data']['items'][0]['url'] = 'https://evil.example/collect';
        $this->page->forceFill( ['content' => $content] )->saveQuietly();

        $this->actingAs( $this->storedUser() )
            ->post( route( 'cms.cashier' ), $this->checkout() )
            ->assertRedirect();

        $provider = app( CashierProvider::class );
        $this->assertInstanceOf( CashierControllerDriver::class, $provider );
        $this->assertSame( url( '/' ), $provider->url );
    }


    public function testCheckoutKeepsValidLocalTarget(): void
    {
        $content = json_decode( json_encode( $this->page->content, JSON_THROW_ON_ERROR ), true, flags: JSON_THROW_ON_ERROR );
        $content[0]['data']['items'][0]['url'] = '/_account?tab=billing';
        $this->page->forceFill( ['content' => $content] )->saveQuietly();

        $this->actingAs( $this->storedUser() )
            ->post( route( 'cms.cashier' ), $this->checkout() )
            ->assertRedirect();

        $provider = app( CashierProvider::class );
        $this->assertInstanceOf( CashierControllerDriver::class, $provider );
        $this->assertSame( url( '/_account?tab=billing' ), $provider->url );
    }


    public function testCheckoutRejectsUnknownAccessRole(): void
    {
        $content = json_decode( json_encode( $this->page->content, JSON_THROW_ON_ERROR ), true, flags: JSON_THROW_ON_ERROR );
        $content[0]['data']['items'][0]['access'] = 'frontend.unknown';
        $this->page->forceFill( ['content' => $content] )->saveQuietly();

        $this->actingAs( $this->storedUser() )->postJson(
            route( 'cms.cashier' ),
            $this->checkout(),
        )->assertNotFound();
    }


    public function testCheckoutRejectsUserFromAnotherTenant(): void
    {
        $user = $this->storedUser();
        $user->setAttribute( 'tenant_id', 'other' );
        Tenancy::$access = fn() => true;

        try {
            $this->actingAs( $user )->postJson(
                route( 'cms.cashier' ),
                $this->checkout(),
            )->assertForbidden();
        } finally {
            Tenancy::$access = null;
        }
    }


    public function testCheckoutResumesFromSession(): void
    {
        $this->withSession( ['cms.cashier' => $this->checkout()] )
            ->actingAs( $this->storedUser() )
            ->get( route( 'cms.cashier' ) )
            ->assertRedirect();

        $this->assertFalse( session()->has( 'cms.cashier' ) );
        $this->assertNotEmpty( app( CashierProvider::class )->metadata );
    }


    public function testCheckoutSharedPricingElement(): void
    {
        $inline = collect( (array) $this->page->content )->first();
        $element = Element::forceCreate( [
            'type' => 'pricing',
            'name' => 'Shared pricing',
            'data' => $inline->data,
            'editor' => 'test@example.com',
        ] );
        $this->page->forceFill( ['content' => [[
            'id' => 'reference',
            'type' => 'reference',
            'refid' => $element->id,
            'group' => 'main',
        ]]] )->saveQuietly();
        $this->page->elements()->attach( $element->id );

        DB::flushQueryLog();
        DB::enableQueryLog();

        $product = app( CashierProduct::class )->find(
            $this->storedUser(),
            (string) $this->page->id,
            (string) $element->id,
            'professional',
            'once',
        );

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame( 'frontend.pro', $product['access'] );
        $this->assertSame( 'price_test123', $product['reference'] );
        $this->assertCount( 2, $queries );
    }


    public function testCheckoutSignsProviderMetadata(): void
    {
        $this->actingAs( $this->storedUser() )
            ->post( route( 'cms.cashier' ), $this->checkout() )
            ->assertRedirect();

        $provider = app( CashierProvider::class );
        $this->assertInstanceOf( CashierControllerDriver::class, $provider );
        $token = $provider->metadata['cms'] ?? null;
        $this->assertIsString( $token );
        $meta = app( CashierToken::class )->read( $token );

        $this->assertSame( 'stripe', $meta['provider'] ?? null );
        $this->assertSame( 'frontend.pro', $meta['access'] ?? null );
        $this->assertSame( 'price_test123', $meta['reference'] ?? null );
        $this->assertSame( url( '/account' ), $provider->url );
    }


    public function testCheckoutThrottle(): void
    {
        RateLimiter::clear( 'cms-cashier' );

        for( $i = 0; $i < 10; $i++ ) {
            $this->actingAs( $this->storedUser() )->postJson( route( 'cms.cashier' ), $this->checkout() );
        }

        $this->actingAs( $this->storedUser() )
            ->postJson( route( 'cms.cashier' ), $this->checkout() )
            ->assertTooManyRequests();
    }


    public function testCheckoutUnauthenticated(): void
    {
        $response = $this->post( route( 'cms.cashier' ), $this->checkout() );

        $response->assertRedirect( route( 'login' ) );
        $this->assertEquals( $this->page->id, session( 'cms.cashier.page' ) );
        $this->assertEquals( 'once', session( 'cms.cashier.price' ) );
    }


    public function testPricingAccessDoesNotFallBackToLink(): void
    {
        $element = collect( (array) $this->page->content )->first();
        unset( $element->data->items[0]->id );

        $html = view( 'cms::pricing', [
            'data' => $element->data,
            'files' => collect(),
            'id' => $element->id,
            'page' => $this->page,
        ] )->render();

        $this->assertStringNotContainsString( '<form method="POST"', $html );
        $this->assertStringNotContainsString( '<a class="btn"', $html );
    }


    public function testPricingFormExposesOnlyCmsIdentifiers(): void
    {
        $element = collect( (array) $this->page->content )->first();
        $element->data->items[0]->prices[] = (object) [
            'id' => 'subscription',
            'reference' => 'price_subscription',
            'kind' => 'subscription',
            'amount' => 79.95,
            'currency' => 'EUR',
            'unit' => 'month',
        ];
        $html = view( 'cms::pricing', [
            'data' => $element->data,
            'files' => collect(),
            'id' => $element->id,
            'page' => $this->page,
        ] )->render();

        $this->assertStringContainsString( 'name="page"', $html );
        $this->assertStringContainsString( 'name="element"', $html );
        $this->assertStringContainsString( 'name="package"', $html );
        $this->assertStringContainsString( 'name="price"', $html );
        $this->assertStringContainsString( 'data-priceid="subscription"', $html );
        $this->assertStringContainsString( 'data-unit="month"', $html );
        $this->assertStringContainsString( '<span class="amount">79.95</span>', $html );
        $this->assertStringContainsString( '<span class="currency">EUR</span>', $html );
        $this->assertStringContainsString( '<span class="unit">month</span>', $html );
        $this->assertStringNotContainsString( 'pricing-toggle', $html );
        $this->assertStringNotContainsString( 'data-price="', $html );
        $this->assertStringNotContainsString( 'price_test123', $html );
        $this->assertStringNotContainsString( 'price_subscription', $html );
        $this->assertStringNotContainsString( 'frontend.pro', $html );
        $this->assertStringNotContainsString( 'name="provider"', $html );
    }


    public function testPricingLinkWithoutAccess(): void
    {
        $element = collect( (array) $this->page->content )->first();
        unset( $element->data->items[0]->access );
        $element->data->items[0]->url = '#contact';

        $html = view( 'cms::pricing', [
            'data' => $element->data,
            'files' => collect(),
            'id' => $element->id,
            'page' => $this->page,
        ] )->render();

        $this->assertStringContainsString( '<a class="btn" href="#contact">', $html );
        $this->assertStringNotContainsString( '<form method="POST"', $html );
    }


    public function testPricingOmitsEmptyUnit(): void
    {
        $element = collect( (array) $this->page->content )->first();
        unset( $element->data->items[0]->prices[0]->unit );

        $html = view( 'cms::pricing', [
            'data' => $element->data,
            'files' => collect(),
            'id' => $element->id,
            'page' => $this->page,
        ] )->render();

        $this->assertStringContainsString( 'data-unit=""', $html );
        $this->assertStringNotContainsString( '<span class="unit">', $html );
    }


    /**
     * @param array<string, string> $replace
     * @return array<string, string>
     */
    private function checkout( array $replace = [] ): array
    {
        return $replace + [
            'page' => (string) $this->page->id,
            'element' => 'pricing',
            'package' => 'professional',
            'price' => 'once',
        ];
    }


    private function storedUser(): \App\Models\User
    {
        if( !$this->user ) {
            throw new \LogicException( 'Test user not initialized.' );
        }

        return $this->user;
    }
}


class CashierControllerDriver extends CashierProvider
{
    /** @var array<string, string> */
    public array $metadata = [];
    public string $url = '';

    protected string $provider = 'stripe';


    public function cancel( Authenticatable $user, string $subscription ) : void
    {
    }


    protected function start( Authenticatable $user, array $product, array $metadata ) : \Illuminate\Http\RedirectResponse
    {
        $this->metadata = $metadata;
        $this->url = $product['url'];
        return redirect( $this->previous() );
    }
}
