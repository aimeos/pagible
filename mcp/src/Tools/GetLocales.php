<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[IsReadOnly]
#[Name('get-locales')]
#[Title('Get available ISO language codes')]
#[Description('Returns the list of available ISO language codes for the pages and their content as JSON array.')]
class GetLocales extends Tool
{
    protected const PERMISSIONS = ['*'];


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : \Laravel\Mcp\ResponseFactory
    {
        return Response::structured( ['locales' => config( 'cms.locales', [] )] );
    }
}
