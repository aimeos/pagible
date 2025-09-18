<?php

/**
 * @license MIT, http://opensource.org/licenses/MIT
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
     * @var array
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
     * @var array
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
