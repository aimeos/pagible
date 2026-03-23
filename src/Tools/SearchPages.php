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
#[Description('Searches the page tree for pages containing the keywords. Returns up to 10 matching pages as JSON array.')]
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
            'term.required' => 'You must specify a search term. For example, "blog" or "product documentation".',
        ] );

        $lang = $validated['lang'];
        $term = $validated['term'];

        $result = Page::search( $term )
            ->where( 'lang', $lang )
            ->searchFields( 'draft' ) // @phpstan-ignore method.notFound
            ->take( 25 )
            ->get()
            ->map( function( $item ) {
                /** @var Page $item */
                return $item->toArray() + ['url' => route( 'cms.page', ['path' => $item->path] )];
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
