<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Permission;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\Version;
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
#[Name('get-page-history')]
#[Title('Get version history for a page')]
#[Description('Returns the version history of a page ordered by most recent first. Each version includes the editor, language, published status, scheduled publication date, and creation timestamp.')]
class GetPageHistory extends Tool
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
            'id' => 'required|string|max:36',
            'limit' => 'integer|min:1|max:50',
        ], [
            'id.required' => 'You must specify the page ID to get version history for.',
        ] );

        /** @var Page|null $page */
        $page = Page::withTrashed()->find( $v['id'] );

        if( !$page ) {
            return Response::structured( ['error' => 'Page not found.'] );
        }

        $limit = $v['limit'] ?? 10;

        $versions = $page->versions()
            ->take( $limit )
            ->get()
            ->map( function( $version ) {
                /** @var Version $version */
                return [
                    'id' => $version->id,
                    'editor' => $version->editor,
                    'lang' => $version->lang,
                    'published' => (bool) $version->published,
                    'publish_at' => $version->publish_at,
                    'created_at' => $version->created_at?->format( 'Y-m-d H:i:s' ),
                    'data' => $version->data ?? [],
                    'aux' => $version->aux ?? [],
                ];
            } );

        return Response::structured( [
            'page_id' => $page->id,
            'page_name' => $page->name,
            'versions' => $versions->all(),
        ] );
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
                ->description('The UUID of the page to get version history for.')
                ->required(),
            'limit' => $schema->integer()
                ->description('Maximum number of versions to return (1-50, default: 10).'),
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
