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
use Laravel\Mcp\Response;
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
            'latest_id' => $schema->string()
                ->description( 'Required. The latest_id value returned by get-file, add-file, or your previous save-file for this file. Ensures edits made by another editor in the meantime are merged instead of overwritten.' )
                ->required(),
        ];
    }


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : ResponseFactory
    {
        $v = $request->validate( [
            'file' => 'required|string|max:36',
            'mask' => 'required|string|max:36',
            'latest_id' => 'required|string|max:36',
        ], [
            'file.required' => 'You must specify the UUID of the image file to edit.',
            'mask.required' => 'You must specify the UUID of the mask image file.',
            'latest_id.required' => 'You must pass the latest_id returned by get-file, add-file, or a previous save-file so concurrent edits are detected.',
        ] );

        $image = $this->image( $v['file'] );
        $mask = $this->image( $v['mask'], 'Mask' );

        $base64 = Ai::erase( $image, $mask );

        return Response::structured( $this->update( $v['file'], $base64, $v['latest_id'], $request->user() ) );
    }
}
