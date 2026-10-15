<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Illuminate\Contracts\Auth\Authenticatable;


/**
 * Base class of the tools soft-deleting an item.
 */
abstract class DropTool extends LifecycleTool
{
    protected const VERB = 'delete';


    /**
     * Soft-deletes the item.
     *
     * @param array<string, mixed> $v Validated input
     * @param Authenticatable|null $user Authenticated user
     * @return array<string, mixed> Deleted item or error
     */
    protected function apply( array $v, ?Authenticatable $user ) : array
    {
        return Presenter::item( $this->resource()::drop( $v['id'], $user ) );
    }
}
