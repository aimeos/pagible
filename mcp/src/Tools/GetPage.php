<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Permission;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\Version;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[IsReadOnly]
#[Name('get-page')]
#[Title('Get a page by ID or path')]
#[Description('Retrieves a single page by its ID or URL path. Returns the full page data including its frontend restriction state, content, meta, config, and URL. Callers with page:access also receive the immediate access values. The returned latest_id identifies the version you read — pass it back to save-page so concurrent edits are merged instead of overwritten.')]
class GetPage extends Tool
{
    protected const PERMISSIONS = ['page:view'];


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : \Laravel\Mcp\ResponseFactory
    {
        $v = $request->validate([
            'id' => 'nullable|string|max:36',
            'path' => 'nullable|string|max:255',
        ] );

        if( ( $v['id'] ?? null ) === null && ( $v['path'] ?? null ) === null ) {
            throw new \Aimeos\Cms\Exception( 'You must specify either an ID or a path.' );
        }

        $canAccess = Permission::can( 'page:access', $request->user() );
        $with = [
            'latest' => fn( $q ) => $q->select( [...Version::SELECT_COLUMNS, 'aux', 'publish_at', 'created_at'] ),
        ];

        if( $canAccess ) {
            $with['access'] = fn( $q ) => $q->select( 'page_id', 'tenant_id', 'value' );
        }

        $query = Page::withTrashed()
            ->select( 'id', 'tenant_id', 'parent_id', 'latest_id', 'created_at', 'deleted_at' )
            ->withCount( 'access' )
            ->with( $with );

        /** @var Page $page */
        $page = !empty( $v['id'] )
            ? $query->findOrFail( $v['id'] )
            : $query->where( 'path', $v['path'] ?? '' )->firstOrFail();

        $data = ['restricted' => $page->restricted()] + Presenter::page( $page );

        if( $canAccess ) {
            $data['access'] = $page->accessValues();
        }

        return Response::structured( $data );
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
                ->description('The UUID of the page to retrieve.'),
            'path' => $schema->string()
                ->description('The URL path of the page to retrieve, e.g., "blog/my-article".'),
        ];
    }
}
