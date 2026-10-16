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
    use HandlesMedia;


    protected const PERMISSIONS = ['image:isolate', 'file:save', 'file:view'];


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return [
            'file' => $schema->string()
                ->description( 'The UUID of the image file whose background to remove.' )
                ->required(),
            'latest_id' => $this->latest( $schema ),
        ];
    }


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : ResponseFactory
    {
        return $this->edit( $request, [], [
            'file.required' => 'You must specify the UUID of the image file to isolate.',
        ], fn( $image ) => Ai::isolate( $image ) );
    }
}
