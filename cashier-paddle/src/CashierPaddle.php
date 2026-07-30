<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Laravel\Paddle\Cashier;


/**
 * Paddle checkout and verified lifecycle handling.
 *
 * @phpstan-import-type ProductData from CashierProduct
 * @phpstan-import-type TokenData from CashierToken
 * @phpstan-type PaddleData TokenData&array{source?: string}
 */
class CashierPaddle extends CashierProvider
{
    protected string $provider = 'paddle';


    /**
     * Schedules cancellation of a CMS-created Paddle subscription.
     */
    public function cancel( Authenticatable $user, string $subscription ) : void
    {
        $this->cancelSubscription( $user, $subscription, 'paddle_id' );
    }


    /**
     * Projects supported Paddle lifecycle events onto user access.
     *
     * @param array<string, mixed> $payload
     */
    public function webhook( array $payload ) : void
    {
        $type = $payload['event_type'] ?? null;
        $data = is_array( $payload['data'] ?? null ) ? $payload['data'] : [];
        $at = $payload['occurred_at'] ?? null;

        if( $type === 'transaction.completed' )
        {
            $meta = $this->meta( $data );
            $subscription = (string) ( $data['subscription_id'] ?? '' );

            if( $subscription !== '' )
            {
                $this->grant(
                    $data,
                    $subscription,
                    'subscription',
                    data_get( $data, 'billing_period.ends_at' ),
                    $at,
                );
            }
            elseif( ( $meta['kind'] ?? null ) === 'once' )
            {
                $this->grant( $data, (string) ( $data['id'] ?? '' ), 'once', null, $at );
            }
        }
        elseif( $type === 'subscription.created' && ( $data['status'] ?? null ) === 'trialing' )
        {
            $this->grant(
                $data,
                (string) ( $data['id'] ?? '' ),
                'subscription',
                data_get( $data, 'current_billing_period.ends_at' ) ?? data_get( $data, 'next_billed_at' ),
                $at,
            );
        }
        elseif( $type === 'subscription.canceled' )
        {
            $this->remove(
                $data,
                (string) ( $data['id'] ?? '' ),
                $at,
            );
        }
        elseif( in_array( $type, ['adjustment.created', 'adjustment.updated'], true )
            && in_array( $data['status'] ?? null, ['approved', 'completed'], true )
            && in_array( $data['action'] ?? null, ['refund', 'chargeback'], true )
            && ( $data['action'] === 'chargeback' || ( $data['type'] ?? 'full' ) === 'full' )
        ) {
            $id = (string) ( ( $data['subscription_id'] ?? null ) ?: ( $data['transaction_id'] ?? '' ) );
            $this->remove( $data, $id, $at );
        }
    }


    /**
     * Reads CMS metadata bound to its originating Paddle transaction.
     *
     * @param array<string, mixed>|object $data
     * @return PaddleData|null
     */
    protected function meta( array|object $data ) : ?array
    {
        $token = data_get( $data, 'custom_data.cms' );
        $bound = is_string( $token ) ? $this->tokens->parse( $token ) : null;
        $source = is_array( $bound ) ? ( $bound['source'] ?? null ) : null;
        $cms = is_array( $bound ) ? ( $bound['cms'] ?? null ) : null;

        if( !is_string( $source ) || $source === ''
            || mb_strlen( $source ) > 255 || !is_string( $cms )
            || !( $meta = $this->tokens->read( $cms ) )
        ) {
            return null;
        }

        return $meta + ['source' => $source];
    }


    /**
     * Resolves the local billable owner of a Paddle source.
     *
     * @param array<string, mixed>|object $data
     */
    protected function owner( array|object $data, string $id ) : ?Authenticatable
    {
        $model = str_starts_with( $id, 'sub_' )
            ? Cashier::$subscriptionModel
            : Cashier::$transactionModel;

        $record = $model::query()->where( 'paddle_id', $id )->first();
        $user = $record?->getRelationValue( 'billable' );

        return $user instanceof Authenticatable ? $user : null;
    }


    /**
     * Retrieves provider-controlled metadata and its originating transaction.
     *
     * @param array<string, mixed>|object $data
     * @return array<string, mixed>|object
     */
    protected function proof( array|object $data, string $id ) : array|object
    {
        $result = (array) $data;
        $transaction = (string) ( $result['transaction_id'] ?? '' );
        $origin = $transaction !== '' ? $this->transaction( $transaction ) : [];

        if( !$origin && str_starts_with( $id, 'txn_' ) ) {
            $origin = $this->transaction( $id );
        }

        if( !$origin && str_starts_with( $id, 'sub_' ) )
        {
            $subscription = (array) $this->subscription( $id );
            $custom = $subscription['custom_data'] ?? null;

            if( $custom !== null ) {
                $result['custom_data'] = $custom;
            }
        }

        $origin = (array) $origin;
        $custom = $origin['custom_data'] ?? null;

        if( $custom !== null ) {
            $result['custom_data'] = $custom;
        }

        $meta = $this->meta( $result );
        $source = $meta['source'] ?? null;

        if( is_string( $source ) && $source !== ''
            && !hash_equals( $source, (string) ( $origin['id'] ?? '' ) )
        ) {
            $origin = (array) $this->transaction( $source );
        }

        if( $origin ) {
            $result['_cms_origin'] = $origin;
        }

        return $result;
    }


