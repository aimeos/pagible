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


#[Name('upscale-image')]
#[Title('Upscale an image')]
#[Description('Increases the resolution of an existing image by the given factor using AI and stores the result as a new draft version of the same file. Returns the updated file.')]
class UpscaleImage extends Tool
{
    use HandlesMedia;


    protected const PERMISSIONS = ['image:upscale', 'file:save', 'file:view'];


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return [
            'file' => $schema->string()
                ->description( 'The UUID of the image file to upscale.' )
                ->required(),
            'factor' => $schema->integer()
                ->description( 'Upscale factor between 2 and 4.' )
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
            'factor' => 'required|integer|min:2|max:4',
            'latest_id' => 'required|string|max:36',
        ], [
            'file.required' => 'You must specify the UUID of the image file to upscale.',
            'factor.required' => 'You must specify the upscale factor, e.g., 2 or 4.',
            'latest_id.required' => 'You must pass the latest_id returned by get-file, add-file, or a previous save-file so concurrent edits are detected.',
        ] );

        $image = $this->image( $v['file'] );
        $base64 = Ai::upscale( $image, $v['factor'] );

        return Response::structured( $this->update( $v['file'], $base64, $v['latest_id'], $request->user() ) );
    }
}
