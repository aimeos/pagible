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
#[Name('search-pages')]
#[Title('Search for pages by keywords')]
#[Description('Full-text search across pages. Optional: term (keywords), lang, trashed (without/with/only), publish (PUBLISHED/DRAFT/SCHEDULED), editor. Returns up to 25 matches.')]
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
            'trashed' => 'string|in:without,with,only',
            'publish' => 'string|in:PUBLISHED,DRAFT,SCHEDULED',
            'editor' => 'string|max:255',
        ] );

        $query = Page::select( 'cms_pages.*' )
            ->join( 'cms_versions', 'cms_pages.latest_id', '=', 'cms_versions.id' )
            ->orderBy( 'cms_pages.updated_at', 'desc' );

        switch( $v['trashed'] ?? null ) {
            case 'with': $query->withTrashed(); break;
            case 'only': $query->onlyTrashed(); break;
        }

        switch( $v['publish'] ?? null ) {
            case 'PUBLISHED': $query->where( 'cms_versions.published', true ); break;
            case 'DRAFT': $query->where( 'cms_versions.published', false ); break;
            case 'SCHEDULED': $query->where( 'cms_versions.publish_at', '!=', null )
                ->where( 'cms_versions.published', false ); break;
        }

        if( isset( $v['lang'] ) ) {
            $query->where( 'cms_versions.lang', $v['lang'] );
        }

        if( isset( $v['editor'] ) ) {
            $query->where( 'cms_versions.editor', $v['editor'] );
        }

        if( isset( $v['term'] ) )
        {
            $ids = Page::search( mb_substr( trim( (string) $v['term'] ), 0, 200 ) )
                ->searchFields( 'draft' )
                ->take( 250 )
                ->keys();

            $query->whereIn( 'cms_pages.id', $ids->all() );
        }

        $result = $query->take( 25 )->get()->map( function( $item ) {
            /** @var Page $item */
            return $item->toArray() + [
                'url' => route( 'cms.page', ['path' => $item->path] ),
                'editor' => $item->latest?->editor,
                'deleted' => $item->trashed(),
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
            'term' => $schema->string()
                ->description('Search keyword, e.g., "blog", "product", or "FAQ". One word or page path only.'),
            'lang' => $schema->string()
                ->description('ISO language code from the get-locales tool call, e.g., "en" or "en-US".'),
            'trashed' => $schema->string()
                ->description('Include trashed items: "without" (default), "with" (include deleted), or "only" (only deleted).'),
            'publish' => $schema->string()
                ->description('Filter by publish status: "PUBLISHED", "DRAFT", or "SCHEDULED".'),
            'editor' => $schema->string()
                ->description('Filter by editor name.'),
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