    /**
     * Starts a source-bound Paddle transaction and renders inline checkout.
     *
     * @param ProductData $product
     * @param array<string, string> $metadata
     */
    protected function start( Authenticatable $user, array $product, array $metadata ) : View
    {
        if( trim( (string) config( 'cashier.webhook_secret' ) ) === '' ) {
            abort( 503 );
        }

        if( $product['kind'] === 'subscription' )
        {
            $metadata['subscription_type'] = CashierAccess::subscription(
                Tenancy::value(),
                $product['access'],
            );
        }

        /** @phpstan-ignore method.notFound */
        $customer = $user->createAsCustomer();
        $id = (string) data_get( $customer, 'paddle_id' );

        if( $id === '' ) {
            throw new \RuntimeException( 'Paddle returned no customer.' );
        }

        $transaction = Cashier::api( 'POST', 'transactions', [
            'items' => [[
                'price_id' => $product['reference'],
                'quantity' => 1,
            ]],
            'customer_id' => $id,
        ] )->json( 'data' );

        $transaction = is_array( $transaction ) ? (string) ( $transaction['id'] ?? '' ) : '';

        if( $transaction === '' ) {
            throw new \RuntimeException( 'Paddle returned no transaction.' );
        }

        $metadata['cms'] = $this->tokens->make( [
            'cms' => $metadata['cms'],
            'source' => $transaction,
        ] );

        Cashier::api( 'PATCH', 'transactions/' . $transaction, ['custom_data' => $metadata] );

        return app( \Illuminate\Contracts\View\Factory::class )->make( 'cms-cashier::paddle', [
            'cancelUrl' => $this->previous(),
            'options' => [
                'transactionId' => $transaction,
                'settings' => [
                    'displayMode' => 'inline',
                    'frameTarget' => 'paddle-checkout-container',
                    'frameInitialHeight' => 450,
                    'frameStyle' => 'width: 100%; background-color: transparent; border: none;',
                    'successUrl' => $product['url'],
                    'allowLogout' => false,
                ],
            ],
            'transaction' => $transaction,
        ] );
    }


    /**
     * Resolves a Paddle subscription for source-bound cancellation metadata.
     *
     * @return array<string, mixed>|object
     */
    protected function subscription( string $id ) : array|object
    {
        $data = Cashier::api( 'GET', 'subscriptions/' . $id )->json( 'data' );
        return is_array( $data ) || is_object( $data ) ? $data : [];
    }


    /**
     * Resolves the transaction that authorized a Paddle checkout.
     *
     * @return array<string, mixed>|object
     */
    protected function transaction( string $id ) : array|object
    {
        $data = Cashier::api( 'GET', 'transactions/' . $id )->json( 'data' );
        return is_array( $data ) || is_object( $data ) ? $data : [];
    }


    /**
     * Verifies Paddle customer, source, and purchased price details.
     *
     * @param array<string, mixed>|object $data
     * @param PaddleData $meta
     */
    protected function verify( array|object $data, array $meta, Authenticatable $user ) : bool
    {
        $source = $meta['source'] ?? null;

        if( !is_string( $source ) || $source === '' ) {
            return false;
        }

        if( !method_exists( $user, 'customer' ) ) {
            return false;
        }

        $customer = $user->getRelationValue( 'customer' );

        if( !is_object( $customer )
            || (string) data_get( $customer, 'paddle_id' ) !== (string) data_get( $data, 'customer_id' )
        ) {
            return false;
        }

        $id = (string) data_get( $data, 'id', '' );

        if( !hash_equals( $source, $id ) )
        {
            if( $meta['kind'] !== 'subscription' ) {
                return false;
            }

            $subscription = (string) ( data_get( $data, 'subscription_id' ) ?: $id );
            $origin = $this->transaction( $source );

            if( $subscription === '' || (string) data_get( $origin, 'subscription_id', '' ) !== $subscription ) {
                return false;
            }
        }

        foreach( (array) data_get( $data, 'items', [] ) as $item )
        {
            if( (string) data_get( $item, 'price.id' ) === $meta['reference'] && (int) data_get( $item, 'quantity', 1 ) > 0 ) {
                return true;
            }
        }

        return false;
    }


    /**
     * Verifies that a Paddle revocation belongs to the signed payment source.
     *
     * @param array<string, mixed>|object $data
     * @param PaddleData $meta
     */
    protected function verifyRemove( array|object $data, array $meta,
        Authenticatable $user, string $id
    ) : bool {
        $data = (array) $data;
        $origin = (array) ( $data['_cms_origin'] ?? [] );
        $source = (string) ( $meta['source'] ?? '' );
        $transaction = (string) (
            ( $data['transaction_id'] ?? null )
            ?? ( $origin['id'] ?? null )
            ?? ( str_starts_with( $id, 'txn_' ) ? $id : '' )
        );

        if( $source === '' || $transaction === '' || !hash_equals( $source, $transaction ) ) {
            return false;
        }

        if( $meta['kind'] === 'once' ) {
            return hash_equals( $source, $id );
        }

        $subscription = (string) (
            ( $data['subscription_id'] ?? null )
            ?? ( $origin['subscription_id'] ?? null )
            ?? ( str_starts_with( (string) ( $data['id'] ?? '' ), 'sub_' )
                ? $data['id'] : '' )
        );

        return $subscription !== '' && hash_equals( $subscription, $id );
    }


}
