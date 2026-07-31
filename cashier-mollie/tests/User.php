<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace App\Models;


class User extends \Illuminate\Foundation\Auth\User
{
    use \Aimeos\Cms\Concerns\CashierAccess;
    use \Laravel\Cashier\Billable;

    protected $guarded = [];


    public function getTenantIdAttribute( mixed $value ) : string
    {
        return (string) ( $value ?? 'test' );
    }
}
