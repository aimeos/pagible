<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Utils;
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


#[Name('move-page')]
#[Title('Move a page in the tree')]
#[Description('Moves a page to a new position in the page tree. You can place it before a sibling, append it to a parent, or make it a root page. Returns the moved page as a JSON object.')]
class MovePage extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'page:move', $request->user() ) ) {
            throw new \Exception( 'Insufficient permissions' );
        }

        $v = $request->validate([
            'id' => 'required|string|max:36',
            'parent_id' => 'string|max:36',
            'before_id' => 'string|max:36',
        ], [
            'id.required' => 'You must specify the ID of the page to move.',
        ] );

        /** @var Page|null $page */
        $page = Page::withTrashed()->find( $v['id'] );

        if( !$page ) {
            return Response::structured( ['error' => 'Page not found.'] );
        }

        return Utils::lockedTransaction( function() use ( $page, $v, $request ) {

            $page->editor = Utils::editor( $request->user() );

            Resource::position( $page, $v['before_id'] ?? null, $v['parent_id'] ?? null, true );

            Page::withoutSyncingToSearch( fn() => $page->save() );

            return Response::structured( $page->toArray() + ['url' => route( 'cms.page', ['path' => $page->path] )] );
        } );
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
                ->description('The UUID of the page to move.')
                ->required(),
            'parent_id' => $schema->string()
                ->description('Move the page as last child of this parent page. Omit both parent_id and before_id to make it a root page.'),
            'before_id' => $schema->string()
                ->description('Move the page before this sibling page. Takes priority over parent_id if both are set.'),
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
        return Permission::can( 'page:move', $request->user() );
    }
}
