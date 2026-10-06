<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Publication;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('publish-element')]
#[Title('Publish one or more shared content elements')]
#[Description('Publishes one or more shared elements by ID. Pass an array of up to 50 UUIDs. Optionally schedule via "at". Returns published and skipped items.')]
class PublishElement extends Tool
{
    protected const PERMISSIONS = ['element:publish'];


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : \Laravel\Mcp\ResponseFactory
    {
        $v = $request->validate([
            'id' => 'required|array|max:50',
            'id.*' => 'string|max:36',
            'at' => 'date',
        ], [
            'id.required' => 'You must specify an array of up to 50 IDs of the elements to publish.',
        ] );

        $ids = $v['id'];
        $items = Publication::publish( Element::class, $ids, $request->user(), $v['at'] ?? null );

        return Response::structured( Presenter::published( $items, $ids, $v['at'] ?? null ) );
    }


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return [
            'id' => $schema->array()
                ->items( $schema->string() )
                ->description('An array of up to 50 element UUIDs to publish.')
                ->required(),
            'at' => $schema->string()
                ->description('Schedule publication for a future date/time in ISO 8601 format, e.g., "2026-04-01 12:00:00". Omit to publish immediately.'),
        ];
    }
}
