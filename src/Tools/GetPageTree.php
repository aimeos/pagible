<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Permission;
use Aimeos\Cms\Models\Nav;
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
#[Name('get-page-tree')]
#[Title('Get the page tree hierarchy')]
#[Description('Returns the page tree as a nested JSON structure. Use root_id to get the subtree of a specific page, or omit it to get all root pages with their children up to 3 levels deep.')]
class GetPageTree extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'page:view', $request->user() ) ) {
            throw new \Exception( 'Insufficient permissions' );
        }

        $rootId = $request->get( 'root_id' );
        $lang = $request->get( 'lang' );

        if( $rootId )
        {
            /** @var Nav|null $root */
            $root = Nav::find( $rootId );

            if( !$root ) {
                return Response::structured( ['error' => 'Root page not found.'] );
            }

            $descendants = $root->subtree?->toTree() ?? collect();
            $tree = $this->buildTree( $descendants );
        }
        else
        {
            $query = Nav::whereNull( 'parent_id' )->defaultOrder();

            if( $lang ) {
                $query->where( 'lang', $lang );
            }

            $roots = $query->take( 50 )->get();

            $tree = $roots->map( function( $root ) {
                /** @var Nav $root */
                $node = [
                    'id' => $root->id,
                    'name' => $root->name,
                    'title' => $root->title,
                    'path' => $root->path,
                    'lang' => $root->lang,
                    'status' => $root->status,
                    'type' => $root->type,
                    'has_children' => $root->has,
                    'children' => [],
                ];

                if( $root->has ) {
                    $descendants = $root->subtree?->toTree() ?? collect();
                    $node['children'] = $this->buildTree( $descendants );
                }

                return $node;
            } )->all();
        }

        /** @phpstan-ignore argument.type */
        return Response::structured( ['tree' => $tree] );
    }


    /**
     * Recursively build a tree array from a nested set collection.
     *
     * @param \Aimeos\Nestedset\Collection|iterable<Nav> $nodes
     * @return array<int, array<string, mixed>>
     */
    protected function buildTree( $nodes ) : array
    {
        $result = [];

        foreach( $nodes as $node )
        {
            /** @var Nav $node */
            $entry = [
                'id' => $node->id,
                'name' => $node->name,
                'title' => $node->title,
                'path' => $node->path,
                'lang' => $node->lang,
                'status' => $node->status,
                'type' => $node->type,
                'children' => [],
            ];

            if( $node->children->count() > 0 ) {
                $entry['children'] = $this->buildTree( $node->children );
            }

            $result[] = $entry;
        }

        return $result;
    }


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return [
            'root_id' => $schema->string()
                ->description('ID of the root page to get the subtree for. Omit to get all root pages.'),
            'lang' => $schema->string()
                ->description('Filter root pages by ISO language code, e.g., "en". Only used when root_id is not set.'),
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
