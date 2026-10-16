<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Models\PageView;
use Illuminate\Contracts\Auth\Authenticatable;


/**
 * Base class of the tools restoring a soft-deleted item.
 */
abstract class RestoreTool extends LifecycleTool
{
    /**
     * Model class of the item to check if it's soft-deleted
     *
     * @var class-string<Base>
     */
    protected const MODEL = Base::class;

    protected const VERB = 'restore';
    protected const PREFIX = 'soft-deleted ';


    /**
     * Restores the soft-deleted item.
     *
     * @param array<string, mixed> $v Validated input
     * @param Authenticatable|null $user Authenticated user
     * @return array<string, mixed> Restored item or error
     */
    protected function apply( array $v, ?Authenticatable $user ) : array
    {
        $item = ( static::MODEL )::withTrashed()->select( 'id', 'tenant_id', 'deleted_at' )->whereKey( $v['id'] )->firstOrFail();

        if( !$item->trashed() ) {
            return ['error' => ucfirst( static::NOUN ) . ' is not deleted.'];
        }

        return Presenter::item( ( static::RESOURCE )::restore( $v['id'], $user ), static::MODEL === PageView::class );
    }
}
