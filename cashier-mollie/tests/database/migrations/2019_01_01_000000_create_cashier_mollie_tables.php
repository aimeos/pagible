<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Laravel\Cashier\Cashier;


return new class extends Migration
{
    private const MIGRATIONS = [
        'orders' => ['create_orders_table.php.stub', 'CreateOrdersTable'],
        'order_items' => ['create_order_items_table.php.stub', 'CreateOrderItemsTable'],
        'subscriptions' => ['create_subscriptions_table.php.stub', 'CreateSubscriptionsTable'],
        'payments' => ['create_payments_table.php.stub', 'CreatePaymentsTable'],
        'refunds' => ['create_refunds_table.php.stub', 'CreateRefundsTable'],
        'refund_items' => ['create_refund_items_table.php.stub', 'CreateRefundItemsTable'],
    ];


    public function down(): void
    {
        foreach( array_reverse( array_keys( self::MIGRATIONS ) ) as $table ) {
            Schema::dropIfExists( $table );
        }
    }


    public function up(): void
    {
        $path = dirname( ( new ReflectionClass( Cashier::class ) )->getFileName(), 2 )
            . '/database/migrations/';

        foreach( self::MIGRATIONS as [$file, $class] )
        {
            if( !class_exists( $class, false ) ) {
                require_once $path . $file;
            }

            ( new $class() )->up();
        }
    }
};
