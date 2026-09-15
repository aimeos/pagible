<?php

namespace App\Models;


class User extends \Illuminate\Foundation\Auth\User
{
    protected $attributes = [
        'name' => '',
        'email' => '',
        'password' => '',
        'cmsperms' => '[]',
    ];

    protected $fillable = ['name', 'email', 'password', 'cmsperms'];
    protected $casts = ['cmsperms' => 'array'];


    public function getTenantIdAttribute( mixed $value ) : string
    {
        return (string) ( $value ?? 'test' );
    }
}
