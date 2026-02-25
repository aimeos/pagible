<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Models;


/**
 * Nav model
 */
class Nav extends Page
{
    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'lang' => '',
        'path' => '',
        'domain' => '',
        'to' => '',
        'name' => '',
        'title' => '',
        'status' => 0,
        'config' => '{}',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'lang',
        'path',
        'domain',
        'to',
        'name',
        'title',
        'status',
    ];


    /**
     * Returns the name of the used morph class.
     *
     * @return string Class name
     */
    public function getMorphClass()
    {
        return Page::class;
    }
}
