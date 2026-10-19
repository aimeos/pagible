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


#[Name('erase-image')]
#[Title('Erase part of an image')]
#[Description('Removes the part of an image covered by a mask (black: keep, white: erase) using AI and stores the result as a new draft version of the source file. Both the image and the mask are referenced by file ID. Returns the updated file.')]
class EraseImage extends Tool
{
    use HandlesMedia;


    protected const PERMISSIONS = ['image:erase', 'file:save', 'file:view'];


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return [
            'file' => $schema->string()
                ->description( 'The UUID of the image file to edit.' )
                ->required(),
            'mask' => $schema->string()
                ->description( 'The UUID of the mask image file (black: keep, white: erase).' )
                ->required(),
            'latest_id' => $this->latest( $schema ),
        ];
    }


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : ResponseFactory
    {
        return $this->edit( $request, [
            'mask' => 'required|string|max:36',
        ], [
            'file.required' => 'You must specify the UUID of the image file to edit.',
            'mask.required' => 'You must specify the UUID of the mask image file.',
        ], fn( $image, $v ) => Ai::erase( $image, $this->image( $v['mask'], $request->user(), 'Mask' ) ) );
    }
}
