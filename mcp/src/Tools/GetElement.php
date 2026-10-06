<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

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
#[Name('get-element')]
#[Title('Get a shared content element by ID')]
#[Description('Retrieves a single shared content element by its ID. Returns the full element data including type, name, language, content data, and the latest draft version as a JSON object. The returned latest_id identifies the version you read — pass it back to save-element so concurrent edits are merged instead of overwritten.')]
class GetElement extends Tool
{
    protected const PERMISSIONS = ['element:view'];


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : \Laravel\Mcp\ResponseFactory
    {
        $v = $request->validate([
            'id' => 'required|string|max:36',
        ], [
            'id.required' => 'You must specify the ID of the element to retrieve.',
        ] );

        /** @var Element $element */
        $element = Element::withTrashed()->with( [
            'latest' => fn( $q ) => $q->select( [...Version::SELECT_COLUMNS, 'publish_at', 'created_at'] )
        ] )->findOrFail( $v['id'] );

        $data = Presenter::element( $element ) + [
            'used_by_pages' => Presenter::rows( $element->bypages(), 'cms_pages.id', 'cms_pages.name', 'cms_pages.path' ),
        ];

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
}
