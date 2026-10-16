<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Facades\Elements;
use Aimeos\Cms\Facades\Files;
use Aimeos\Cms\Facades\Pages;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Request;


/**
 * Base class of the tools publishing items.
 */
abstract class PublishTool extends Tool
{
    /**
     * Resource class managing the items
     *
     * @var class-string<Pages|Elements|Files>
     */
    protected const RESOURCE = Pages::class;

    /**
     * Name of the item type used in messages
     */
    protected const NOUN = 'item';

    /**
     * Validation rules of the request
     *
     * @var array<string, string>
     */
    protected const RULES = [
        'id' => 'required|array|max:50',
        'id.*' => 'string|max:36',
        'at' => 'date',
    ];


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : ResponseFactory
    {
        $v = $request->validate( static::RULES, [
            'id.required' => 'You must specify an array of up to 50 IDs of the ' . static::NOUN . 's to publish.',
        ] );

        return Response::structured( Presenter::published( $this->publish( $v, $request->user() ), $v['id'], $v['at'] ?? null ) );
    }


    /**
     * Publishes the items.
     *
     * @param array<string, mixed> $v Validated input
     * @param Authenticatable|null $user Authenticated user
     * @return Collection<int, covariant \Aimeos\Cms\Models\Base> Published items
     */
    protected function publish( array $v, ?Authenticatable $user ) : Collection
    {
        return ( static::RESOURCE )::publishMany( $v['id'], $user, $v['at'] ?? null );
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
                ->description( 'An array of up to 50 ' . static::NOUN . ' UUIDs to publish.' )
                ->required(),
            'at' => $schema->string()
                ->description('Schedule publication for a future date/time in ISO 8601 format, e.g., "2026-04-01 12:00:00". Omit to publish immediately.'),
        ];
    }
}
