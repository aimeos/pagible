<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Controllers;

use Aimeos\Cms\JsonSchema;
use Illuminate\Http\JsonResponse;


/**
 * Returns the registered CMS schemas.
 */
class SchemaController
{
    /**
     * Returns the JSON Schema document.
     */
    public function __invoke() : JsonResponse
    {
        return response()->json( [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'properties' => [
                'content' => JsonSchema::build( 'content', strict: true ),
                'meta' => JsonSchema::build( 'meta', strict: true ),
                'config' => JsonSchema::build( 'config', strict: true ),
            ],
            'additionalProperties' => false,
        ] )
            ->header( 'Content-Type', 'application/schema+json' );
    }
}
