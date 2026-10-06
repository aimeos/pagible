<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

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
#[Name('get-page-history')]
#[Title('Get version history for a page')]
#[Description('Returns the version history of a page ordered by most recent first. Each version includes the editor, language, published status, scheduled publication date, and creation timestamp.')]
class GetPageHistory extends Tool
{
    protected const PERMISSIONS = ['page:view'];


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : \Laravel\Mcp\ResponseFactory
    {
        $v = $request->validate([
            'id' => 'required|string|max:36',
            'limit' => 'integer|min:1|max:50',
        ], [
            'id.required' => 'You must specify the page ID to get version history for.',
        ] );

        /** @var Page $page */
        $page = Page::withTrashed()->select( 'id', 'tenant_id', 'name' )->findOrFail( $v['id'] );

        $result = [];
        $limit = $v['limit'] ?? 10;
        $versions = $page->versions()->select(
            'id', 'tenant_id', 'versionable_id', 'editor', 'lang', 'published', 'publish_at', 'created_at', 'data', 'aux'
        )->take( $limit )->get();

        foreach( $versions as $version )
        {
            /** @var Version $version */
            $result[] = [
                'id' => $version->id,
                'editor' => $version->editor,
                'lang' => $version->lang,
                'published' => (bool) $version->published,
                'publish_at' => $version->publish_at,
                'created_at' => $version->created_at?->format( 'Y-m-d H:i:s' ),
                'data' => $version->data ?? [],
                'aux' => $version->aux ?? [],
            ];
        }

        return Response::structured( [
            'page_id' => $page->id,
            'page_name' => $page->name,
            'versions' => $result,
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
}
