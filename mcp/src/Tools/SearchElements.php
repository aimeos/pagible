<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Filter;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Models\Element;
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
#[Name('search-elements')]
#[Title('Search shared content elements')]
#[Description('Lists and searches shared content elements. Optional: term (full-text search), type, lang, trashed, publish, editor. Without term, returns all matching elements. Returns up to 25 results.')]
class SearchElements extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'element:view', $request->user() ) ) {
            throw new \Exception( 'Insufficient permissions' );
        }

        $v = $request->validate([
            'term' => 'string|max:255',
            'type' => 'string|max:50',
            'lang' => 'nullable|string|max:5',
            'trashed' => 'string|in:without,with,only',
            'publish' => 'string|in:PUBLISHED,DRAFT,SCHEDULED',
            'editor' => 'string|max:255',
        ] );

        $search = Element::search( mb_substr( trim( (string) ( $v['term'] ?? '' ) ), 0, 200 ) )
            ->query( fn( $q ) => $q->select( 'cms_elements.id', 'created_at', 'updated_at', 'deleted_at', 'latest_id' )
            ->with( ['latest' => fn( $q ) => $q->select( 'id', 'versionable_id', 'data', 'lang', 'editor' )] ) )
            ->searchFields( 'draft' )
            ->take( 25 );

        $result = [];

        foreach( Filter::elements( $search, $v )->get() as $item )
        {
            /** @var Element $item */
            $latest = $item->latest;
            $data = $latest->data ?? new \stdClass();
            $result[] = [
                'id' => $item->id,
                'type' => $data->type ?? null,
                'name' => $data->name ?? null,
                'lang' => $latest?->lang,
                'editor' => $latest?->editor,
                'deleted' => $item->trashed(),
                'created_at' => $item->created_at?->format( 'Y-m-d H:i:s' ),
                'updated_at' => $item->updated_at?->format( 'Y-m-d H:i:s' ),
            ];
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


    /**
     * Determine if the tool should be registered.
     *
     * @param Request $request The incoming request to check permissions for.
     * @return bool TRUE if the tool should be registered, FALSE otherwise.
     */
    public function shouldRegister( Request $request ) : bool
    {
        return Permission::can( 'element:view', $request->user() );
    }
}
