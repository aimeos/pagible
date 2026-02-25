<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Permission;
use Aimeos\Cms\Models\Page;
use Aimeos\AnalyticsBridge\Facades\Analytics;
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
#[IsOpenWorld]
#[Name('search-pages')]
#[Title('Search for pages by keyword')]
#[Description('Searches the page tree for pages containing a keyword. Returns up to 10 matching pages as JSON array.')]
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

        $validated = $request->validate([
            'lang' => 'required|string|max:5',
            'term' => 'required|string|max:255',
        ], [
            'lang.required' => 'You must specify a language code from the list of available locales. For example, "en" or "en-US".',
            'term.required' => 'You must specify a search term. For example, "blog" or "product".',
        ] );

        $lang = $validated['lang'];
        $term = $validated['term'];

        $result = Page::withoutTrashed()
            ->where( function( $builder ) use ( $lang, $term ) {
                $builder->whereAny( ['content', 'meta', 'name', 'path', 'title'], 'like', '%' . $term . '%' )
                    ->where( 'lang', $lang );
            } )
            ->orWhereHas('latest', function( $builder ) use ( $lang, $term  ) {
                $builder->whereAny( ['aux->content', 'aux->meta', 'data->name', 'data->path', 'data->title'], 'like', '%' . $term . '%' )
                    ->where( 'lang', $lang );
            } )
            ->take( 10 )
            ->get()
            ->map( function( $item ) {
                /** @var Page $item */
                return $item->toArray() + ['url' => route( 'cms.page', ['path' => $item->path] )];
            } );

        return Response::structured( $result->all() );
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
                ->description('ISO language code from the get-locales tool call, e.g., "en" or "en-US".')
                ->required(),
            'term' => $schema->string()
                ->description('Search keyword, e.g., "blog", "product", or "FAQ". One word or page path only.')
                ->required(),
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
