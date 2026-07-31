<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Checkout;


/**
 * Stripe checkout and verified lifecycle handling.
 *
 * @phpstan-import-type ProductData from CashierProduct
 * @phpstan-import-type TokenData from CashierToken
 */
class CashierStripe extends CashierProvider
{
    public const EVENTS = [
        'checkout.session.completed',
        'checkout.session.async_payment_succeeded',
        'customer.subscription.created',
        'customer.subscription.updated',
        'customer.subscription.deleted',
        'invoice.paid',
        'charge.refunded',
        'charge.dispute.created',
    ];

    protected string $provider = 'stripe';


    /**
     * Schedules cancellation of a CMS-created Stripe subscription.
     */
    public function cancel( Authenticatable $user, string $subscription ) : void
    {
        $this->cancelSubscription( $user, $subscription, 'stripe_id' );
    }


    /**
     * Projects supported Stripe lifecycle events onto user access.
     *
     * @param array<string, mixed> $payload
     */
    public function webhook( array $payload ) : void
    {
        $type = $payload['type'] ?? null;
        $data = data_get( $payload, 'data.object' );
        $data = is_array( $data ) ? $data : [];
        $at = $payload['created'] ?? null;

        if( in_array( $type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true )
            && ( $data['mode'] ?? null ) === 'payment'
            && ( $data['payment_status'] ?? null ) === 'paid'
        ) {
            $this->grant( $data, (string) ( $data['payment_intent'] ?? $data['id'] ?? '' ), 'once', null, $at );
        }
        elseif( $type === 'customer.subscription.created' && ( $data['status'] ?? null ) === 'trialing' )
        {
            $end = $data['current_period_end'] ?? data_get( $data, 'items.data.0.current_period_end' );
            $this->grant( $data, (string) ( $data['id'] ?? '' ), 'subscription', $end, $at );
        }
        elseif( $type === 'invoice.paid' )
        {
            $subscription = $this->paid( $data );

            if( in_array( data_get( $subscription, 'status' ), ['active', 'trialing'], true ) )
            {
                $end = data_get( $subscription, 'current_period_end' )
                    ?? data_get( $subscription, 'items.data.0.current_period_end' );

                $this->grant( $subscription, (string) data_get( $subscription, 'id', '' ), 'subscription', $end, $at );
            }
        }
        elseif( $type === 'customer.subscription.deleted' )
        {
            $this->remove( $data, (string) ( $data['id'] ?? '' ), $at );
        }
        elseif( $type === 'charge.refunded' && (int) ( $data['amount_refunded'] ?? 0 ) >= (int) ( $data['amount'] ?? 1 ) )
        {
            $this->remove( $data, $this->source( $data ), $at );
        }
        elseif( $type === 'charge.dispute.created' )
        {
            $charge = $this->charge( $data['charge'] ?? null );
            $this->remove( $charge, $this->source( $charge ), $at );
        }
    }


    /**
     * Resolves the charge behind a Stripe dispute.
     *
     * @return array<string, mixed>|object
     */
    protected function charge( mixed $charge ) : array|object
    {
        if( is_string( $charge ) && $charge !== '' ) {
            return Cashier::stripe()->charges->retrieve( $charge, ['expand' => ['invoice']] );
        }

        return is_array( $charge ) || is_object( $charge ) ? $charge : [];
    }


    /**
     * Resolves the local billable owner of a Stripe source.
     *
     * @param array<string, mixed>|object $data
     */
    protected function owner( array|object $data, string $id ) : ?Authenticatable
    {
        $customer = data_get( $data, 'customer' );
        $user = is_string( $customer ) ? Cashier::findBillable( $customer ) : null;

        return $user instanceof Authenticatable ? $user : null;
    }


    /**
     * Resolves the subscription paid by an invoice.
     *
     * @param array<string, mixed>|object $invoice
     * @return array<string, mixed>|object
     */
    protected function paid( array|object $invoice ) : array|object
    {
        $subscription = data_get( $invoice, 'subscription' )
            ?? data_get( $invoice, 'parent.subscription_details.subscription' );

        if( is_string( $subscription ) && $subscription !== '' ) {
            return Cashier::stripe()->subscriptions->retrieve( $subscription, [] );
        }

        return is_array( $subscription ) || is_object( $subscription ) ? $subscription : [];
    }


