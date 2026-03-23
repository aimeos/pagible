<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
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

}
