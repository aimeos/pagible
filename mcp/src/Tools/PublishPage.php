<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Publication;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;


#[Name('publish-page')]
#[Title('Publish one or more pages')]
#[Description('Publishes one or more pages by ID, in the language passed by lang or in their source language. Pass an array of up to 50 UUIDs. Optionally schedule for a future ISO 8601 datetime via "at". Returns published and skipped items with reasons.')]
class PublishPage extends Tool
{
    protected const PERMISSIONS = ['page:publish'];


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : \Laravel\Mcp\ResponseFactory
    {
        $v = $request->validate([
            'id' => 'required|array|max:50',
            'id.*' => 'string|max:36',
            'at' => 'date',
            'lang' => 'string|max:10',
        ], [
            'id.required' => 'You must specify an array of up to 50 IDs of the pages to publish.',
        ] );

        $ids = $v['id'];
        $items = Publication::publish( Page::class, $ids, $request->user(), $v['at'] ?? null, $v['lang'] ?? null );

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
                ->description('An array of up to 50 page UUIDs to publish.')
                ->required(),
            'lang' => $schema->string()
                ->description('ISO language code of the language variants to publish, e.g., "de". Omit to publish the source language variants.'),
            'at' => $schema->string()
                ->description('Schedule publication for a future date/time in ISO 8601 format, e.g., "2026-04-01 12:00:00". Omit to publish immediately.'),
        ];
    }
}
