<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
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
            throw new \Exception( 'Insufficient permissions' );
        }

        $v = $request->validate([
            'id' => 'required|string|max:36',
            'name' => 'string|max:100',
            'lang' => 'nullable|string|max:5',
            'data' => 'array',
            'files' => 'array',
            'files.*' => 'string|max:36',
            'latestId' => 'string|max:36',
        ], [
            'id.required' => 'You must specify the ID of the element to save.',
        ] );

        try {
            $input = array_diff_key( $v, array_flip( ['id', 'files', 'latestId'] ) );
            $element = Resource::saveElement( $v['id'], $input, $request->user(), $v['files'] ?? null, $v['latestId'] ?? null );
        } catch( ModelNotFoundException $e ) {
            return Response::structured( ['error' => 'Element not found.'] );
        }

        $data = (array) ( $element->latest->data ?? [] );

        return Response::structured( [
            'id' => $element->id,
            'type' => $data['type'] ?? '',
            'name' => $data['name'] ?? '',
            'lang' => $element->latest?->lang,
            'data' => $data['data'] ?? new \stdClass(),
            'changes' => $element->changed(),
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
            'files' => $schema->array()
                ->items( $schema->string() )
                ->description( 'Array of file UUIDs to attach to the version.' ),
            'latestId' => $schema->string()
                ->description( 'Version ID the caller last retrieved. Enables conflict detection and three-way merge when another editor has saved in the meantime.' ),
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
