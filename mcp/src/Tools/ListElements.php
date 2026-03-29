<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

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
#[Name('list-elements')]
#[Title('List shared content elements with optional filters')]
#[Description('Lists up to 25 shared content elements. Optional filters: type, lang (ISO code). Returns JSON array with id, type, name, lang, data, deleted, timestamps.')]
class ListElements extends Tool
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
            'type' => 'string|max:50',
            'lang' => 'nullable|string|max:5',
        ]);

        $query = Element::withTrashed()->orderBy( 'updated_at', 'desc' );

        if( isset( $v['type'] ) ) {
            $query->where( 'type', $v['type'] );
        }

        if( array_key_exists( 'lang', $v ) ) {
            $query->where( 'lang', $v['lang'] );
        }

        $result = $query->take( 25 )->get()->map( function( $item ) {
            /** @var Element $item */
            return [
                'id' => $item->id,
                'type' => $item->type,
                'name' => $item->name,
                'lang' => $item->lang,
                'data' => $item->data,
                'deleted' => $item->trashed(),
                'created_at' => $item->created_at?->format( 'Y-m-d H:i:s' ),
                'updated_at' => $item->updated_at?->format( 'Y-m-d H:i:s' ),
            ];
        } );

        return Response::structured( ['elements' => $result->all()] );
    }


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return [
            'type' => $schema->string()
                ->description('Filter by element type, e.g., "heading", "text", "image", "contact".'),
            'lang' => $schema->string()
                ->description('Filter by ISO language code, e.g., "en" or "de".'),
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
