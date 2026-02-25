<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Permission;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[IsReadOnly]
#[Name('get-locales')]
#[Title('Get available ISO language codes')]
#[Description('Returns the list of available ISO language codes for the pages and their content as JSON array.')]
class GetLocales extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(): \Laravel\Mcp\ResponseFactory
    {
        return Response::structured( config( 'cms.config.locales', [] ) );
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
