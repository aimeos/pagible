<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Ai;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Request;


#[Name('isolate-image')]
#[Title('Remove the background of an image')]
#[Description('Removes the background of an existing image using AI and stores the result as a new draft version of the same file. Returns the updated file.')]
class IsolateImage extends Tool
{
    use Concerns\HandlesMedia;


    protected const PERMISSIONS = ['image:isolate', 'file:save', 'file:view'];


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return $this->media( $schema, [], 'The UUID of the image file whose background to remove.' );
    }


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : ResponseFactory
    {
        return $this->edit( $request, [], [], Ai::isolate( ... ) );
    }
}
