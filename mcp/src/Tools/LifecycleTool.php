<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Models\Base;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Request;


/**
 * Base class of the tools changing the lifecycle state of a single item.
 */
abstract class LifecycleTool extends Tool
{
    /**
     * Model class of the item
     *
     * @var class-string<Base>
     */
    protected const MODEL = Base::class;

    /**
     * Name of the item type used in messages
     */
    protected const NOUN = 'item';

    /**
     * Action used in messages
     */
    protected const VERB = 'change';

    /**
     * Item state prefix used in the schema description
     */
    protected const PREFIX = '';

    /**
     * Validation rules of the request
     *
     * @var array<string, string>
     */
    protected const RULES = [
        'id' => 'required|string|max:36',
    ];


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : ResponseFactory
    {
        $v = $request->validate( static::RULES, [
            'id.required' => 'You must specify the ID of the ' . static::NOUN . ' to ' . static::VERB . '.',
        ] );

        return Response::structured( $this->apply( $v, $request->user() ) );
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
                ->description( 'The UUID of the ' . static::PREFIX . static::NOUN . ' to ' . static::VERB . '.' )
                ->required(),
        ];
    }


    /**
     * Applies the action to the item.
     *
     * @param array<string, mixed> $v Validated input
     * @param Authenticatable|null $user Authenticated user
     * @return array<string, mixed> Changed item or error
     */
    abstract protected function apply( array $v, ?Authenticatable $user ) : array;
}
