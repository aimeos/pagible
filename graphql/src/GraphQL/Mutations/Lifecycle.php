<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Resource;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Execution\ResolveInfo;


/**
 * Base class of the mutations dropping, restoring and purging items.
 */
abstract class Lifecycle
{
    /**
     * Model class of the items
     *
     * @var class-string<Base>
     */
    protected const MODEL = Base::class;

    /**
     * Action executed for the items: "drop", "restore" or "purge"
     */
    protected const ACTION = 'drop';


    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array<int, mixed>
     */
    public function __invoke( $rootValue, array $args, mixed $context = null, ?ResolveInfo $info = null ) : array
    {
        $fields = array_keys( $info?->getFieldSelection( 1 ) ?? [] );

        if( static::MODEL === PageView::class && isset( $args['lang'] ) ) {
            return Resource::variants( static::ACTION, $args['id'], $args['lang'], Auth::user(), $fields )->all();
        }

        $items = match( static::ACTION ) {
            'restore' => Resource::restore( static::MODEL, $args['id'], Auth::user(), $fields ),
            'purge' => Resource::purge( static::MODEL, $args['id'], Auth::user(), $fields ),
            default => Resource::drop( static::MODEL, $args['id'], Auth::user(), $fields ),
        };

        return $items->all();
    }
}
