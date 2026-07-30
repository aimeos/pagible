<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Charge\ChargeItemBuilder;
use Laravel\Cashier\Charge\FirstPaymentChargeBuilder;
use Laravel\Cashier\SubscriptionBuilder\FirstPaymentSubscriptionBuilder;
use Mollie\Api\Types\RefundStatus;
use Money\Money;


/**
 * Adapts pricing-content products to Cashier Mollie's local billing engine.
 *
 * @phpstan-import-type ProductData from CashierProduct
 */
class CashierMollie extends CashierProvider
{
    protected string $provider = 'mollie';


    /**
     * Creates the Mollie driver with its signed dynamic-plan repository.
     */
    public function __construct( CashierAccess $access, CashierToken $tokens,
        private CashierMolliePlan $plans
    ) {
        parent::__construct( $access, $tokens );
    }


    /**
     * Schedules cancellation of a CMS-created Mollie subscription.
     */
    public function cancel( Authenticatable $user, string $subscription ) : void
    {
        $this->cancelSubscription( $user, $subscription, 'id', 'name' );
    }


    /**
     * Renews or revokes sources referenced by a paid Mollie order.
     *
     * @param iterable<mixed>|null $items Refunded order items or null for the complete order
     */
    public function order( object $order, bool $revoked = false,
        ?\DateTimeInterface $at = null, ?iterable $items = null
    ) : void
    {
        if( $revoked )
        {
            $this->revokeOrder( $order, $items, $at ?? $this->occurred( $order ) );
            return;
        }

        foreach( $items ?? ( $order->items ?? [] ) as $item )
        {
            if( is_object( $item->orderable ?? null ) ) {
                $this->subscription( $item->orderable, at: $at );
            }
        }
    }


    /**
     * Grants a verified one-time Mollie payment.
     */
    public function payment( object $payment, bool $revoked = false,
        ?\DateTimeInterface $at = null
    ) : void
    {
        if( $revoked )
        {
            if( !$at ) {
                throw new \InvalidArgumentException( 'Mollie revocations require a provider event time.' );
            }

            $this->remove( $payment, (string) ( $payment->id ?? '' ), $at );
            return;
        }

        $this->grant( $payment, (string) ( $payment->id ?? '' ), 'once', null,
            $at ?? $this->occurred( $payment ),
        );
    }


    /**
     * Handles Cashier Mollie's model-rich subscription events.
     */
    public function subscription( object $subscription, bool $cancelled = false,
        ?\DateTimeInterface $at = null
    ) : void
    {
        $type = (string) ( $subscription->name ?? '' );
        $user = $subscription->owner ?? null;
        $id = (string) ( $subscription->mollie_id ?? $subscription->id ?? '' );
        $tenant = $user instanceof Model ? $user->getAttribute( 'tenant_id' ) : null;
        $role = is_string( $tenant ) ? CashierAccess::subscriptionAccess( $type, $tenant ) : null;

        if( !$user instanceof Authenticatable || !is_string( $tenant ) || $role === null || $id === '' ) {
            return;
        }

        $end = $this->end(
            $subscription->ends_at
                ?? $subscription->cycle_ends_at
                ?? $subscription->trial_ends_at
                ?? null
        );

        $at = $at
            ? \DateTimeImmutable::createFromInterface( $at )
            : $this->occurred( $subscription );

        if( $cancelled )
        {
            if( !$end || $end <= new \DateTimeImmutable() ) {
                $this->access->remove( $user, $tenant, $this->provider, $id, $at );
            }

            return;
        }

        $plan = (string) ( $subscription->plan ?? '' );

        if( $end && $this->plans->matches( $plan, $type ) )
        {
            $this->access->grant(
                $user,
                $tenant,
                $role,
                $this->provider,
                $id,
                $end,
                $at,
            );
        }
    }


    /**
     * Projects authoritative provider state before acknowledging its webhook.
     */
    public function webhook( object $payment, bool $firstPayment = false,
        ?\DateTimeInterface $at = null
    ) : bool
    {
        if( $this->revoked( $payment ) )
        {
            $this->revoke( $payment, $at ?? $this->adverse( $payment ) );
            return false;
        }

        if( !method_exists( $payment, 'isPaid' ) || !$payment->isPaid() ) {
            return true;
        }

        $at ??= $this->occurred( $payment );
        $order = $this->paymentOrder( $payment );

        if( $order ) {
            $this->order( $order, at: $at );
        } elseif( $firstPayment ) {
            $this->payment( $payment, at: $at );
        }

        return true;
    }


    /**
     * Resolves local Cashier ownership without authorizing an unknown tombstone.
     */
    protected function owner( array|object $data, string $id ) : ?Authenticatable
    {
        $owner = is_object( $data ) ? ( $data->owner ?? null ) : null;
        return $owner instanceof Authenticatable ? $owner : null;
    }


    /**
     * @param ProductData $product
     * @param array<string, string> $metadata
     */
    protected function start( Authenticatable $user, array $product, array $metadata ) : RedirectResponse
    {
        if( $product['kind'] === 'once' ) {
            return $this->once( $user, $product, $metadata );
        }

        return $this->subscribe( $user, $product );
    }


