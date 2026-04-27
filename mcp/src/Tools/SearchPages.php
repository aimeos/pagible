<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Filter;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Models\Page;
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
#[Name('search-pages')]
#[Title('Search for pages by keywords')]
#[Description('Lists and searches pages. Optional: term (full-text search), lang, domain, type, tag, theme, path, status, cache, to, trashed (without/with/only), publish (PUBLISHED/DRAFT/SCHEDULED), editor. Returns up to 25 matches.')]
class SearchPages extends Tool
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
            'term' => 'string|max:255',
            'lang' => 'string|max:5',
            'status' => 'integer|in:0,1,2',
            'parent_id' => 'string|max:36',
            'type' => 'string|max:50',
            'trashed' => 'string|in:without,with,only',
            'publish' => 'string|in:PUBLISHED,DRAFT,SCHEDULED',
            'editor' => 'string|max:255',
            'domain' => 'string|max:255',
            'tag' => 'string|max:50',
            'theme' => 'string|max:50',
            'path' => 'string|max:255',
            'cache' => 'integer|min:0|max:525600',
            'to' => 'string|max:2000',
        ] );

        $search = Page::search( mb_substr( trim( (string) ( $v['term'] ?? '' ) ), 0, 200 ) )
            ->query( fn( $q ) => $q->select( 'cms_pages.id', 'parent_id', 'path', 'has', 'created_at', 'updated_at', 'deleted_at', 'latest_id', NestedSet::LFT, NestedSet::RGT )
            ->with( ['latest' => fn( $q ) => $q->select( 'id', 'versionable_id', 'data', 'lang', 'editor' )] ) )
            ->searchFields( 'draft' )
            ->take( 25 );

        $result = [];

        foreach( Filter::pages( $search, $v )->get() as $item )
        {
            /** @var Page $item */
            $latest = $item->latest;
            $data = $latest->data ?? new \stdClass();
            $result[] = [
                'id' => $item->id,
                'has_children' => $item->has,
                'parent_id' => $item->parent_id,
                'tag' => $data->tag ?? null,
                'path' => $data->path ?? null,
                'domain' => $data->domain ?? null,
                'to' => $data->to ?? null,
                'name' => $data->name ?? null,
                'title' => $data->title ?? null,
                'type' => $data->type ?? null,
                'theme' => $data->theme ?? null,
                'status' => $data->status ?? null,
                'cache' => $data->cache ?? null,
                'lang' => $latest?->lang,
                'editor' => $latest?->editor,
                'deleted' => $item->trashed(),
                'created_at' => $item->created_at?->format( 'Y-m-d H:i:s' ),
                'updated_at' => $item->updated_at?->format( 'Y-m-d H:i:s' ),
                'url' => route( 'cms.page', ['path' => $data->path ?? ''] ),
            ];
        }

        return Response::structured( ['pages' => $result] );
    }


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return [
            'term' => $schema->string()
                ->description('Search keyword, e.g., "blog", "product", or "FAQ". One word or page path only.'),
            'lang' => $schema->string()
                ->description('ISO language code from the get-locales tool call, e.g., "en" or "en-US".'),
            'status' => $schema->integer()
                ->description('Filter by status: 0 = inactive, 1 = visible, 2 = hidden.'),
            'parent_id' => $schema->string()
                ->description('Filter by parent page ID to list children of a specific page.'),
            'type' => $schema->string()
                ->description('Filter by page type, e.g., "page", "blog", "docs".'),
            'trashed' => $schema->string()
                ->description('Include trashed items: "without" (default), "with" (include deleted), or "only" (only deleted).'),
            'publish' => $schema->string()
                ->description('Filter by publish status: "PUBLISHED", "DRAFT", or "SCHEDULED".'),
            'editor' => $schema->string()
                ->description('Filter by editor name.'),
            'domain' => $schema->string()
                ->description('Filter by domain name.'),
            'tag' => $schema->string()
                ->description('Filter by page tag, e.g., "root".'),
            'theme' => $schema->string()
                ->description('Filter by theme name.'),
            'path' => $schema->string()
                ->description('Filter by page path.'),
            'cache' => $schema->integer()
                ->description('Filter by cache duration in minutes.'),
            'to' => $schema->string()
                ->description('Filter by redirect URL.'),
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
