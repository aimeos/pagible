<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Resource\Elements;
use Aimeos\Cms\Resource\Files;
use Aimeos\Cms\Resource\Pages;
use Aimeos\Cms\Resource\Variants;
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
            $items = match( static::ACTION ) {
                'restore' => Variants::restoreMany( $args['id'], $args['lang'], Auth::user(), $fields ),
                'purge' => Variants::purgeMany( $args['id'], $args['lang'], Auth::user(), $fields ),
                default => Variants::dropMany( $args['id'], $args['lang'], Auth::user(), $fields ),
            };

            return $items->all();
        }

        $items = match( static::ACTION ) {
            'restore' => $this->resource()::restoreMany( $args['id'], Auth::user(), $fields ),
            'purge' => $this->resource()::purgeMany( $args['id'], Auth::user(), $fields ),
            default => $this->resource()::dropMany( $args['id'], Auth::user(), $fields ),
        };

        return $items->all();
    }

    /**
     * Returns the resource class managing the items of the model.
     *
     * @return class-string<Pages|Elements|Files> Resource class
     */
    protected function resource() : string
    {
        return match( static::MODEL ) {
            PageView::class => Pages::class,
            Element::class => Elements::class,
            File::class => Files::class,
            default => throw new \LogicException( 'No resource for ' . static::MODEL ),
        };
    }

}
