<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Resource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('save-element')]
#[Title('Save a shared content element')]
#[Description('Updates an existing element by ID. Only send fields you want to change — unsent fields are preserved. Returns the updated element as JSON.')]
class SaveElement extends Tool
{
    protected const PERMISSIONS = ['element:save'];


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : \Laravel\Mcp\ResponseFactory
    {
        $v = $request->validate([
            'id' => 'required|string|max:36',
            'name' => 'string|max:100',
            'lang' => 'nullable|string|max:10',
            'data' => 'array',
            'latest_id' => 'required|string|max:36',
        ], [
            'id.required' => 'You must specify the ID of the element to save.',
            'latest_id.required' => 'You must pass the latest_id returned by get-element, add-element, or a previous save-element so concurrent edits are detected.',
        ] );

        $input = array_diff_key( $v, array_flip( ['id', 'latest_id'] ) );
        $element = Resource::saveElement( $v['id'], $input, $request->user(), $v['latest_id'] );

        return Response::structured( Presenter::saved( Presenter::element( $element ), $element ) );
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
                ->description( 'The UUID of the element to save. Use search-elements to find the ID.' )
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
}
