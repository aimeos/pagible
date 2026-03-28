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
#[Name('get-element')]
#[Title('Get a shared content element by ID')]
#[Description('Retrieves a single shared content element by its ID. Returns the full element data including type, name, language, content data, and the latest draft version as a JSON object.')]
class GetElement extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle( Request $request ): \Laravel\Mcp\ResponseFactory
    {
        if( !Permission::can( 'element:view', $request->user() ) ) {
            throw new \Exception( 'Insufficient permissions' );
        }

        $validated = $request->validate([
            'id' => 'required|string|max:36',
        ], [
            'id.required' => 'You must specify the ID of the element to retrieve.',
        ] );

        /** @var Element|null $element */
        $element = Element::withTrashed()->find( $validated['id'] );

        if( !$element ) {
            return Response::structured( ['error' => 'Element not found.'] );
        }

        $data = $element->toArray();

        if( $latest = $element->latest ) {
            $data['latest_version'] = array_merge(
                (array) $latest->data,
                ['published' => $latest->published],
                ['publish_at' => $latest->publish_at],
                ['editor' => $latest->editor],
            );
        }

        return Response::structured( $data );
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
                ->description('The UUID of the element to retrieve.')
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
        return Permission::can( 'element:view', $request->user() );
    }
}
