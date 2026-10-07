<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Filter;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\Version;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[IsReadOnly]
#[Name('search-elements')]
#[Title('Search shared content elements')]
#[Description('Lists and searches shared content elements. Optional: term (full-text search), type, lang, trashed, publish, editor. Without term, returns all matching elements. Returns up to 25 results.')]
class SearchElements extends Tool
{
    protected const PERMISSIONS = ['element:view'];


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : \Laravel\Mcp\ResponseFactory
    {
        $v = $request->validate([
            'term' => 'string|max:255',
            'type' => 'string|max:50',
            'lang' => 'nullable|string|max:10',
            'trashed' => 'string|in:without,with,only',
            'publish' => 'string|in:PUBLISHED,DRAFT,SCHEDULED',
            'editor' => 'string|max:255',
        ] );

        $search = Filter::search( Element::class, $v['term'] ?? '' )
            ->query( fn( $q ) => $q->select( 'cms_elements.id', 'cms_elements.tenant_id', 'cms_elements.created_at', 'cms_elements.updated_at', 'cms_elements.deleted_at', 'cms_elements.latest_id' )
            ->with( ['latest' => fn( $q ) => $q->select( Version::SELECT_COLUMNS )] ) )
            ->take( 25 );

        $result = [];

        foreach( Filter::elements( $search, $v )->get() as $item )
        {
            /** @var Element $item */
            $result[] = Presenter::element( $item, false );
        }

        return Response::structured( ['elements' => $result] );
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
                ->description('Search keyword to match against element name or data content.'),
            'type' => $schema->string()
                ->description('Filter by element type, e.g., "heading", "text", "image", "contact".'),
            'lang' => $schema->string()
                ->description('Filter by ISO language code, e.g., "en" or "de".'),
            'trashed' => $schema->string()
                ->description('Include trashed items: "without" (default), "with" (include deleted), or "only" (only deleted).'),
            'publish' => $schema->string()
                ->description('Filter by publish status: "PUBLISHED", "DRAFT", or "SCHEDULED".'),
            'editor' => $schema->string()
                ->description('Filter by editor name.'),
        ];
    }
}
