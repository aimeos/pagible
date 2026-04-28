<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Permission;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\Nav;
use Aimeos\Nestedset\NestedSet;
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
#[Description('Returns the page tree as nested JSON. Pass node_id (UUID) for a subtree, or omit for all root pages (up to 50). Optional lang filter (ISO code, only without node_id).')]
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

        $v = $request->validate([
            'node_id' => 'string|max:36',
            'lang' => 'string|max:5',
        ]);

        $builder = Page::tree( $v['node_id'] ?? null )
            ->select( 'id', 'parent_id', 'tenant_id', 'lang', 'latest_id', NestedSet::LFT, NestedSet::RGT, NestedSet::DEPTH )
            ->with( ['latest' => fn( $q ) => $q->select( 'id', 'versionable_id', 'data' )] );

        if( !isset( $v['node_id'] ) && isset( $v['lang'] ) ) {
            $builder->where( 'lang', $v['lang'] );
        }

        /** @var \Aimeos\Nestedset\Collection $nodes */
        $nodes = $builder->get();
        $tree = $this->buildTree( $nodes->toTree() );

        return Response::structured( ['tree' => $tree] );
    }


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return [
            'node_id' => $schema->string()
                ->description('ID of the root page to get the subtree for. Omit to get all root pages.'),
            'lang' => $schema->string()
                ->description('Filter root pages by ISO language code, e.g., "en". Only used when node_id is not set.'),
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
            $data = $node->latest?->data;
            $entry = [
                'id' => $node->id,
                'name' => $data->name ?? '',
                'title' => $data->title ?? '',
                'domain' => $data->domain ?? '',
                'path' => $data->path ?? '',
                'lang' => $data->lang ?? '',
                'cache' => $data->cache ?? 0,
                'status' => $data->status ?? 0,
                'type' => $data->type ?? '',
                'tag' => $data->tag ?? '',
                'to' => $data->to ?? '',
                'children' => [],
            ];

            if( $node->children->count() > 0 ) {
                $entry['children'] = $this->buildTree( $node->children );
            }

            $result[] = $entry;
        }

        return $result;
    }
}
