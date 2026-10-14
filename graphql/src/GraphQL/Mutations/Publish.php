<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Publication;
use Illuminate\Support\Facades\Auth;


/**
 * Base class of the mutations publishing items.
 */
abstract class Publish
{
    /**
     * Model class of the items
     *
     * @var class-string<Base>
     */
    protected const MODEL = Base::class;


    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array<int, mixed>
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        return Publication::publish( static::MODEL, $args['id'], Auth::user(), $args['at'] ?? null, $args['lang'] ?? null )->all();
    }
}
