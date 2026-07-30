<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms;

use Laravel\Cashier\Exceptions\PlanNotFoundException;
use Laravel\Cashier\Order\OrderItemPreprocessorCollection;
use Laravel\Cashier\Plan\Contracts\Plan as PlanContract;
use Laravel\Cashier\Plan\Contracts\PlanRepository;
use Laravel\Cashier\Plan\Plan;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Money;
use Money\Parser\DecimalMoneyParser;


/**
 * Recreates immutable Mollie plans from signed pricing-content snapshots.
 */
class CashierMolliePlan implements PlanRepository
{
    /**
     * Creates the repository with the shared signed-token codec.
     */
    public function __construct( private CashierToken $tokens )
    {
    }


    /**
     * Creates a durable signed plan name from a trusted product snapshot.
     *
     * @param array<string, mixed> $product
     */
    public function create( array $product, string $type ) : string
    {
        $price = $this->valid( $product, true );

        return $this->name( [
            'v' => $price['reference'],
            'c' => $price['currency'],
            'i' => $price['interval'],
            'd' => $price['description'],
            'b' => $this->binding( $type ),
        ] );
    }


    /**
     * Resolves a signed dynamic plan or returns null when it is invalid.
     */
    public static function find( string $name ) : ?PlanContract
    {
        return app( self::class )->plan( $name );
    }


    /**
     * Resolves a signed dynamic plan or throws the Cashier plan exception.
     */
    public static function findOrFail( string $name ) : PlanContract
    {
        return self::find( $name ) ?? throw new PlanNotFoundException();
    }


    /**
     * Tests whether a signed plan is bound to the canonical subscription name.
     */
    public function matches( string $name, string $type ) : bool
    {
        return ( $data = $this->read( $name ) )
            && hash_equals( $data['b'], $this->binding( $type ) );
    }


    /**
     * Converts a trusted Mollie price snapshot to Money.
     *
     * @param array<string, mixed> $product
     */
    public function money( array $product ) : Money
    {
        $price = $this->valid( $product, false );

        return ( new DecimalMoneyParser( new ISOCurrencies() ) )->parse(
            $price['reference'],
            new Currency( $price['currency'] ),
        );
    }


    /**
     * Returns a compact 128-bit binding for the canonical subscription name.
     */
    private function binding( string $type ) : string
    {
        return $this->compact( substr( hash( 'sha256', $type ), 0, 32 ) );
    }


    /**
     * Encodes a hexadecimal 128-bit value as compact URL-safe Base64.
     */
    private function compact( string $value ) : string
    {
        $binary = hex2bin( $value );

        if( $binary === false ) {
            throw new \InvalidArgumentException( 'Invalid compact value.' );
        }

        return rtrim( strtr( base64_encode( $binary ), '+/', '-_' ), '=' );
    }


    /**
     * Formats a day interval for Cashier Mollie.
     */
    private function days( int $interval ) : string
    {
        return $interval . ( $interval === 1 ? ' day' : ' days' );
    }


    /**
     * Keeps the signed plan name within Mollie's 255-byte limit.
     *
     * @param array{v: string, c: string, i: int, d: string, b: string} $data
     */
    private function name( array $data ) : string
    {
        do
        {
            $name = 'cms.' . $this->tokens->make( $data );

            if( strlen( $name ) <= 255 ) {
                return $name;
            }

            $data['d'] = mb_substr( $data['d'], 0, -1 );
        }
        while( $data['d'] !== '' );

        throw new \InvalidArgumentException( 'Mollie price is too long.' );
    }


    /**
     * Recreates a Cashier plan from a valid signed plan name.
     */
    private function plan( string $name ) : ?PlanContract
    {
        if( !( $data = $this->read( $name ) ) ) {
            return null;
        }

        $product = [
            'reference' => $data['v'],
            'currency' => $data['c'],
            'interval' => $data['i'],
            'description' => $data['d'],
        ];

        $price = $this->valid( $product, true );

        $money = $this->money( $product );

        $plan = new Plan( $name );
        $plan->setAmount( $money );
        $plan->setInterval( $this->days( $price['interval'] ) );
        $plan->setDescription( $price['description'] );
        $plan->setFirstPaymentAmount( $money );
        $plan->setFirstPaymentDescription( $price['description'] );
        $plan->setFirstPaymentMethod( (array) config( 'cashier.first_payment.method', [] ) );
        $plan->setFirstPaymentRedirectUrl(
            (string) config( 'cashier.first_payment.redirect_url', config( 'app.url' ) )
        );
        $plan->setFirstPaymentWebhookUrl(
            (string) config( 'cashier.first_payment.webhook_url', 'webhooks/mollie/first-payment' )
        );
        $plan->setOrderItemPreprocessors( OrderItemPreprocessorCollection::fromArray(
            (array) config( 'cashier_plans.defaults.order_item_preprocessors', [] )
        ) );

        return $plan;
    }


    /**
     * @return array{v: string, c: string, i: int, d: string, b: string}|null
     */
    private function read( string $name ) : ?array
    {
        if( !str_starts_with( $name, 'cms.' ) ) {
            return null;
        }

        $data = $this->tokens->parse( substr( $name, 4 ) );

        if( !is_array( $data ) ) {
            return null;
        }

        foreach( ['v', 'c', 'd', 'b'] as $key )
        {
            if( !is_string( $data[$key] ?? null ) ) {
                return null;
            }
        }

        if( !is_int( $data['i'] ?? null ) || !preg_match( '/^[A-Za-z0-9_-]{22}$/D', $data['b'] ) ) {
            return null;
        }

        return $data;
    }


    /**
     * @param array<string, mixed> $product
     * @return array{
     *     currency: non-empty-string,
     *     description: non-empty-string,
     *     interval: int,
     *     reference: non-empty-string
     * }
     */
    private function valid( array $product, bool $subscription ) : array
    {
        $currency = $product['currency'] ?? null;
        $description = $product['description'] ?? null;
        $interval = $product['interval'] ?? 0;
        $reference = $product['reference'] ?? null;

        if( !is_string( $reference ) || !preg_match( '/^[0-9]+(?:\.[0-9]{2})$/', $reference )
            || !is_string( $currency ) || !preg_match( '/^[A-Z]{3}$/', $currency )
            || !is_string( $description ) || ( $description = trim( $description ) ) === ''
            || mb_strlen( $description ) > 80
            || !is_int( $interval ) || $interval < 0 || $interval > 365
            || ( $subscription && $interval === 0 )
        ) {
            throw new \InvalidArgumentException( 'Invalid Mollie price.' );
        }

        return [
            'currency' => $currency,
            'description' => $description,
            'interval' => $interval,
            'reference' => $reference,
        ];
    }
}
