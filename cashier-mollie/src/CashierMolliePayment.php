<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms;

use Illuminate\Http\Exceptions\HttpResponseException;
use Laravel\Cashier\Mollie\Contracts\GetMolliePayment;
use Laravel\Cashier\Mollie\GetMolliePayment as MolliePayment;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Payment;


/**
 * Makes remote Mollie payment state part of webhook processing.
 */
class CashierMolliePayment implements GetMolliePayment
{
    /**
     * Decorates Cashier Mollie's payment lookup with synchronous access projection.
     */
    public function __construct( private MolliePayment $payments, private CashierMollie $provider )
    {
    }


    /**
     * Retrieves authoritative payment state and projects webhook effects before acknowledgement.
     *
     * @param array<string, mixed> $parameters
     */
    public function execute( string $id, array $parameters = [] ) : Payment
    {
        $route = request()->route()?->getName();
        $route = is_string( $route ) ? $route : '';
        $webhook = str_starts_with( $route, 'webhooks.mollie.' );

        if( $webhook ) {
            $id = self::id( $id );
            $parameters['embed'] = 'refunds,chargebacks';
        }

        try
        {
            $payment = $this->payments->execute( $id, $parameters );
        }
        catch( ApiException $e )
        {
            if( $webhook && $e->getStatusCode() === 404 ) {
                throw new HttpResponseException( response()->noContent() );
            }

            throw new \RuntimeException( 'Unable to retrieve the Mollie payment.', 0, $e );
        }

        if( $webhook )
        {
            try {
                $this->provider->webhook(
                    $payment,
                    firstPayment: $route === 'webhooks.mollie.first_payment',
                );
            } catch( ApiException $e ) {
                throw new \RuntimeException( 'Unable to retrieve the Mollie payment state.', 0, $e );
            }
        }

        return $payment;
    }


    /**
     * Validates the untrusted payment identifier before an API lookup.
     */
    public static function id( mixed $id ) : string
    {
        if( !is_string( $id ) || preg_match( '/\Atr_[A-Za-z0-9]{1,100}\z/D', $id ) !== 1 ) {
            abort( 400 );
        }

        return $id;
    }
}
