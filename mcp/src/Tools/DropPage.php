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


#[Name('drop-page')]
#[Title('Soft-delete a page')]
#[Description('Soft-deletes a page with all its language variants, or only one language variant if lang is passed. The page or variant can be restored within the retention period using restore-page. Returns the deleted page as a JSON object.')]
class DropPage extends Tool
{
    protected const PERMISSIONS = ['page:drop'];


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : \Laravel\Mcp\ResponseFactory
    {
        $v = $request->validate([
            'id' => 'required|string|max:36',
            'lang' => 'string|max:10',
        ], [
            'id.required' => 'You must specify the ID of the page to delete.',
        ] );

        if( isset( $v['lang'] ) ) {
            return Response::structured( Presenter::item( Resource::dropVariant( $v['id'], $v['lang'], $request->user() ) ) );
        }

        if( !( $item = Resource::drop( Page::class, [$v['id']], $request->user() )->first() ) ) {
            return Response::structured( ['error' => 'Page not found.'] );
        }

        return Response::structured( Presenter::item( $item ) );
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
                ->description('The UUID of the page to delete.')
                ->required(),
            'lang' => $schema->string()
                ->description('ISO language code to delete only that language variant of the page, e.g., "de". The source language can not be deleted.'),
        ];
    }
}