    /**
     * Retrieves the provider source that owns otherwise missing CMS metadata.
     *
     * @param array<string, mixed>|object $data
     * @return array<string, mixed>|object
     */
    protected function proof( array|object $data, string $id ) : array|object
    {
        if( str_starts_with( $id, 'sub_' ) ) {
            return Cashier::stripe()->subscriptions->retrieve( $id, [] );
        }

        if( str_starts_with( $id, 'pi_' ) ) {
            return Cashier::stripe()->paymentIntents->retrieve( $id, [] );
        }

        return $data;
    }


    /**
     * Starts Stripe Checkout for a one-time price or subscription.
     *
     * @param ProductData $product
     * @param array<string, string> $metadata
     */
    protected function start( Authenticatable $user, array $product, array $metadata ) : Checkout
    {
        if( trim( (string) config( 'cashier.webhook.secret' ) ) === '' ) {
            abort( 503 );
        }

        $urls = ['success_url' => $product['url'], 'cancel_url' => $this->previous()];

        if( $product['kind'] === 'once' )
        {
            /** @phpstan-ignore method.notFound */
            return $user->checkout( [$product['reference'] => 1], $urls + [
                'metadata' => $metadata,
                'payment_intent_data' => ['metadata' => $metadata],
            ] );
        }

        $type = CashierAccess::subscription( Tenancy::value(), $product['access'] );

        /** @phpstan-ignore method.notFound */
        return $user->newSubscription( $type, $product['reference'] )
            ->checkout( $urls + [
                'metadata' => $metadata,
                'subscription_data' => ['metadata' => $metadata],
            ] );
    }


    /**
     * Verifies that a subscription still contains the signed Stripe price.
     *
     * @param array<string, mixed>|object $data
     * @param TokenData $meta
     */
    protected function verify( array|object $data, array $meta, Authenticatable $user ) : bool
    {
        if( $meta['kind'] !== 'subscription' ) {
            return true;
        }

        foreach( (array) data_get( $data, 'items.data', [] ) as $item )
        {
            $price = data_get( $item, 'price.id' ) ?? data_get( $item, 'price' );

            if( is_string( $price ) && hash_equals( $meta['reference'], $price ) ) {
                return true;
            }
        }

        return false;
    }


    /**
     * Verifies that a Stripe revocation belongs to the signed payment source.
     *
     * @param array<string, mixed>|object $data
     * @param TokenData $meta
     */
    protected function verifyRemove( array|object $data, array $meta, Authenticatable $user, string $id ) : bool
    {
        $source = $this->source( $data );
        return $source !== '' && hash_equals( $id, $source );
    }


    /**
     * Returns the access source represented by a Stripe object.
     *
     * @param array<string, mixed>|object $data
     */
    private function source( array|object $data ) : string
    {
        $meta = $this->meta( $data );
        $invoice = data_get( $data, 'invoice' );

        if( is_string( $invoice ) && $invoice !== '' ) {
            $invoice = Cashier::stripe()->invoices->retrieve( $invoice, [] );
        }

        if( ( $meta['kind'] ?? null ) === 'subscription' || $invoice !== null )
        {
            return (string) (
                data_get( $data, 'subscription' )
                ?? data_get( $invoice, 'subscription' )
                ?? data_get( $invoice, 'parent.subscription_details.subscription' )
                ?? ( str_starts_with( (string) data_get( $data, 'id' ), 'sub_' )
                    ? data_get( $data, 'id' ) : null )
                ?? ''
            );
        }

        return (string) (
            data_get( $data, 'payment_intent' )
            ?? ( str_starts_with( (string) data_get( $data, 'id' ), 'pi_' )
                ? data_get( $data, 'id' ) : '' )
        );
    }
}
