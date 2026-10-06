<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\JsonSchema;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[IsReadOnly]
#[Name('get-schemas')]
#[Title('Get available content type schemas')]
#[Description('Returns the available content, meta, and config element types and their field definitions. Use this to understand what structures are valid when creating or saving pages.')]
class GetSchemas extends Tool
{
    protected const PERMISSIONS = ['*'];


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : \Laravel\Mcp\ResponseFactory
    {
        return Response::structured( [
            'content' => JsonSchema::build( 'content' ),
            'meta' => JsonSchema::build( 'meta' ),
            'config' => JsonSchema::build( 'config' ),
        ] );
    }
}