    /**
     * @param array<string, mixed>|object $data
     * @param array<string, mixed> $meta
     */
    protected function verifyRemove( array|object $data, array $meta,
        Authenticatable $user, string $id
    ) : bool {
        $source = is_object( $data )
            ? (string) ( $data->id ?? $data->mollie_payment_id ?? '' )
            : (string) ( $data['id'] ?? $data['mollie_payment_id'] ?? '' );

        return $source !== '' && hash_equals( $source, $id );
    }


    /**
     * Returns the newest authoritative adverse-event timestamp.
     */
    private function adverse( object $payment ) : \DateTimeImmutable
    {
        $latest = null;
        $charged = $this->money( $payment->amountChargedBack ?? null );

        if( $charged && (int) $charged->getAmount() > 0 )
        {
            foreach( $this->events( $payment, 'chargebacks' ) as $event )
            {
                if( is_object( $event ) && empty( $event->reversedAt )
                    && ( $at = $this->date( $event->createdAt ?? $event->created_at ?? null ) )
                    && ( !$latest || $at > $latest )
                ) {
                    $latest = $at;
                }
            }
        }

        $amount = $this->money( $payment->amount ?? null );
        $refunded = $this->money( $payment->amountRefunded ?? null );

        if( $amount && $refunded
            && (int) $refunded->getAmount() >= max( 1, (int) $amount->getAmount() )
        ) {
            foreach( $this->events( $payment, 'refunds' ) as $event )
            {
                if( is_object( $event ) && ( $event->status ?? null ) === RefundStatus::REFUNDED
                    && ( $at = $this->date( $event->createdAt ?? $event->created_at ?? null ) )
                    && ( !$latest || $at > $latest )
                ) {
                    $latest = $at;
                }
            }
        }

        if( !$latest ) {
            throw new \RuntimeException( 'Mollie adverse event time is unavailable.' );
        }

        return $latest->setTime(
            (int) $latest->format( 'H' ),
            (int) $latest->format( 'i' ),
            (int) $latest->format( 's' ),
            999999,
        );
    }


    /**
     * Parses a provider date without allowing invalid webhook data to escape.
     */
    private function date( mixed $value ) : ?\DateTimeImmutable
    {
        try
        {
            return $value instanceof \DateTimeInterface
                ? \DateTimeImmutable::createFromInterface( $value )
                : ( is_string( $value ) && $value !== '' ? new \DateTimeImmutable( $value ) : null );
        }
        catch( \Throwable )
        {
            return null;
        }
    }


    /**
     * Returns embedded adverse events or retrieves them from Mollie.
     *
     * @return iterable<object>
     */
    private function events( object $payment, string $name ) : iterable
    {
        $embedded = $payment->_embedded ?? null;
        $events = is_object( $embedded )
            ? ( $embedded->{$name} ?? null )
            : ( is_array( $embedded ) ? ( $embedded[$name] ?? null ) : null );

        if( is_iterable( $events ) ) {
            return $events;
        }

        $events = method_exists( $payment, $name ) ? $payment->{$name}() : [];
        return is_iterable( $events ) ? $events : [];
    }


    /**
     * Limits a paid order to Pagible subscription items and preloads their owners.
     *
     * @param Builder<Model> $query
     */
    private function items( Builder $query ) : void
    {
        $class = Cashier::$subscriptionModel;

        if( !is_subclass_of( $class, Model::class ) ) {
            throw new \RuntimeException( 'Invalid Cashier Mollie subscription model.' );
        }

        $query
            ->whereHasMorph(
                'orderable',
                [$class],
                fn( Builder $query ) => $query->where(
                    'name',
                    'like',
                    CashierAccess::SUBSCRIPTION_PREFIX . '%',
                ),
            )
            ->with( 'orderable.owner' );
    }


    /**
     * Converts a Mollie amount object to its Money value.
     */
    private function money( mixed $value ) : ?Money
    {
        return is_object( $value ) ? mollie_object_to_money( $value ) : null;
    }


    /**
     * Returns the best available provider event timestamp.
     */
    private function occurred( object $source ) : \DateTimeImmutable
    {
        return $this->end(
            $source->paidAt
                ?? $source->updated_at
                ?? $source->created_at
                ?? $source->updatedAt
                ?? $source->createdAt
                ?? null
        ) ?? new \DateTimeImmutable();
    }


