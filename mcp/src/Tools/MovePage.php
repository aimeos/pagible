<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Resource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('move-page')]
#[Title('Move a page in the tree')]
#[Description('Moves a page to a new position in the page tree. You can place it before a sibling, append it to a parent, or make it a root page. Returns the moved page as a JSON object.')]
class MovePage extends Tool
{
    protected const PERMISSIONS = ['page:move'];


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : \Laravel\Mcp\ResponseFactory
    {
        $v = $request->validate([
            'id' => 'required|string|max:36',
            'parent_id' => 'string|max:36',
            'before_id' => 'string|max:36',
        ], [
            'id.required' => 'You must specify the ID of the page to move.',
        ] );

        $page = Resource::movePage( $v['id'], $v['before_id'] ?? null, $v['parent_id'] ?? null, $request->user() );

        return Response::structured( Presenter::item( $page, true ) );
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
}
