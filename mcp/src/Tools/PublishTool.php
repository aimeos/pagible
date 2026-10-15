<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Resource\Elements;
use Aimeos\Cms\Resource\Files;
use Aimeos\Cms\Resource\Pages;
use Aimeos\Cms\Resource\Variants;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Request;


/**
 * Base class of the tools publishing items.
 */
abstract class PublishTool extends Tool
{
    /**
     * Model class of the items
     *
     * @var class-string<Base>
     */
    protected const MODEL = Base::class;

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

        $ids = $v['id'];
        $items = match( static::MODEL ) {
            PageView::class => isset( $v['lang'] )
                ? Variants::publishMany( $ids, $v['lang'], $request->user(), $v['at'] ?? null )
                : Pages::publishMany( $ids, $request->user(), $v['at'] ?? null ),
            Element::class => Elements::publishMany( $ids, $request->user(), $v['at'] ?? null ),
            File::class => Files::publishMany( $ids, $request->user(), $v['at'] ?? null ),
            default => throw new \LogicException( 'No resource for ' . static::MODEL ),
        };

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
                ->description( 'An array of up to 50 ' . static::NOUN . ' UUIDs to publish.' )
                ->required(),
            'at' => $schema->string()
                ->description('Schedule publication for a future date/time in ISO 8601 format, e.g., "2026-04-01 12:00:00". Omit to publish immediately.'),
        ];
    }
}
