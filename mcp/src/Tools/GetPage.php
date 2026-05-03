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
#[Name('get-page')]
#[Title('Get a page by ID or path')]
#[Description('Retrieves a single page by its ID or URL path. Returns the full page data including content, meta, config, and the URL as a JSON object.')]
class GetPage extends Tool
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
            'id' => 'nullable|string|max:36',
            'path' => 'nullable|string|max:255',
        ] );

        if( ( $v['id'] ?? null ) === null && ( $v['path'] ?? null ) === null ) {
            throw new \Exception( 'You must specify either an ID or a path.' );
        }

        $query = Page::withTrashed()
            ->select( 'id', 'parent_id', 'latest_id', 'created_at', 'deleted_at' )
            ->with( ['latest' => fn( $q ) => $q->select( 'id', 'versionable_id', 'data', 'aux', 'lang', 'editor', 'published', 'publish_at', 'created_at' )] );

        if( !empty( $v['id'] ) ) {
            $page = $query->find( $v['id'] );
        } else {
            $page = $query->where( 'path', $v['path'] ?? '' )->first();
        }

        if( !$page ) {
            return Response::structured( ['error' => 'Page not found.'] );
        }

        /** @var Page $page */
        $version = $page->latest;
        $vdata = $version?->data;
        $vaux = $version?->aux;

        $data = [
            'id' => $page->id,
            'parent_id' => $page->parent_id,
            'deleted' => $page->trashed(),
            'lang' => $version->lang ?? '',
            'editor' => $version->editor ?? '',
            'tag' => $vdata->tag ?? '',
            'path' => $vdata->path ?? '',
            'domain' => $vdata->domain ?? '',
            'to' => $vdata->to ?? '',
            'name' => $vdata->name ?? '',
            'title' => $vdata->title ?? '',
            'type' => $vdata->type ?? '',
            'theme' => $vdata->theme ?? '',
            'status' => $vdata->status ?? 0,
            'cache' => $vdata->cache ?? 0,
            'content' => $vaux->content ?? [],
            'meta' => $vaux->meta ?? new \stdClass(),
            'config' => $vaux->config ?? new \stdClass(),
            'published' => $version->published ?? false,
            'publish_at' => $version->publish_at ?? null,
            'created_at' => $page->created_at?->format( 'Y-m-d H:i:s' ),
            'updated_at' => $version?->created_at?->format( 'Y-m-d H:i:s' ),
            'url' => route( 'cms.page', ['path' => $vdata->path ?? ''] ),
        ];

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
