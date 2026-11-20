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


#[Name('inpaint-image')]
#[Title('Edit part of an image using a mask and prompt')]
#[Description('Replaces the masked area of an image based on a text prompt using AI and stores the result as a new draft version of the source file. Both the image and the mask are referenced by file ID. Returns the updated file.')]
class InpaintImage extends Tool
{
    use Concerns\HandlesMedia;


    protected const PERMISSIONS = ['image:inpaint', 'file:save', 'file:view'];


    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema( JsonSchema $schema ) : array
    {
        return $this->media( $schema, [
            'prompt' => $schema->string()
                ->description( 'Describe what to generate in the masked area.' )
                ->required(),
        ], mask: 'The UUID of the mask image file marking the area to replace.' );
    }


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : ResponseFactory
    {
        return $this->masked( $request, [
            'prompt' => 'required|string|max:2000',
        ], [
            'prompt.required' => 'You must provide a prompt describing the desired content.',
        ], fn( Image $image, Image $mask, array $v ) => Ai::inpaint( $image, $mask, $v['prompt'] ) );
    }
}
