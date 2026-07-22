<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Models;

use Aimeos\Nestedset\NestedSet;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Query\Expression;


/**
 * Nav model
 *
 * @property bool $access_exists
 */
class Nav extends Page
{
    /** @var list<string> Columns required by navigation and ancestor projections */
    public const SELECT_COLUMNS = [
        'id', 'tenant_id', 'parent_id', 'name', 'title', 'tag', 'path', 'domain', 'lang', 'to',
        'status', 'config', 'latest_id', NestedSet::LFT, NestedSet::RGT, NestedSet::DEPTH,
    ];


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
     * Finds the lightweight published page projection for a frontend route.
     *
     * The returned model is partial and must be treated as read-only.
     */
    public static function page( string $path, string $domain = '' ) : ?self
    {
        return self::query()
            ->select( 'id', 'tenant_id', 'domain', 'path', 'to', 'cache', 'status' )
            ->withAggregate( 'access as access_exists', new Expression( '1' ) )
            ->whereIn( 'status', [1, 2] )
            ->where( 'domain', $domain )
            ->where( 'path', $path )
            ->first();
    }


    /**
     * Returns the text content of the page.
     *
     * @return string Text content
     */
    public function __toString() : string
    {
        return trim( ( $this->name ?? '' ) . "\n" . ( $this->title ?? '' ) );
    }


    /**
     * Returns the name of the used morph class.
     *
     * @return string Class name
     */
    public function getMorphClass()
    {
        return Page::class;
    }


    /**
     * Whether the page has explicit frontend access rules.
     *
     * @return Attribute<bool, never>
     */
    protected function accessExists() : Attribute
    {
        return Attribute::get( fn( $value ) => (bool) $value );
    }
}
