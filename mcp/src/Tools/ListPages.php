<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Permission;
use Aimeos\Cms\Models\Page;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[IsReadOnly]
#[Name('list-pages')]
#[Title('List pages with optional filters')]
#[Description('Lists up to 25 pages. Optional filters: lang (ISO code), status (0=inactive, 1=visible, 2=hidden), parent_id (UUID), type. Returns JSON array with id, name, title, path, lang, status, type, parent_id, has_children, deleted, url.')]
class ListPages extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'page:view', $request->user() ) ) {
            throw new \Exception( 'Insufficient permissions' );
        }

        $v = $request->validate([
            'lang' => 'string|max:5',
            'status' => 'integer|in:0,1,2',
            'parent_id' => 'string|max:36',
            'type' => 'string|max:50',
        ]);

        $query = Page::withTrashed()
            ->select( 'id', 'name', 'title', 'path', 'lang', 'status', 'type', 'parent_id', '_lft', '_rgt', 'deleted_at' )
            ->defaultOrder();

        if( isset( $v['lang'] ) ) {
            $query->where( 'lang', $v['lang'] );
        }

        if( isset( $v['status'] ) ) {
            $query->where( 'status', (int) $v['status'] );
        }

        if( isset( $v['parent_id'] ) ) {
            $query->where( 'parent_id', $v['parent_id'] );
        }

        if( isset( $v['type'] ) ) {
            $query->where( 'type', $v['type'] );
        }

        $result = $query->take( 25 )->get()->map( function( $item ) {
            /** @var Page $item */
            return [
                'id' => $item->id,
                'name' => $item->name,
                'title' => $item->title,
                'path' => $item->path,
                'lang' => $item->lang,
                'status' => $item->status,
                'type' => $item->type,
                'parent_id' => $item->parent_id,
                'has_children' => $item->has,
                'deleted' => $item->trashed(),
                'url' => route( 'cms.page', ['path' => $item->path] ),
            ];
        } );

        return Response::structured( ['pages' => $result->all()] );
    }


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return [
            'lang' => $schema->string()
                ->description('Filter by ISO language code, e.g., "en" or "de".'),
            'status' => $schema->integer()
                ->description('Filter by status: 0 = draft, 1 = published.'),
            'parent_id' => $schema->string()
                ->description('Filter by parent page ID to list children of a specific page.'),
            'type' => $schema->string()
                ->description('Filter by page type, e.g., "page", "blog", "docs".'),
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
        return Permission::can( 'page:view', $request->user() );
    }
}
