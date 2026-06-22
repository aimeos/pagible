<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Permission;
use Aimeos\Cms\Resource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('save-element')]
#[Title('Save a shared content element')]
#[Description('Updates an existing element by ID. Only send fields you want to change — unsent fields are preserved. Returns the updated element as JSON.')]
class SaveElement extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'element:save', $request->user() ) ) {
            throw new \Aimeos\Cms\Exception( 'Insufficient permissions' );
        }

        $v = $request->validate([
            'id' => 'required|string|max:36',
            'name' => 'string|max:100',
            'lang' => 'nullable|string|max:5',
            'data' => 'array',
            'latest_id' => 'required|string|max:36',
        ], [
            'id.required' => 'You must specify the ID of the element to save.',
            'latest_id.required' => 'You must pass the latest_id returned by get-element, add-element, or a previous save-element so concurrent edits are detected.',
        ] );

        try {
            $input = array_diff_key( $v, array_flip( ['id', 'latest_id'] ) );
            $element = Resource::saveElement( $v['id'], $input, $request->user(), $v['latest_id'] ?? null );
        } catch( ModelNotFoundException $e ) {
            return Response::structured( ['error' => 'Element not found.'] );
        }

        $data = (array) ( $element->latest->data ?? [] );

        return Response::structured( [
            'id' => $element->id,
            'latest_id' => $element->latest_id,
            'type' => $data['type'] ?? '',
            'name' => $data['name'] ?? '',
            'lang' => $element->latest?->lang,
            'data' => $data['data'] ?? new \stdClass(),
            'changed' => $element->changed,
            'created_at' => (string) $element->created_at,
            'updated_at' => (string) $element->updated_at,
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
                ->description( 'The UUID of the element to save. Use search-elements or list-elements to find the ID.' )
                ->required(),
            'name' => $schema->string()
                ->description( 'New name for the element.' ),
            'lang' => $schema->string()
                ->description( 'ISO language code for the version.' ),
            'data' => $schema->object()
                ->description( 'Element data as a JSON object. Fields depend on the element type. Use get-element to see the current type and get-schemas for available fields.' ),
            'latest_id' => $schema->string()
                ->description( 'Required. The latest_id value returned by get-element, add-element, or your previous save-element for this element. Ensures edits made by another editor in the meantime are merged instead of overwritten.' )
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
        return Permission::can( 'element:save', $request->user() );
    }
}