    /**
     * @param ProductData $product
     * @param array<string, string> $metadata
     */
    private function once( Authenticatable $user, array $product, array $metadata ) : RedirectResponse
    {
        if( !$user instanceof Model || !method_exists( $user, 'newFirstPaymentChargeThroughCheckout' ) ) {
            throw new \RuntimeException( 'Cashier Mollie is not installed.' );
        }

        $item = ( new ChargeItemBuilder( $user ) )
            ->unitPrice( $this->plans->money( $product ) )
            ->description( $product['description'] )
            ->make();

        /** @var FirstPaymentChargeBuilder $builder */
        $builder = $user->newFirstPaymentChargeThroughCheckout();
        $response = $builder
            ->addItem( $item )
            ->setRedirectUrl( $product['url'] )
            ->molliePaymentOverrides( [
                'metadata' => [
                    'owner' => [
                        'type' => $user->getMorphClass(),
                        'id' => $user->getKey(),
                    ],
                    'cms' => $metadata['cms'],
                ],
            ] )
            ->create();

        if( !$response instanceof RedirectResponse ) {
            throw new \RuntimeException( 'Cashier Mollie returned no checkout redirect.' );
        }

        return $response;
    }


    /**
     * Resolves and preloads the local order associated with a Mollie payment.
     */
    private function paymentOrder( object $payment ) : ?Model
    {
        $id = (string) ( $payment->id ?? '' );

        if( $id === '' ) {
            return null;
        }

        $paymentModel = Cashier::$paymentModel;
        $local = $paymentModel::query()
            ->where( 'mollie_payment_id', $id )
            ->first();
        $order = $local?->getRelationValue( 'order' );

        if( !$order instanceof Model )
        {
            $orderModel = Cashier::$orderModel;
            $order = $orderModel::query()
                ->where( 'mollie_payment_id', $id )
                ->first();
        }

        if( $order instanceof Model )
        {
            $order->load( [
                'items' => fn( HasMany $relation ) => $this->items( $relation->getQuery() ),
                'owner',
            ] );

            if( ( $metadata = $payment->metadata ?? null ) !== null ) {
                $order->setAttribute( 'metadata', $metadata );
            }
        }

        return $order instanceof Model ? $order : null;
    }


    /**
     * Revokes the order or one-time source represented by a payment.
     */
    private function revoke( object $payment, \DateTimeInterface $at ) : void
    {
        if( $order = $this->paymentOrder( $payment ) )
        {
            $this->order( $order, true, $at );
            return;
        }

        $this->payment( $payment, true, $at );
    }


    /**
     * Revokes Pagible subscriptions or a one-time source from an order.
     *
     * @param iterable<mixed>|null $items
     */
    private function revokeOrder( object $order, ?iterable $items = null,
        ?\DateTimeInterface $occurred = null
    ) : void
    {
        $subscription = false;
        $at = $occurred ?? $this->occurred( $order );

        foreach( $items ?? ( $order->items ?? [] ) as $item )
        {
            $original = is_object( $item ) ? ( $item->originalOrderItem ?? null ) : null;
            $source = is_object( $item ) ? ( $item->orderable ?? null ) : null;
            $source = $source ?: ( is_object( $original ) ? ( $original->orderable ?? null ) : null );
            $user = is_object( $source ) ? ( $source->owner ?? null ) : null;
            $id = is_object( $source )
                ? (string) ( $source->mollie_id ?? $source->id ?? '' ) : '';
            $tenant = $user instanceof Model ? $user->getAttribute( 'tenant_id' ) : null;
            $role = is_object( $source ) && is_string( $tenant )
                ? CashierAccess::subscriptionAccess( (string) ( $source->name ?? '' ), $tenant ) : null;

            if( $user instanceof Authenticatable && is_string( $tenant ) && $role !== null && $id !== '' )
            {
                $this->access->remove( $user, $tenant, $this->provider, $id, $at );
                $subscription = true;
            }
        }

        if( $subscription ) {
            return;
        }

        $user = $order->owner ?? null;
        $id = (string) ( $order->mollie_payment_id ?? '' );

        if( $user instanceof Authenticatable && $id !== '' ) {
            $this->remove( $order, $id, $at );
        }
    }


    /**
     * Tests whether a payment is fully refunded or charged back.
     */
    private function revoked( object $payment ) : bool
    {
        $charged = $this->money( $payment->amountChargedBack ?? null );

        if( $charged && (int) $charged->getAmount() > 0 ) {
            return true;
        }

        $amount = $this->money( $payment->amount ?? null );
        $refunded = $this->money( $payment->amountRefunded ?? null );

        return $amount && $refunded
            && (int) $refunded->getAmount() >= max( 1, (int) $amount->getAmount() );
    }


    /**
     * Starts a subscription with the signed pricing-content plan snapshot.
     *
     * @param ProductData $product
     */
    private function subscribe( Authenticatable $user, array $product ) : RedirectResponse
    {
        if( !$user instanceof Model || !method_exists( $user, 'newSubscriptionViaMollieCheckout' ) ) {
            throw new \RuntimeException( 'Cashier Mollie is not installed.' );
        }

        $type = CashierAccess::subscription( Tenancy::value(), $product['access'] );

        /** @var FirstPaymentSubscriptionBuilder $builder */
        $builder = $user->newSubscriptionViaMollieCheckout(
            $type,
            $this->plans->create( $product, $type ),
            ['redirectUrl' => $product['url']],
        );

        $response = $builder->create();

        if( !$response instanceof RedirectResponse ) {
            throw new \RuntimeException( 'Cashier Mollie returned no checkout redirect.' );
        }

        return $response;
    }
}
