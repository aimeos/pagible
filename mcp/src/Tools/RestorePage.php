<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Resource;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Models\Page;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('restore-page')]
#[Title('Restore a soft-deleted page')]
#[Description('Restores a previously soft-deleted page. Returns the restored page as a JSON object.')]
class RestorePage extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'page:keep', $request->user() )
            || !Permission::can( 'page:view', $request->user() ) ) {
            throw new \Aimeos\Cms\Exception( 'Insufficient permissions' );
        }

        $v = $request->validate([
            'id' => 'required|string|max:36',
        ], [
            'id.required' => 'You must specify the ID of the page to restore.',
        ] );

        /** @var Page|null $page */
        $page = Page::withTrashed()->select( 'id', 'tenant_id', 'deleted_at' )->find( $v['id'] );

        if( !$page ) {
            return Response::structured( ['error' => 'Page not found.'] );
        }

        if( !$page->trashed() ) {
            return Response::structured( ['error' => 'Page is not deleted.'] );
        }

        $items = Resource::restore( Page::class, [$v['id']], $request->user() );

        /** @var Page $restored */
        $restored = $items->firstOrFail();

        return Response::structured( ['id' => $restored->id] + $restored->toArray() + ['url' => route( 'cms.page', ['path' => $restored->path] )] );
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
        ];
    }


    /**
     * Determine if the tool should be registered.
     *
     * @param Request $request The incoming request to check permissions for.
     * @return bool TRUE if the tool should be registered, FALSE otherwise.
     */
    public function shouldRegister( Request $request ) : bool
    {
        return Permission::can( 'page:keep', $request->user() )
            && Permission::can( 'page:view', $request->user() );
    }
}
