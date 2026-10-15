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
        $items = match( static::MODEL ) {
            PageView::class => isset( $args['lang'] )
                ? Variants::publishMany( $args['id'], $args['lang'], Auth::user(), $args['at'] ?? null )
                : Pages::publishMany( $args['id'], Auth::user(), $args['at'] ?? null ),
            Element::class => Elements::publishMany( $args['id'], Auth::user(), $args['at'] ?? null ),
            File::class => Files::publishMany( $args['id'], Auth::user(), $args['at'] ?? null ),
            default => throw new \LogicException( 'No resource for ' . static::MODEL ),
        };

        return $items->all();
    }
}
