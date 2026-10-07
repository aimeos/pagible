<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Resource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('restore-page')]
#[Title('Restore a soft-deleted page')]
#[Description('Restores a previously soft-deleted page, or only one deleted language variant if lang is passed. Returns the restored page as a JSON object.')]
class RestorePage extends Tool
{
    protected const PERMISSIONS = ['page:keep'];


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : \Laravel\Mcp\ResponseFactory
    {
        $v = $request->validate([
            'id' => 'required|string|max:36',
            'lang' => 'string|max:10',
        ], [
            'id.required' => 'You must specify the ID of the page to restore.',
        ] );

        if( isset( $v['lang'] ) )
        {
            $item = Resource::restoreVariant( $v['id'], $v['lang'], $request->user() );
            return Response::structured( Presenter::item( $item, true ) + ['stale' => $item->stale] );
        }

        /** @var Page $item */
        $item = Page::withTrashed()->select( 'id', 'tenant_id', 'deleted_at' )->findOrFail( $v['id'] );

        if( !$item->trashed() ) {
            return Response::structured( ['error' => 'Page is not deleted.'] );
        }

        $items = Resource::restore( Page::class, [$v['id']], $request->user() );

        return Response::structured( Presenter::item( $items->firstOrFail(), true ) );
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
                ->description('The UUID of the soft-deleted page to restore.')
                ->required(),
            'lang' => $schema->string()
                ->description('ISO language code to restore only that deleted language variant of the page, e.g., "de".'),
        ];
    }
}
