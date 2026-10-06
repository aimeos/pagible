<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Resource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('restore-element')]
#[Title('Restore a soft-deleted shared content element')]
#[Description('Restores a previously soft-deleted shared content element. Returns the restored element as a JSON object.')]
class RestoreElement extends Tool
{
    protected const PERMISSIONS = ['element:keep'];


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : \Laravel\Mcp\ResponseFactory
    {
        $v = $request->validate([
            'id' => 'required|string|max:36',
        ], [
            'id.required' => 'You must specify the ID of the element to restore.',
        ] );

        /** @var Element $item */
        $item = Element::withTrashed()->select( 'id', 'tenant_id', 'deleted_at' )->findOrFail( $v['id'] );

        if( !$item->trashed() ) {
            return Response::structured( ['error' => 'Element is not deleted.'] );
        }

        $items = Resource::restore( Element::class, [$v['id']], $request->user() );

        return Response::structured( Presenter::item( $items->firstOrFail() ) );
    }


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return [
            'id' => $schema->string()
                ->description('The UUID of the soft-deleted element to restore.')
                ->required(),
        ];
    }
}
