<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace App\Models;


class User extends \Illuminate\Foundation\Auth\User
{
    protected $attributes = [
        'name' => '',
        'email' => '',
        'password' => '',
        'cmsperms' => '[]',
        'cmsdata' => null,
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'cmsperms',
        'cmsdata',
    ];

    protected $casts = [
        'cmsperms' => 'array',
    ];


    public function getTenantIdAttribute( mixed $value ) : string
    {
        return (string) ( $value ?? 'test' );
    }

}
