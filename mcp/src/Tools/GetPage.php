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
            'id' => 'required_without:path|string|max:36',
            'path' => 'required_without:id|string|max:255',
        ], [
            'id.required_without' => 'You must specify either an ID or a path.',
            'path.required_without' => 'You must specify either an ID or a path.',
        ] );

        if( !empty( $v['id'] ) ) {
            $page = Page::withTrashed()->find( $v['id'] );
        } else {
            $page = Page::withTrashed()->where( 'path', $v['path'] )->first();
        }

        if( !$page ) {
            return Response::structured( ['error' => 'Page not found.'] );
        }

        /** @var Page $page */
        $data = $page->toArray();
        $data['url'] = route( 'cms.page', ['path' => $page->path] );

        if( $latest = $page->latest ) {
            $data['latest_version'] = array_merge(
                (array) $latest->data,
                ['meta' => $latest->aux->meta ?? null],
                ['content' => $latest->aux->content ?? null],
                ['config' => $latest->aux->config ?? null],
                ['published' => $latest->published],
                ['publish_at' => $latest->publish_at],
                ['editor' => $latest->editor],
            );
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
