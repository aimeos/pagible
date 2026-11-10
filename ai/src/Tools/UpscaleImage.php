<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Ai;
use Aimeos\Prisma\Files\Image;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Request;


#[Name('upscale-image')]
#[Title('Upscale an image')]
#[Description('Increases the resolution of an existing image by the given factor using AI and stores the result as a new draft version of the same file. Returns the updated file.')]
class UpscaleImage extends Tool
{
    use Concerns\HandlesMedia;


    protected const PERMISSIONS = ['image:upscale', 'file:save', 'file:view'];


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return $this->media( $schema, [
            'factor' => $schema->integer()
                ->description( 'Upscale factor between 2 and 4.' )
                ->required(),
        ], 'The UUID of the image file to upscale.' );
    }


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : ResponseFactory
    {
        return $this->edit( $request, [
            'factor' => 'required|integer|min:2|max:4',
        ], [
            'factor.required' => 'You must specify the upscale factor, e.g., 2 or 4.',
        ], fn( Image $image, array $v ) => Ai::upscale( $image, $v['factor'] ) );
    }
}
