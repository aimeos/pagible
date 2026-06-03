<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\JsonSchema;
use Aimeos\Cms\Permission;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[IsReadOnly]
#[Name('get-schemas')]
#[Title('Get available content type schemas')]
#[Description('Returns the available content, meta, and config element types and their field definitions. Use this to understand what structures are valid when creating or saving pages.')]
class GetSchemas extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(): \Laravel\Mcp\ResponseFactory
    {
        return Response::structured( [
            'content' => JsonSchema::build( 'content' ),
            'meta' => JsonSchema::build( 'meta' ),
            'config' => JsonSchema::build( 'config' ),
        ] );
    }


    /**
     * Determine if the tool should be registered.
     *
     * @param Request $request The incoming request to check permissions for.
     * @return bool TRUE if the tool should be registered, FALSE otherwise.
     */
    public function shouldRegister( Request $request ) : bool
    {
        return Permission::can( '*', $request->user() );
    }
}
